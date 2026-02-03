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
 * Dali Service class for communicating with Dali backend via Laravel API.
 * Replaces direct OpenAI calls.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dali_service
{

    /** @var string API key */
    private string $apikey;

    /** @var string Base URL */
    private string $baseurl;

    /** @var string System prompt */
    private string $systemprompt;

    /** @var string Default rubric */
    private string $defaultrubric;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->apikey = get_config('local_aigrading', 'apikey');
        $this->baseurl = get_config('local_aigrading', 'apibaseurl') ?: 'http://localhost:8000';
        $this->systemprompt = get_config('local_aigrading', 'systemprompt') ?: '';
        $this->defaultrubric = get_config('local_aigrading', 'defaultrubric') ?: '';
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

        // Build payload for Mastra/Laravel backend
        $payload = [
            'questiontext' => $questiontext,
            'answertext' => $answertext,
            'maxgrade' => $maxgrade,
            'rubric' => $rubric,
            'graderinfo' => $graderinfo,
            'systemprompt' => $this->systemprompt // Pass system prompt context if needed by backend
        ];

        try {
            $response = $this->call_api($payload);
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

        // Currently implementing iterative calls as per previous pattern
        // TODO: Future optimization - implement bulk endpoint in Mastra if needed
        foreach ($answers as $answer) {
            $result = $this->suggest_grade($questiontext, $answer['text'], $maxgrade, $rubric, $graderinfo);
            $results[$answer['id']] = $result;
        }

        return $results;
    }

    /**
     * Call the Mastra/Laravel API.
     *
     * @param array $data
     * @return string
     * @throws \Exception
     */
    private function call_api(array $data): string
    {
        $url = rtrim($this->baseurl, '/') . '/api/moodle/grade';

        // Create curl with ignoresecurity flag to bypass Moodle's cURL security restrictions
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->apikey,
            'Accept: application/json'
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
            $errormsg = $decoded['message'] ?? $decoded['error'] ?? 'HTTP ' . $httpcode;
            if (is_array($errormsg)) {
                $errormsg = json_encode($errormsg);
            }
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

        if (json_last_error() !== JSON_ERROR_NONE) {
             return [
                'success' => false,
                'error' => get_string('error:invalidresponse', 'local_aigrading'),
            ];
        }

        if (empty($decoded['success']) && !isset($decoded['grade'])) {
             // Handle case where success might be implicit or backend error returned as 200 OK json
             if (isset($decoded['error'])) {
                 return [
                    'success' => false,
                    'error' => is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']),
                 ];
             }
        }

        // Expected format: { "success": true, "grade": 85.0, "feedback": "...", "explanation": "...", "confidence": "high" }
        // Fallback or validation
        if (!isset($decoded['grade'])) {
            return [
                'success' => false,
                'error' => get_string('error:invalidresponse', 'local_aigrading'),
            ];
        }

        // Ensure grade is within bounds.
        $grade = (float)$decoded['grade'];
        $grade = max(0, min($maxgrade, $grade));

        return [
            'success' => true,
            'grade' => $grade,
            'feedback' => $decoded['feedback'] ?? '',
            'explanation' => $decoded['explanation'] ?? '',
            'confidence' => $decoded['confidence'] ?? 'medium',
        ];
    }
}
