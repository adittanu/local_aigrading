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
 * AI Service class for communicating with OpenAI API.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_service
{

    /** @var string API key */
    private string $apikey;

    /** @var string Base URL */
    private string $baseurl;

    /** @var string Model */
    private string $model;

    /** @var string System prompt */
    private string $systemprompt;

    /** @var string Default rubric */
    private string $defaultrubric;

    /** @var int Max tokens */
    private int $maxtokens;

    /** @var float Temperature */
    private float $temperature;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->apikey = get_config('local_aigrading', 'apikey');
        $this->baseurl = get_config('local_aigrading', 'apibaseurl') ?: 'https://api.openai.com/v1';
        $this->model = get_config('local_aigrading', 'model') ?: 'gpt-4o-mini';
        $this->systemprompt = get_config('local_aigrading', 'systemprompt') ?: '';
        $this->defaultrubric = get_config('local_aigrading', 'defaultrubric') ?: '';
        $this->maxtokens = (int)(get_config('local_aigrading', 'maxtokens') ?: 1000);
        $this->temperature = (float)(get_config('local_aigrading', 'temperature') ?: 0.3);
    }

    /**
     * Check if the service is configured.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        return !empty($this->apikey);
    }

    /**
     * Suggest a grade for an essay answer.
     *
     * @param string $questiontext The question text
     * @param string $answertext The student's answer
     * @param float $maxgrade Maximum possible grade
     * @param string|null $rubric Custom rubric (uses default if null)
     * @param string|null $graderinfo Grading information/model answer from question
     * @return array{success: bool, grade?: float, feedback?: string, explanation?: string, confidence?: string, error?: string}
     */
    public function suggest_grade(string $questiontext, string $answertext, float $maxgrade, ?string $rubric = null, ?string $graderinfo = null): array
    {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'error' => get_string('error:noapikey', 'local_aigrading'),
            ];
        }

        $rubric = $rubric ?: $this->defaultrubric;

        $userprompt = $this->build_user_prompt($questiontext, $answertext, $maxgrade, $rubric, $graderinfo);

        try {
            $response = $this->call_api($userprompt);
            return $this->parse_response($response, $maxgrade);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => get_string('error:apierror', 'local_aigrading', $e->getMessage()),
            ];
        }
    }

    /**
     * Bulk grade multiple answers.
     *
     * @param string $questiontext The question text
     * @param array $answers Array of answers with keys: id, text
     * @param float $maxgrade Maximum possible grade
     * @param string|null $rubric Custom rubric
     * @param string|null $graderinfo Grading information/model answer
     * @return array Array of results keyed by answer id
     */
    public function bulk_grade(string $questiontext, array $answers, float $maxgrade, ?string $rubric = null, ?string $graderinfo = null): array
    {
        $results = [];

        foreach ($answers as $answer) {
            $result = $this->suggest_grade($questiontext, $answer['text'], $maxgrade, $rubric, $graderinfo);
            $results[$answer['id']] = $result;
        }

        return $results;
    }

    /**
     * Build the user prompt for grading.
     *
     * @param string $questiontext
     * @param string $answertext
     * @param float $maxgrade
     * @param string $rubric
     * @param string|null $graderinfo
     * @return string
     */
    private function build_user_prompt(string $questiontext, string $answertext, float $maxgrade, string $rubric, ?string $graderinfo = null): string
    {
        $prompt = "## Pertanyaan:\n{$questiontext}\n\n";
        $prompt .= "## Jawaban Siswa:\n{$answertext}\n\n";
        $prompt .= "## Nilai Maksimum: {$maxgrade}\n\n";

        if (!empty($graderinfo)) {
            $prompt .= "## Informasi Penilaian / Contoh Jawaban yang Benar:\n{$graderinfo}\n\n";
        }

        if (!empty($rubric)) {
            $prompt .= "## Rubrik Penilaian:\n{$rubric}\n\n";
        }

        // Add uncertainty handling instructions.
        if (empty($graderinfo) && empty($rubric)) {
            $prompt .= "## CATATAN PENTING:\n";
            $prompt .= "Tidak ada contoh jawaban atau rubrik yang diberikan. ";
            $prompt .= "Nilai berdasarkan: (1) Kelengkapan dan kejelasan jawaban, ";
            $prompt .= "(2) Struktur dan koherensi argumen, (3) Penggunaan bahasa yang baik. ";
            $prompt .= "Jika Anda tidak yakin tentang kebenaran faktual, ";
            $prompt .= "set confidence ke 'low' dan jelaskan ketidakpastian di explanation.\n\n";
        }

        $prompt .= "Berikan penilaian dalam format JSON yang diminta. ";
        $prompt .= "Sertakan field 'confidence' dengan nilai 'high', 'medium', atau 'low' untuk menunjukkan tingkat keyakinan penilaian.";

        return $prompt;
    }

    /**
     * Call the OpenAI API.
     *
     * @param string $userprompt
     * @return string
     * @throws \Exception
     */
    private function call_api(string $userprompt): string
    {
        $url = rtrim($this->baseurl, '/') . '/chat/completions';

        $data = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemprompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userprompt,
                ],
            ],
            'max_tokens' => $this->maxtokens,
            'temperature' => $this->temperature,
            'response_format' => ['type' => 'json_object'],
        ];

        // Create curl with ignoresecurity flag to bypass Moodle's cURL security restrictions
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apikey,
        ]);
        
        // Disable SSL verification for local development
        $curl->setopt([
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => 0,
        ]);

        $response = $curl->post($url, json_encode($data));

        if ($curl->get_errno()) {
            throw new \Exception('cURL error: ' . $curl->error);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
        if ($httpcode >= 400) {
            $decoded = json_decode($response, true);
            $errormsg = $decoded['error']['message'] ?? 'HTTP ' . $httpcode;
            throw new \Exception($errormsg);
        }

        return $response;
    }

    /**
     * Parse the API response.
     *
     * @param string $response
     * @param float $maxgrade
     * @return array
     */
    private function parse_response(string $response, float $maxgrade): array
    {
        $decoded = json_decode($response, true);

        if (!isset($decoded['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'error' => get_string('error:invalidresponse', 'local_aigrading'),
            ];
        }

        $content = $decoded['choices'][0]['message']['content'];
        $gradedata = json_decode($content, true);

        if (!isset($gradedata['grade'])) {
            return [
                'success' => false,
                'error' => get_string('error:invalidresponse', 'local_aigrading'),
            ];
        }

        // Ensure grade is within bounds.
        $grade = (float)$gradedata['grade'];
        $grade = max(0, min($maxgrade, $grade));

        return [
            'success' => true,
            'grade' => $grade,
            'feedback' => $gradedata['feedback'] ?? '',
            'explanation' => $gradedata['explanation'] ?? '',
            'confidence' => $gradedata['confidence'] ?? 'medium',
        ];
    }
}
