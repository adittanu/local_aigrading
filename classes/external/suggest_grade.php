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
use local_aigrading\mastra_service;

/**
 * External function to get AI grade suggestion for a single answer.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suggest_grade extends external_api
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
            'answertext' => new external_value(PARAM_RAW, 'The student answer text'),
            'maxgrade' => new external_value(PARAM_FLOAT, 'Maximum possible grade'),
            'rubric' => new external_value(PARAM_RAW, 'Optional custom rubric', VALUE_DEFAULT, ''),
            'graderinfo' => new external_value(PARAM_RAW, 'Grading information/model answer', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid Course module ID
     * @param string $questiontext Question text
     * @param string $answertext Answer text
     * @param float $maxgrade Maximum grade
     * @param string $rubric Optional rubric
     * @param string $graderinfo Optional grading information
     * @return array
     */
    public static function execute(int $cmid, string $questiontext, string $answertext, float $maxgrade, string $rubric = '', string $graderinfo = ''): array
    {
        global $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'questiontext' => $questiontext,
            'answertext' => $answertext,
            'maxgrade' => $maxgrade,
            'rubric' => $rubric,
            'graderinfo' => $graderinfo,
        ]);

        // Check capability.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);

        // Call AI service.
        $service = new mastra_service();
        $result = $service->suggest_grade(
            $params['questiontext'],
            $params['answertext'],
            $params['maxgrade'],
            !empty($params['rubric']) ? $params['rubric'] : null,
            !empty($params['graderinfo']) ? $params['graderinfo'] : null
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
            'confidence' => new external_value(PARAM_ALPHA, 'AI confidence level: high, medium, or low'),
            'error' => new external_value(PARAM_RAW, 'Error message if any'),
        ]);
    }
}
