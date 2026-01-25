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

namespace local_aigrading\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_aigrading\ai_service;
use local_aigrading\file_extractor;

/**
 * External function to suggest grade for a file submission.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggest_grade_file extends external_api
{

    /**
     * Returns the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'assignmentdesc' => new external_value(PARAM_RAW, 'Assignment description'),
            'maxgrade' => new external_value(PARAM_FLOAT, 'Maximum grade'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID  
     * @param string $assignmentdesc Assignment description
     * @param float $maxgrade Maximum grade
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $assignmentdesc, float $maxgrade): array
    {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'assignmentdesc' => $assignmentdesc,
            'maxgrade' => $maxgrade,
        ]);

        // Check capability.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);

        // Get submission text.
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

        // Get the latest submission.
        $submission = $DB->get_record_sql(
            "SELECT * FROM {assign_submission} 
             WHERE assignment = :assignid AND userid = :userid AND latest = 1",
            ['assignid' => $assign->id, 'userid' => $params['userid']]
        );

        if (!$submission) {
            return [
                'success' => false,
                'grade' => 0,
                'feedback' => '',
                'explanation' => '',
                'confidence' => 'low',
                'error' => 'No submission found.',
            ];
        }

        $submissionText = '';

        // Try online text first.
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
            'submission' => $submission->id
        ]);
        if ($onlinetext && !empty($onlinetext->onlinetext)) {
            $submissionText = strip_tags($onlinetext->onlinetext);
        }

        // If no online text, try file.
        if (empty(trim($submissionText))) {
            $fs = get_file_storage();
            $extractor = new file_extractor();

            $files = $fs->get_area_files(
                $context->id,
                'assignsubmission_file',
                'submission_files',
                $submission->id,
                'sortorder, id',
                false
            );

            foreach ($files as $file) {
                if ($extractor->is_supported($file->get_mimetype())) {
                    $result = $extractor->extract($file);
                    if ($result['success'] && !empty($result['text'])) {
                        $submissionText = $result['text'];
                        break;
                    }
                }
            }
        }

        if (empty(trim($submissionText))) {
            return [
                'success' => false,
                'grade' => 0,
                'feedback' => '',
                'explanation' => '',
                'confidence' => 'low',
                'error' => 'No text content found in submission. Only PDF, DOCX, and TXT files are supported.',
            ];
        }

        // Get AI suggestion.
        $service = new ai_service();
        $result = $service->suggest_grade(
            $params['assignmentdesc'],
            $submissionText,
            $params['maxgrade'],
            null,
            ''
        );

        return [
            'success' => $result['success'],
            'grade' => $result['grade'] ?? 0,
            'feedback' => $result['feedback'] ?? '',
            'explanation' => $result['explanation'] ?? '',
            'confidence' => $result['confidence'] ?? 'medium',
            'error' => $result['error'] ?? '',
        ];
    }

    /**
     * Returns the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure
    {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'grade' => new external_value(PARAM_FLOAT, 'Suggested grade'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback for student'),
            'explanation' => new external_value(PARAM_RAW, 'Explanation for teacher'),
            'confidence' => new external_value(PARAM_ALPHA, 'Confidence level'),
            'error' => new external_value(PARAM_RAW, 'Error message if failed'),
        ]);
    }
}
