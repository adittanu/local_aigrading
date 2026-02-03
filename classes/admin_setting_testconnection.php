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
 * Admin setting for test connection button.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrading;

defined('MOODLE_INTERNAL') || die();

/**
 * Test connection button admin setting.
 */
class admin_setting_testconnection extends \admin_setting {

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, '');
    }

    /**
     * Get setting value.
     *
     * @return mixed
     */
    public function get_setting() {
        return '';
    }

    /**
     * Write setting.
     *
     * @param mixed $data
     * @return string
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Output the HTML for the setting.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $testurl = new \moodle_url('/local/aigrading/testconnection.php', ['sesskey' => sesskey()]);
        $buttonid = 'testconnection-btn';
        $resultid = 'testconnection-result';

        $html = \html_writer::start_div('testconnection-wrapper');
        $html .= \html_writer::tag('button', 
            get_string('testconnection', 'local_aigrading'),
            [
                'id' => $buttonid,
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'data-testurl' => $testurl->out(false)
            ]
        );
        $html .= \html_writer::div('', 'mt-2', ['id' => $resultid]);
        $html .= \html_writer::end_div();

        // Add JavaScript for AJAX handling - using vanilla JavaScript with POST
        $html .= '
<script type="text/javascript">
//<![CDATA[
document.addEventListener("DOMContentLoaded", function() {
    var btn = document.getElementById("' . $buttonid . '");
    var result = document.getElementById("' . $resultid . '");
    
    if (btn) {
        btn.addEventListener("click", function() {
            var testUrl = btn.getAttribute("data-testurl");
            var originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = "Testing...";
            result.className = "mt-2";
            result.textContent = "";
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", testUrl, true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.setRequestHeader("Accept", "application/json");
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                    
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                result.className = "mt-2 alert alert-success";
                                result.textContent = response.message;
                            } else {
                                result.className = "mt-2 alert alert-danger";
                                result.textContent = response.message;
                            }
                        } catch (e) {
                            result.className = "mt-2 alert alert-danger";
                            result.textContent = "Connection failed: Invalid response from server.";
                        }
                    } else {
                        result.className = "mt-2 alert alert-danger";
                        result.textContent = "Connection failed: HTTP " + xhr.status;
                    }
                }
            };
            
            xhr.onerror = function() {
                btn.disabled = false;
                btn.textContent = originalText;
                result.className = "mt-2 alert alert-danger";
                result.textContent = "Connection failed: Unable to reach the server.";
            };
            
            xhr.send("test=1&sesskey=" + M.cfg.sesskey);
        });
    }
});
//]]>
</script>';

        return format_admin_setting($this, $html);
    }
}
