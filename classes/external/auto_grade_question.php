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
use local_aigrading\mastra_service;

/**
 * External function to auto-grade all ungraded essays for a quiz question.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_grade_question extends external_api
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
            'slot' => new external_value(PARAM_INT, 'Question slot number'),
            'questionid' => new external_value(PARAM_INT, 'Question ID'),
        ]);
    }

    /**
     * Execute the function - grade all ungraded attempts for a question.
     *
     * @param int $cmid Course module ID
     * @param int $slot Question slot
     * @param int $questionid Question ID
     * @return array
     */
    public static function execute(int $cmid, int $slot, int $questionid): array
    {
        global $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'slot' => $slot,
            'questionid' => $questionid,
        ]);

        // Check capability.
        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/aigrading:useaigrading', $context);
        require_capability('mod/quiz:grade', $context);

        // Get the quiz.
        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        // Get question info.
        $question = $DB->get_record('question', ['id' => $params['questionid']], '*', MUST_EXIST);
        $questiontext = strip_tags($question->questiontext);
        $maxgrade = $question->defaultmark;

        // Get all ungraded attempts for this question.
        // We need to find attempts where the question step state is 'needsgrading'.
        $sql = "SELECT DISTINCT
                    qa.id as attemptid,
                    qa.uniqueid as qubaid,
                    qatt.id as questionattemptid
                FROM {quiz_attempts} qa
                JOIN {question_usages} qu ON qu.id = qa.uniqueid
                JOIN {question_attempts} qatt ON qatt.questionusageid = qu.id AND qatt.slot = :slot
                WHERE qa.quiz = :quizid
                AND qa.state = 'finished'
                AND qatt.questionid = :questionid
                AND EXISTS (
                    SELECT 1 FROM {question_attempt_steps} qas2 
                    WHERE qas2.questionattemptid = qatt.id 
                    AND qas2.state = 'needsgrading'
                )
                ORDER BY qa.id";

        $attempts = $DB->get_records_sql($sql, [
            'quizid' => $quiz->id,
            'slot' => $params['slot'],
            'questionid' => $params['questionid'],
        ]);

        if (empty($attempts)) {
            return [
                'success' => true,
                'graded' => 0,
                'failed' => 0,
                'message' => 'No ungraded attempts found. All essays may have been graded already.',
            ];
        }

        // Use AI service to grade each attempt.
        $service = new mastra_service();
        $graded = 0;
        $failed = 0;

        foreach ($attempts as $attempt) {
            try {
                // Load the question usage and get the answer.
                $quba = \question_engine::load_questions_usage_by_activity($attempt->qubaid);
                $qa = $quba->get_question_attempt($params['slot']);

                // Get the last response (student answer).
                $response = $qa->get_last_qt_data();
                $answertext = $response['answer'] ?? '';

                if (empty($answertext)) {
                    $failed++;
                    continue;
                }

                // Get AI suggestion.
                $result = $service->suggest_grade($questiontext, $answertext, $maxgrade);

                if ($result['success']) {
                    // Submit the grade using manual grading.
                    $quba->process_action($params['slot'], [
                        '-mark' => $result['grade'],
                        '-maxmark' => $maxgrade,
                        '-comment' => $result['feedback'],
                        '-commentformat' => FORMAT_HTML,
                    ]);
                    \question_engine::save_questions_usage_by_activity($quba);
                    $graded++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return [
            'success' => true,
            'graded' => $graded,
            'failed' => $failed,
            'message' => "Graded $graded attempts, $failed failed.",
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
            'graded' => new external_value(PARAM_INT, 'Number of attempts graded'),
            'failed' => new external_value(PARAM_INT, 'Number of attempts failed'),
            'message' => new external_value(PARAM_RAW, 'Status message'),
        ]);
    }
}
