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
use core_external\external_multiple_structure;
use core_external\external_value;
use local_aigrading\dali_service;

/**
 * External function to get AI grade suggestions for multiple answers (bulk grading).
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_grade extends external_api
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
            'questiontext' => new external_value(PARAM_RAW, 'The question text'),
            'answers' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_RAW, 'Answer identifier'),
                    'text' => new external_value(PARAM_RAW, 'The student answer text'),
                ])
            ),
            'maxgrade' => new external_value(PARAM_FLOAT, 'Maximum possible grade'),
            'rubric' => new external_value(PARAM_RAW, 'Optional custom rubric', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid Course module ID
     * @param string $questiontext Question text
     * @param array $answers Array of answers
     * @param float $maxgrade Maximum grade
     * @param string $rubric Optional rubric
     * @return array
     */
    public static function execute(int $cmid, string $questiontext, array $answers, float $maxgrade, string $rubric = ''): array
    {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'questiontext' => $questiontext,
            'answers' => $answers,
            'maxgrade' => $maxgrade,
            'rubric' => $rubric,
        ]);

        // Check capability.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);

        // Call AI service.
        $service = new dali_service();
        $results = $service->bulk_grade(
            $params['questiontext'],
            $params['answers'],
            $params['maxgrade'],
            !empty($params['rubric']) ? $params['rubric'] : null
        );

        // Format results for return.
        $formattedresults = [];
        foreach ($results as $id => $result) {
            $formattedresults[] = [
                'id' => (string)$id,
                'success' => $result['success'],
                'grade' => $result['grade'] ?? 0,
                'feedback' => $result['feedback'] ?? '',
                'explanation' => $result['explanation'] ?? '',
                'error' => $result['error'] ?? '',
            ];
        }

        return [
            'success' => true,
            'results' => $formattedresults,
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
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_RAW, 'Answer identifier'),
                    'success' => new external_value(PARAM_BOOL, 'Whether grading was successful'),
                    'grade' => new external_value(PARAM_FLOAT, 'Suggested grade'),
                    'feedback' => new external_value(PARAM_RAW, 'Feedback for student'),
                    'explanation' => new external_value(PARAM_RAW, 'Explanation for teacher'),
                    'error' => new external_value(PARAM_RAW, 'Error message if any'),
                ])
            ),
        ]);
    }
}
