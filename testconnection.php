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
 * Test connection handler for AI Grading plugin.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_sesskey();
require_capability('moodle/site:config', context_system::instance());

$apikey = get_config('local_aigrading', 'apikey');
$apibaseurl = get_config('local_aigrading', 'apibaseurl') ?: 'http://localhost:8000';

$result = [
    'success' => false,
    'message' => ''
];

if (empty($apikey)) {
    $result['message'] = get_string('testconnection_noapikey', 'local_aigrading');
} else {
    // Test connection to Laravel/Dali API
    $testurl = rtrim($apibaseurl, '/') . '/api/moodle/grade';
    
    // Create curl with ignoresecurity flag to bypass Moodle's cURL security restrictions
    $curl = new curl(['ignoresecurity' => true]);
    $curl->setHeader([
        'Content-Type: application/json',
        'X-API-KEY: ' . $apikey,
        'Accept: application/json'
    ]);
    
    // Disable SSL verification for local development
    $curl->setopt([
        'CURLOPT_SSL_VERIFYPEER' => false,
        'CURLOPT_SSL_VERIFYHOST' => 0,
    ]);
    
    // Send a minimal test payload
    $testdata = [
        'questiontext' => 'Test connection',
        'answertext' => 'Test',
        'maxgrade' => 100,
        'rubric' => '',
        'graderinfo' => ''
    ];
    
    $response = $curl->post($testurl, json_encode($testdata));
    $httpcode = $curl->get_info()['http_code'] ?? 0;
    
    if ($curl->get_errno()) {
        $result['message'] = get_string('testconnection_curlerror', 'local_aigrading', $curl->error);
    } elseif ($httpcode === 200) {
        $result['success'] = true;
        $result['message'] = get_string('testconnection_success', 'local_aigrading');
    } elseif ($httpcode === 401) {
        $result['message'] = get_string('testconnection_unauthorized', 'local_aigrading');
    } elseif ($httpcode === 503) {
        $decoded = json_decode($response, true);
        $errormsg = $decoded['error']['message'] ?? 'Service unavailable';
        $result['message'] = get_string('testconnection_serviceerror', 'local_aigrading', $errormsg);
    } else {
        $result['message'] = get_string('testconnection_httperror', 'local_aigrading', $httpcode);
    }
}

// Return JSON response
echo json_encode($result);
