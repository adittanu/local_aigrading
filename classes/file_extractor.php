<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_aigrading;

/**
 * File text extraction service.
 *
 * Extracts text content from various file formats for AI grading.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_extractor
{
    /** @var array Supported MIME types */
    private const SUPPORTED_TYPES = [
        'application/pdf' => 'extract_pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'extract_docx',
        'application/msword' => 'extract_doc',
        'text/plain' => 'extract_txt',
        'text/html' => 'extract_html',
    ];

    /** @var int Maximum text length to return */
    private int $maxtextlength;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->maxtextlength = (int) get_config('local_aigrading', 'maxtextlength') ?: 50000;
    }

    /**
     * Check if a file type is supported.
     *
     * @param string $mimetype MIME type of the file
     * @return bool
     */
    public function is_supported(string $mimetype): bool
    {
        return isset(self::SUPPORTED_TYPES[$mimetype]);
    }

    /**
     * Extract text from a stored file.
     *
     * @param \stored_file $file The file to extract text from
     * @return array ['success' => bool, 'text' => string, 'error' => string]
     */
    public function extract(\stored_file $file): array
    {
        $mimetype = $file->get_mimetype();

        if (!$this->is_supported($mimetype)) {
            return [
                'success' => false,
                'text' => '',
                'error' => "Unsupported file type: $mimetype",
            ];
        }

        $method = self::SUPPORTED_TYPES[$mimetype];

        try {
            $text = $this->$method($file);

            // Truncate if too long.
            if (strlen($text) > $this->maxtextlength) {
                $text = substr($text, 0, $this->maxtextlength) .
                    "\n\n[... Text truncated due to length limit ...]";
            }

            return [
                'success' => true,
                'text' => trim($text),
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract text from PDF file.
     *
     * @param \stored_file $file
     * @return string
     */
    private function extract_pdf(\stored_file $file): string
    {
        // Create temp file.
        $tempdir = make_temp_directory('aigrading');
        $tempfile = $tempdir . '/' . uniqid('pdf_') . '.pdf';
        $file->copy_content_to($tempfile);

        try {
            // Use pdftotext to extract text.
            $output = [];
            $returncode = 0;
            exec("pdftotext -layout " . escapeshellarg($tempfile) . " - 2>&1", $output, $returncode);

            if ($returncode !== 0) {
                throw new \Exception("pdftotext failed: " . implode("\n", $output));
            }

            return implode("\n", $output);
        } finally {
            // Clean up temp file.
            @unlink($tempfile);
        }
    }

    /**
     * Extract text from DOCX file.
     *
     * @param \stored_file $file
     * @return string
     */
    private function extract_docx(\stored_file $file): string
    {
        // Create temp file.
        $tempdir = make_temp_directory('aigrading');
        $tempfile = $tempdir . '/' . uniqid('docx_') . '.docx';
        $file->copy_content_to($tempfile);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tempfile) !== true) {
                throw new \Exception("Failed to open DOCX file");
            }

            // Read the main document content.
            $content = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($content === false) {
                throw new \Exception("Failed to read DOCX content");
            }

            // Parse XML and extract text.
            $text = $this->parse_docx_xml($content);
            return $text;
        } finally {
            @unlink($tempfile);
        }
    }

    /**
     * Parse DOCX XML content to extract text.
     *
     * @param string $xmlcontent
     * @return string
     */
    private function parse_docx_xml(string $xmlcontent): string
    {
        // Remove namespaces for easier parsing.
        $xmlcontent = preg_replace('/(<\/?)(w:|wp:|m:|r:|a:|v:|o:|pic:|c:|dgm:)/', '$1', $xmlcontent);

        $xml = @simplexml_load_string($xmlcontent);
        if ($xml === false) {
            // Fallback: strip tags and return.
            return strip_tags($xmlcontent);
        }

        $text = [];

        // Find all text nodes.
        $this->extract_text_nodes($xml, $text);

        return implode("\n", $text);
    }

    /**
     * Recursively extract text nodes from XML.
     *
     * @param \SimpleXMLElement $node
     * @param array $text
     */
    private function extract_text_nodes(\SimpleXMLElement $node, array &$text): void
    {
        $nodename = $node->getName();

        // Paragraph boundary.
        if ($nodename === 'p') {
            $paragraphtext = [];
            foreach ($node->children() as $child) {
                $this->extract_text_nodes($child, $paragraphtext);
            }
            if (!empty($paragraphtext)) {
                $text[] = implode('', $paragraphtext);
            }
            return;
        }

        // Text node.
        if ($nodename === 't') {
            $text[] = (string) $node;
            return;
        }

        // Recurse into children.
        foreach ($node->children() as $child) {
            $this->extract_text_nodes($child, $text);
        }
    }

    /**
     * Extract text from DOC file (old Word format).
     *
     * @param \stored_file $file
     * @return string
     */
    private function extract_doc(\stored_file $file): string
    {
        // Try antiword first, then catdoc.
        $tempdir = make_temp_directory('aigrading');
        $tempfile = $tempdir . '/' . uniqid('doc_') . '.doc';
        $file->copy_content_to($tempfile);

        try {
            // Try antiword.
            $output = [];
            $returncode = 0;
            exec("antiword " . escapeshellarg($tempfile) . " 2>&1", $output, $returncode);

            if ($returncode === 0) {
                return implode("\n", $output);
            }

            // Try catdoc.
            $output = [];
            exec("catdoc " . escapeshellarg($tempfile) . " 2>&1", $output, $returncode);

            if ($returncode === 0) {
                return implode("\n", $output);
            }

            // Last resort: try to extract any text.
            $content = $file->get_content();
            // Remove binary/control characters.
            $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $content);
            $text = preg_replace('/\s+/', ' ', $text);

            if (strlen(trim($text)) > 100) {
                return trim($text);
            }

            throw new \Exception("DOC extraction not available. Install antiword or catdoc.");
        } finally {
            @unlink($tempfile);
        }
    }

    /**
     * Extract text from TXT file.
     *
     * @param \stored_file $file
     * @return string
     */
    private function extract_txt(\stored_file $file): string
    {
        return $file->get_content();
    }

    /**
     * Extract text from HTML file.
     *
     * @param \stored_file $file
     * @return string
     */
    private function extract_html(\stored_file $file): string
    {
        $content = $file->get_content();

        // Remove script and style tags.
        $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);

        // Convert to text.
        $text = strip_tags($content);

        // Clean up whitespace.
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Get list of supported file extensions.
     *
     * @return array
     */
    public static function get_supported_extensions(): array
    {
        return ['pdf', 'docx', 'doc', 'txt', 'html', 'htm'];
    }
}
