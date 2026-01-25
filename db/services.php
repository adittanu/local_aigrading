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

/**
 * AI Grading plugin external services.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aigrading_suggest_grade' => [
        'classname' => 'local_aigrading\external\suggest_grade',
        'description' => 'Get AI suggestion for grading an essay answer',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/aigrading:useaigrading',
        'loginrequired' => true,
    ],
    'local_aigrading_bulk_grade' => [
        'classname' => 'local_aigrading\external\bulk_grade',
        'description' => 'Get AI suggestions for grading multiple essay answers',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/aigrading:useaigrading',
        'loginrequired' => true,
    ],
    'local_aigrading_auto_grade_question' => [
        'classname' => 'local_aigrading\external\auto_grade_question',
        'description' => 'Auto-grade all ungraded essays for a quiz question',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/aigrading:useaigrading, mod/quiz:grade',
        'loginrequired' => true,
    ],
    'local_aigrading_auto_grade_all' => [
        'classname' => 'local_aigrading\\external\\auto_grade_all',
        'description' => 'Auto-grade all ungraded essays for ALL questions in a quiz',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/aigrading:useaigrading, mod/quiz:grade',
        'loginrequired' => true,
    ],
    'local_aigrading_auto_grade_assignment' => [
        'classname' => 'local_aigrading\\external\\auto_grade_assignment',
        'description' => 'Auto-grade all ungraded online text submissions for an assignment',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/aigrading:useaigrading, mod/assign:grade',
        'loginrequired' => true,
    ],
    'local_aigrading_suggest_grade_file' => [
        'classname' => 'local_aigrading\\external\\suggest_grade_file',
        'description' => 'Get AI suggestion for grading a file submission',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/aigrading:useaigrading',
        'loginrequired' => true,
    ],
];
