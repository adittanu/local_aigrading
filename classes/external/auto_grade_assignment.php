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

/**
 * External function to auto-grade all ungraded submissions for an assignment.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_grade_assignment extends external_api
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
        ]);
    }

    /**
     * Execute the function - grade all ungraded submissions for the assignment.
     *
     * @param int $cmid Course module ID
     * @return array
     */
    public static function execute(int $cmid): array
    {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        // Check capability.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);
        require_capability('mod/assign:grade', $context);

        // Get the assignment.
        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

        // Create assignment instance.
        $context = \context_module::instance($cm->id);
        $assignment = new \assign($context, $cm, $course);

        // Get assignment description for context.
        $assignmentDescription = strip_tags($assign->intro ?? '');

        // Get all ungraded submissions (both online text and file).
        $sql = "SELECT DISTINCT s.id, s.userid, s.assignment, s.status
                FROM {assign_submission} s
                LEFT JOIN {assign_grades} g ON g.assignment = s.assignment 
                    AND g.userid = s.userid AND g.attemptnumber = s.attemptnumber
                WHERE s.assignment = :assignmentid
                AND s.status = 'submitted'
                AND s.latest = 1
                AND (g.grade IS NULL OR g.grade < 0)
                ORDER BY s.id";

        $submissions = $DB->get_records_sql($sql, ['assignmentid' => $assign->id]);

        if (empty($submissions)) {
            return [
                'success' => true,
                'graded' => 0,
                'failed' => 0,
                'message' => 'No ungraded submissions found.',
            ];
        }

        // Use AI service and file extractor.
        $service = new \local_aigrading\ai_service();
        $extractor = new \local_aigrading\file_extractor();
        $fs = get_file_storage();

        $totalGraded = 0;
        $totalFailed = 0;
        $maxgrade = $assign->grade > 0 ? $assign->grade : 100;

        foreach ($submissions as $submission) {
            try {
                $submissionText = '';

                // Try online text first.
                $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
                    'submission' => $submission->id
                ]);
                if ($onlinetext && !empty($onlinetext->onlinetext)) {
                    $submissionText = strip_tags($onlinetext->onlinetext);
                }

                // If no online text, try file submissions.
                if (empty(trim($submissionText))) {
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
                                break; // Use first extractable file.
                            }
                        }
                    }
                }

                if (empty(trim($submissionText))) {
                    $totalFailed++;
                    continue;
                }

                // Get AI suggestion.
                $result = $service->suggest_grade(
                    $assignmentDescription,
                    $submissionText,
                    $maxgrade,
                    null,
                    ''
                );

                if ($result['success']) {
                    // Save the grade using assignment API.
                    $gradedata = new \stdClass();
                    $gradedata->grade = $result['grade'];
                    $gradedata->attemptnumber = -1; // Latest attempt.

                    // Add feedback.
                    $gradedata->assignfeedbackcomments_editor = [
                        'text' => $result['feedback'],
                        'format' => FORMAT_HTML
                    ];

                    $assignment->save_grade($submission->userid, $gradedata);
                    $totalGraded++;
                } else {
                    $totalFailed++;
                }
            } catch (\Exception $e) {
                $totalFailed++;
            }
        }

        return [
            'success' => true,
            'graded' => $totalGraded,
            'failed' => $totalFailed,
            'message' => "Graded $totalGraded submissions, $totalFailed failed.",
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
            'graded' => new external_value(PARAM_INT, 'Number of submissions graded'),
            'failed' => new external_value(PARAM_INT, 'Number of submissions failed'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
