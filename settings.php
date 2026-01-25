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
 * AI Grading plugin settings.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aigrading', get_string('pluginname', 'local_aigrading'));
    $ADMIN->add('localplugins', $settings);

    // API Settings heading.
    $settings->add(new admin_setting_heading(
        'local_aigrading/apiheading',
        get_string('apisettings', 'local_aigrading'),
        get_string('apisettings_desc', 'local_aigrading')
    ));

    // OpenAI API Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aigrading/apikey',
        get_string('apikey', 'local_aigrading'),
        get_string('apikey_desc', 'local_aigrading'),
        ''
    ));

    // OpenAI Base URL.
    $settings->add(new admin_setting_configtext(
        'local_aigrading/apibaseurl',
        get_string('apibaseurl', 'local_aigrading'),
        get_string('apibaseurl_desc', 'local_aigrading'),
        'https://api.openai.com/v1',
        PARAM_URL
    ));

    // Model selection.
    $settings->add(new admin_setting_configtext(
        'local_aigrading/model',
        get_string('model', 'local_aigrading'),
        get_string('model_desc', 'local_aigrading'),
        'gpt-4o-mini',
        PARAM_TEXT
    ));

    // Usage Guide heading.
    $settings->add(new admin_setting_heading(
        'local_aigrading/guideheading',
        get_string('usageguide', 'local_aigrading'),
        get_string('usageguide_desc', 'local_aigrading')
    ));

    // Grading Settings heading.
    $settings->add(new admin_setting_heading(
        'local_aigrading/gradingheading',
        get_string('gradingsettings', 'local_aigrading'),
        get_string('gradingsettings_desc', 'local_aigrading')
    ));

    // Default Rubric.
    $defaultrubric = "Kriteria penilaian:
- 90-100: Jawaban lengkap, contoh relevan, penjelasan jelas dan terstruktur
- 70-89: Jawaban cukup lengkap, ada beberapa kekurangan minor
- 50-69: Jawaban kurang lengkap, perlu perbaikan signifikan
- <50: Jawaban tidak memenuhi kriteria minimum atau tidak relevan";

    $settings->add(new admin_setting_configtextarea(
        'local_aigrading/defaultrubric',
        get_string('defaultrubric', 'local_aigrading'),
        get_string('defaultrubric_desc', 'local_aigrading'),
        $defaultrubric
    ));

    // System Prompt.
    $defaultprompt = "Anda adalah asisten penilaian essay untuk guru. Tugas Anda adalah menilai jawaban siswa berdasarkan pertanyaan dan rubrik yang diberikan.

SANGAT PENTING - VALIDASI RELEVANSI:
Pertama, periksa apakah jawaban siswa RELEVAN dengan pertanyaan yang diberikan.
- Jika jawaban TIDAK SESUAI TOPIK atau membahas hal yang berbeda dari pertanyaan, berikan nilai MAKSIMAL 20% dari nilai maksimum.
- Jika jawaban SEBAGIAN relevan tapi tidak fokus pada topik, berikan nilai maksimal 50%.
- Hanya berikan nilai tinggi jika jawaban benar-benar membahas topik yang diminta.

Berikan output dalam format JSON berikut:
{
    \"grade\": <nilai numerik>,
    \"feedback\": \"<feedback konstruktif untuk siswa>\",
    \"explanation\": \"<penjelasan untuk guru mengapa nilai ini diberikan>\"
}

Pastikan:
1. SELALU cek relevansi jawaban dengan pertanyaan TERLEBIH DAHULU
2. Nilai sesuai dengan skala yang diberikan (0 hingga nilai maksimum)
3. Jika off-topic, jelaskan bahwa jawaban tidak sesuai pertanyaan
4. Feedback membangun dan spesifik
5. Explanation menjelaskan kekuatan dan kelemahan jawaban";

    $settings->add(new admin_setting_configtextarea(
        'local_aigrading/systemprompt',
        get_string('systemprompt', 'local_aigrading'),
        get_string('systemprompt_desc', 'local_aigrading'),
        $defaultprompt
    ));

    // Advanced Settings heading.
    $settings->add(new admin_setting_heading(
        'local_aigrading/advancedheading',
        get_string('advancedsettings', 'local_aigrading'),
        ''
    ));

    // Max tokens.
    $settings->add(new admin_setting_configtext(
        'local_aigrading/maxtokens',
        get_string('maxtokens', 'local_aigrading'),
        get_string('maxtokens_desc', 'local_aigrading'),
        '1000',
        PARAM_INT
    ));

    // Temperature.
    $settings->add(new admin_setting_configtext(
        'local_aigrading/temperature',
        get_string('temperature', 'local_aigrading'),
        get_string('temperature_desc', 'local_aigrading'),
        '0.3',
        PARAM_FLOAT
    ));

    // Max text length for file extraction.
    $settings->add(new admin_setting_configtext(
        'local_aigrading/maxtextlength',
        get_string('maxtextlength', 'local_aigrading'),
        get_string('maxtextlength_desc', 'local_aigrading'),
        '50000',
        PARAM_INT
    ));
}
