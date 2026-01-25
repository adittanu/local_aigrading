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
 * Language strings for AI Grading plugin.
 *
 * @package    local_aigrading
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'AI Grading';

// Settings.
$string['apisettings'] = 'API Settings';
$string['apisettings_desc'] = 'Configure OpenAI API connection settings.';
$string['apikey'] = 'OpenAI API Key';
$string['apikey_desc'] = 'Enter your OpenAI API key. Get one from https://platform.openai.com/api-keys';
$string['apibaseurl'] = 'API Base URL';
$string['apibaseurl_desc'] = 'Base URL for OpenAI API. Change this if using a compatible API provider.';
$string['model'] = 'Model';
$string['model_desc'] = 'OpenAI model to use for grading. Examples: gpt-4o-mini, gpt-4o, gpt-3.5-turbo';

$string['gradingsettings'] = 'Grading Settings';
$string['gradingsettings_desc'] = 'Configure how AI grades essay answers.';
$string['defaultrubric'] = 'Default Rubric';
$string['defaultrubric_desc'] = 'Default grading rubric/criteria. This will be used if no specific rubric is provided in the question.';
$string['systemprompt'] = 'System Prompt';
$string['systemprompt_desc'] = 'System prompt template for AI grading. Use this to customize how AI evaluates and responds.';

// Usage Guide.
$string['usageguide'] = '📖 Usage Guide - How to Get Best Results';
$string['usageguide_desc'] = '<div class="alert alert-info">
<h5><i class="fa fa-lightbulb-o"></i> Tips for Better AI Grading</h5>
<p>For best results, fill in the <strong>"Information for graders"</strong> field when creating essay questions. This helps AI understand what a good answer should contain.</p>

<h6>Where to find it:</h6>
<p>Question Bank → Edit Essay Question → Scroll to <strong>"Grader information"</strong> section → Fill in <strong>"Information for graders"</strong></p>

<h6>Example 1 - Factual Question:</h6>
<pre style="background:#f5f5f5;padding:10px;border-radius:5px;">
Correct Answer:
OpenAI is an AI research company founded in 2015 by Sam Altman, 
Elon Musk, and others. OpenAI created ChatGPT, GPT-4, and DALL-E.

Grading Points:
- Mentions OpenAI is an AI company (2 points)
- Mentions founding year 2015 or founders (1 point)
- Mentions products like ChatGPT/GPT (2 points)
</pre>

<h6>Example 2 - Argumentative Essay:</h6>
<pre style="background:#f5f5f5;padding:10px;border-radius:5px;">
Grading Criteria:
1. Clear introduction with thesis statement (2 points)
2. At least 3 supporting arguments with examples (3 points)
3. Use of relevant references/sources (2 points)
4. Conclusion that summarizes arguments (2 points)
5. Good grammar and paragraph structure (1 point)

The answer should discuss the impact of technology on education 
from accessibility, efficiency, and implementation challenges.
</pre>

<h6>Example 3 - Definition Question:</h6>
<pre style="background:#f5f5f5;padding:10px;border-radius:5px;">
Correct Answer:
Photosynthesis is the process by which plants convert sunlight, 
water, and CO2 into glucose and oxygen.

Must mention: sunlight, water, CO2, glucose, oxygen
Equation: 6CO2 + 6H2O + light → C6H12O6 + 6O2
</pre>

<p><strong>Note:</strong> If "Information for graders" is empty, AI will grade based on general criteria like structure, coherence, and language use. The confidence level will be lower.</p>
</div>';

$string['advancedsettings'] = 'Advanced Settings';
$string['maxtokens'] = 'Max Tokens';
$string['maxtokens_desc'] = 'Maximum number of tokens in AI response.';
$string['temperature'] = 'Temperature';
$string['temperature_desc'] = 'AI temperature (0.0-1.0). Lower values make output more focused and deterministic.';
$string['maxtextlength'] = 'Max Text Length';
$string['maxtextlength_desc'] = 'Maximum characters to extract from files (PDF, DOCX, TXT). Longer texts will be truncated.';

// Capabilities.
$string['aigrading:useaigrading'] = 'Use AI grading suggestions';

// UI strings.
$string['aisuggestgrade'] = 'AI Suggest Grade';
$string['bulkaigrade'] = 'Bulk AI Grade All';
$string['autograde'] = 'Auto AI Grade';
$string['autogradeall'] = 'Auto Grade ALL Questions';
$string['processing'] = 'Processing...';
$string['processingprogress'] = 'Processing {$a->current} of {$a->total}...';
$string['suggestedgrade'] = 'Suggested Grade';
$string['feedback'] = 'Feedback for Student';
$string['explanation'] = 'Explanation for Teacher';
$string['applygrade'] = 'Apply Grade';
$string['applyall'] = 'Apply All Grades';
$string['cancel'] = 'Cancel';
$string['error'] = 'Error';
$string['success'] = 'Success';
$string['gradeapplied'] = 'Grade has been applied successfully.';
$string['allgradesapplied'] = 'All grades have been applied successfully.';
$string['autogradecomplete'] = 'Auto-grading complete: {$a->graded} graded, {$a->failed} failed.';
$string['autogradeconfirm'] = 'Are you sure you want to auto-grade all ungraded essays for this question? This will apply grades automatically without review.';

// Error messages.
$string['error:noapikey'] = 'OpenAI API key is not configured. Please configure it in plugin settings.';
$string['error:apierror'] = 'OpenAI API error: {$a}';
$string['error:invalidresponse'] = 'Invalid response from AI. Please try again.';
$string['error:nopermission'] = 'You do not have permission to use AI grading.';
