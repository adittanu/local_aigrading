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
 * AI Grading helper module.
 *
 * @module
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/templates', 'core/modal_factory', 'core/modal_events'],
function($, Ajax, Notification, Templates, ModalFactory, ModalEvents) {

    var config = {};
    var strings = {};

    /**
     * Initialize the grading helper.
     *
     * @param {Object} options Configuration options
     */
    var init = function(options) {
        config = options;
        strings = options.strings || {};

        // Wait for DOM to be ready.
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupButtons);
        } else {
            setupButtons();
        }
    };

    /**
     * Get user ID from URL parameters.
     *
     * @returns {number} User ID or 0
     */
    var getUserIdFromUrl = function() {
        var urlParams = new URLSearchParams(window.location.search);
        return parseInt(urlParams.get('userid') || '0', 10);
    };

    /**
     * Setup AI grading buttons on the page.
     */
    var setupButtons = function() {
        // Check if we're on assignment grading page.
        if (config.isassignment) {
            setupAssignmentButtons();
            return;
        }
        
        // Quiz: Check if we're on overview page or individual grading page.
        if (config.isoverview) {
            setupOverviewButtons();
        } else {
            setupGradingPageButtons();
        }
    };

    /**
     * Setup buttons for the overview page (question list).
     */
    var setupOverviewButtons = function() {
        // Find the question table.
        var table = document.querySelector('table.questionstograde, table.generaltable');
        if (!table) {
            return;
        }

        // Add global "Auto Grade All Questions" button above the table.
        addGlobalAutoGradeButton(table);

        // Add auto-grade button to each row with ungraded questions.
        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
            addAutoGradeButton(row);
        });
    };

    /**
     * Add a global button to auto-grade ALL questions in the quiz.
     *
     * @param {HTMLElement} table The questions table
     */
    var addGlobalAutoGradeButton = function(table) {
        // Check if button already exists.
        if (document.querySelector('.aigrading-global-btn')) {
            return;
        }

        // Create button container.
        var container = document.createElement('div');
        container.className = 'aigrading-global-container mb-3 p-3 bg-light rounded border';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger btn-lg aigrading-global-btn';
        btn.innerHTML = '<i class="fa fa-bolt mr-2"></i>' + (strings.autogradeall || 'Auto Grade ALL Questions');

        var progressDiv = document.createElement('div');
        progressDiv.className = 'aigrading-global-progress mt-2 d-none';
        progressDiv.innerHTML = '<div class="spinner-border spinner-border-sm mr-2" role="status"></div>' +
            '<span class="aigrading-global-progress-text">Processing all questions...</span>';

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleGlobalAutoGrade(btn, progressDiv);
        });

        container.appendChild(btn);
        container.appendChild(progressDiv);

        // Insert before the table.
        table.parentNode.insertBefore(container, table);
    };

    /**
     * Handle global auto-grade request for ALL questions.
     *
     * @param {HTMLElement} btn Button element
     * @param {HTMLElement} progressDiv Progress element
     */
    var handleGlobalAutoGrade = function(btn, progressDiv) {
        // Confirm with user.
        if (!confirm('Are you sure you want to auto-grade ALL ungraded essays for ALL questions in this quiz? ' +
                     'This will apply grades automatically without review.')) {
            return;
        }

        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>' + (strings.processing || 'Processing...');
        progressDiv.classList.remove('d-none');

        Ajax.call([{
            methodname: 'local_aigrading_auto_grade_all',
            args: {
                cmid: config.cmid
            }
        }])[0].then(function(result) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            progressDiv.classList.add('d-none');

            if (result.success) {
                Notification.addNotification({
                    message: 'Auto-grading complete: ' + result.graded + ' graded, ' + result.failed + ' failed.',
                    type: result.failed > 0 ? 'warning' : 'success'
                });

                // Reload the page to show updated counts.
                if (result.graded > 0) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                Notification.addNotification({
                    message: result.message || 'Auto-grading failed.',
                    type: 'error'
                });
            }
            return result;
        }).catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            progressDiv.classList.add('d-none');
            Notification.exception(error);
        });
    };

    /**
     * Setup buttons for the individual grading page.
     */
    var setupGradingPageButtons = function() {
        // Find all essay question containers.
        var questionContainers = document.querySelectorAll('.que.essay');

        questionContainers.forEach(function(container, index) {
            addSingleGradeButton(container, index);
        });

        // Add bulk grade button if there are multiple essays.
        if (questionContainers.length > 0) {
            addBulkGradeButton(questionContainers);
        }
    };

    /**
     * Add auto-grade button to a row in the overview table.
     *
     * @param {HTMLElement} row Table row
     */
    var addAutoGradeButton = function(row) {
        // Find the "To grade" column - look for links that contain grade info.
        var gradeLink = row.querySelector('a[href*="slot="]');
        if (!gradeLink) {
            return;
        }

        // Parse the URL to get slot and questionid.
        var href = gradeLink.getAttribute('href');
        var urlParams = new URLSearchParams(href.split('?')[1]);
        var slot = urlParams.get('slot');
        var questionid = urlParams.get('qid');

        if (!slot || !questionid) {
            return;
        }

        // Check if button already exists.
        if (row.querySelector('.aigrading-auto-btn')) {
            return;
        }

        // Find the last cell to add the button.
        var lastCell = row.querySelector('td:last-child');
        if (!lastCell) {
            return;
        }

        // Create button.
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-warning btn-sm aigrading-auto-btn ml-2';
        btn.innerHTML = '<i class="fa fa-magic mr-1"></i> ' + (strings.autograde || 'Auto AI Grade');
        btn.dataset.slot = slot;
        btn.dataset.questionid = questionid;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleAutoGrade(btn, slot, questionid);
        });

        lastCell.appendChild(btn);
    };

    /**
     * Handle auto-grade request for all ungraded essays of a question.
     *
     * @param {HTMLElement} btn The button clicked
     * @param {int} slot Question slot
     * @param {int} questionid Question ID
     */
    var handleAutoGrade = function(btn, slot, questionid) {
        // Confirm with user.
        if (!confirm('Are you sure you want to auto-grade all ungraded essays for this question? ' +
                     'This will apply grades automatically without review.')) {
            return;
        }

        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> ' + (strings.processing || 'Processing...');

        Ajax.call([{
            methodname: 'local_aigrading_auto_grade_question',
            args: {
                cmid: config.cmid,
                slot: parseInt(slot),
                questionid: parseInt(questionid)
            }
        }])[0].then(function(result) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (result.success) {
                Notification.addNotification({
                    message: 'Auto-grading complete: ' + result.graded + ' graded, ' + result.failed + ' failed.',
                    type: result.failed > 0 ? 'warning' : 'success'
                });

                // Reload the page to show updated counts.
                if (result.graded > 0) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                Notification.addNotification({
                    message: result.message || 'Auto-grading failed.',
                    type: 'error'
                });
            }
            return result;
        }).catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            Notification.exception(error);
        });
    };

    /**
     * Add AI Suggest Grade button to a single question container.
     *
     * @param {HTMLElement} container The question container
     * @param {number} index Question index
     */
    var addSingleGradeButton = function(container, index) {
        // Find the comment/grade area.
        var gradeArea = container.querySelector('.comment, .gradeitemmarkcontainer, .im-controls');
        if (!gradeArea) {
            return;
        }

        // Check if button already exists.
        if (container.querySelector('.aigrading-suggest-btn')) {
            return;
        }

        // Create button.
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-secondary btn-sm aigrading-suggest-btn ml-2 mb-2';
        btn.innerHTML = '<i class="fa fa-robot mr-1"></i> ' + (strings.aisuggestgrade || 'AI Suggest Grade');
        btn.dataset.questionIndex = index;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleSingleGrade(container, btn);
        });

        // Insert button.
        var firstChild = gradeArea.firstChild;
        if (firstChild) {
            gradeArea.insertBefore(btn, firstChild);
        } else {
            gradeArea.appendChild(btn);
        }
    };

    /**
     * Add Bulk AI Grade button to the page.
     *
     * @param {NodeList} questionContainers All question containers
     */
    var addBulkGradeButton = function(questionContainers) {
        // Find a suitable location for the bulk button.
        var formHeader = document.querySelector('.gradingform, #page-mod-quiz-report form');
        if (!formHeader) {
            return;
        }

        // Check if button already exists.
        if (document.querySelector('.aigrading-bulk-btn')) {
            return;
        }

        // Create button container.
        var btnContainer = document.createElement('div');
        btnContainer.className = 'aigrading-bulk-container mb-3 p-3 bg-light rounded';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-primary aigrading-bulk-btn';
        btn.innerHTML = '<i class="fa fa-magic mr-1"></i> ' + (strings.bulkaigrade || 'Bulk AI Grade All');

        var progressDiv = document.createElement('div');
        progressDiv.className = 'aigrading-progress mt-2 d-none';
        progressDiv.innerHTML = '<div class="progress">' +
            '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>' +
            '</div>' +
            '<small class="text-muted aigrading-progress-text"></small>';

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBulkGrade(questionContainers, btn, progressDiv);
        });

        btnContainer.appendChild(btn);
        btnContainer.appendChild(progressDiv);

        // Insert after the options section, or before the grading questions.
        var optionsSection = document.querySelector('.gradingoptions');
        if (optionsSection) {
            optionsSection.parentNode.insertBefore(btnContainer, optionsSection.nextSibling);
        } else {
            // Fallback: Insert before the first question or at top of form.
            var formHeader = document.querySelector('.gradingform');
            if (formHeader) {
                formHeader.insertBefore(btnContainer, formHeader.firstChild);
            } else {
                 // Last resort: before the first question container
                 var firstQuestion = questionContainers[0];
                 if (firstQuestion) {
                     firstQuestion.parentNode.insertBefore(btnContainer, firstQuestion);
                 }
            }
        }
    };

    /**
     * Handle single grade request.
     *
     * @param {HTMLElement} container Question container
     * @param {HTMLElement} btn The button clicked
     */
    var handleSingleGrade = function(container, btn) {
        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> ' + (strings.processing || 'Processing...');

        var questionData = extractQuestionData(container);
        if (!questionData) {
            Notification.exception({message: 'Could not extract question data'});
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        callSuggestGrade(questionData).then(function(result) {
            if (result.success) {
                showSuggestionModal(container, result, questionData.maxgrade);
            } else {
                Notification.exception({message: result.error || 'Unknown error'});
            }
            btn.disabled = false;
            btn.innerHTML = originalText;
        }).catch(function(error) {
            Notification.exception(error);
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    };

    /**
     * Handle bulk grade request.
     *
     * @param {NodeList} containers All question containers
     * @param {HTMLElement} btn The button clicked
     * @param {HTMLElement} progressDiv Progress indicator
     */
    var handleBulkGrade = function(containers, btn, progressDiv) {
        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> ' + (strings.processing || 'Processing...');
        progressDiv.classList.remove('d-none');

        var progressBar = progressDiv.querySelector('.progress-bar');
        var progressText = progressDiv.querySelector('.aigrading-progress-text');

        var total = containers.length;
        var processed = 0;
        var successCount = 0;

        var processNext = function(index) {
            if (index >= containers.length) {
                // All done
                progressText.textContent = 'Completed! ' + successCount + ' of ' + total + ' graded.';
                Notification.addNotification({
                    message: strings.allgradesapplied || 'All grades have been applied. Please review before saving.',
                    type: 'success'
                });
                btn.disabled = false;
                btn.innerHTML = originalText;
                setTimeout(function() {
                    progressDiv.classList.add('d-none');
                }, 3000);
                return;
            }

            var container = containers[index];
            var progress = ((index + 1) / total) * 100;

            progressBar.style.width = progress + '%';
            progressText.textContent = 'Processing ' + (index + 1) + ' of ' + total + '...';

            var questionData = extractQuestionData(container);
            if (questionData) {
                callSuggestGrade(questionData).then(function(result) {
                    if (result.success) {
                        applyGradeToForm(container, result);
                        successCount++;
                    }
                    processNext(index + 1);
                }).catch(function() {
                    processNext(index + 1);
                });
            } else {
                processNext(index + 1);
            }
        };

        processNext(0);
    };

    /**
     * Extract question data from a container.
     *
     * @param {HTMLElement} container Question container
     * @returns {Object|null} Question data or null
     */
    var extractQuestionData = function(container) {
        // Extract question text.
        var questionTextEl = container.querySelector('.qtext');
        var questionText = questionTextEl ? questionTextEl.textContent.trim() : '';

        // Extract student answer.
        var answerEl = container.querySelector('.answer .qtype_essay_response, .answer .text_to_html, .answer');
        var answerText = answerEl ? answerEl.textContent.trim() : '';

        // Extract max grade.
        var gradeInput = container.querySelector('input[name*="-mark"]');
        var maxgrade = 10; // Default.

        if (gradeInput) {
            // Try to find max from nearby text or input attributes.
            var maxAttr = gradeInput.getAttribute('max');
            if (maxAttr) {
                maxgrade = parseFloat(maxAttr);
            } else {
                // Look for "out of X" text.
                var gradeText = container.querySelector('.grade, .maxgrade');
                if (gradeText) {
                    var match = gradeText.textContent.match(/(\d+(?:\.\d+)?)/);
                    if (match) {
                        maxgrade = parseFloat(match[1]);
                    }
                }
            }
        }

        if (!questionText || !answerText) {
            return null;
        }

        return {
            questionText: questionText,
            answerText: answerText,
            maxgrade: maxgrade,
            gradeInput: gradeInput,
            graderinfo: extractGraderInfo(container)
        };
    };

    /**
     * Extract grader information from the page if available.
     *
     * @param {HTMLElement} container Question container
     * @returns {string} Grader info or empty string
     */
    var extractGraderInfo = function(container) {
        // Try to find grader information in various locations.
        // First look for explicit grader info section.
        var graderInfoEl = container.querySelector('.graderinfo, .generalfeedback, .rightanswer');
        if (graderInfoEl) {
            return graderInfoEl.textContent.trim();
        }

        // Look for "Response information" or similar sections.
        var responseInfo = document.querySelector('.responseinfo, .response-information');
        if (responseInfo) {
            return responseInfo.textContent.trim();
        }

        return '';
    };

    /**
     * Call the suggest_grade AJAX endpoint.
     *
     * @param {Object} questionData Question data
     * @returns {Promise<Object>} Result
     */
    var callSuggestGrade = function(questionData) {
        return Ajax.call([{
            methodname: 'local_aigrading_suggest_grade',
            args: {
                cmid: config.cmid,
                questiontext: questionData.questionText,
                answertext: questionData.answerText,
                maxgrade: questionData.maxgrade,
                rubric: '',
                graderinfo: questionData.graderinfo || ''
            }
        }])[0];
    };

    /**
     * Show the suggestion modal.
     *
     * @param {HTMLElement} container Question container
     * @param {Object} result AI result
     * @param {number} maxgrade Maximum grade
     */
    var showSuggestionModal = function(container, result, maxgrade) {
        var confidence = result.confidence || 'medium';
        var templateContext = {
            grade: result.grade,
            maxgrade: maxgrade,
            feedback: result.feedback,
            explanation: result.explanation,
            confidence: confidence,
            confidenceHigh: confidence === 'high',
            confidenceMedium: confidence === 'medium',
            confidenceLow: confidence === 'low',
            strings: strings
        };

        Templates.render('local_aigrading/suggestion_modal', templateContext).then(function(html) {
            return ModalFactory.create({
                title: strings.suggestedgrade || 'Suggested Grade',
                body: html,
                type: ModalFactory.types.SAVE_CANCEL,
                large: true
            });
        }).then(function(modal) {
            modal.setSaveButtonText(strings.applygrade || 'Apply Grade');

            modal.getRoot().on(ModalEvents.save, function() {
                applyGradeToForm(container, result);
                Notification.addNotification({
                    message: strings.gradeapplied || 'Grade has been applied.',
                    type: 'success'
                });
            });

            modal.show();
            return modal;
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    /**
     * Apply grade to the form fields.
     *
     * @param {HTMLElement} container Question container
     * @param {Object} result AI result
     */
    var applyGradeToForm = function(container, result) {
        // Find the question's parent form area (may be the whole form for single questions)
        var formArea = container.closest('form') || document;

        // Debug log
        console.log('Applying grade:', result.grade, 'Feedback:', result.feedback);

        // Try multiple selectors for the grade/mark input
        var gradeInput = container.querySelector('input[name*="-mark"]') ||
                         container.querySelector('input[name*="mark"]') ||
                         formArea.querySelector('input[id*="-mark"]') ||
                         formArea.querySelector('.que input[type="text"]');

        if (gradeInput) {
            console.log('Found grade input:', gradeInput.name || gradeInput.id);
            gradeInput.value = result.grade;
            gradeInput.dispatchEvent(new Event('change', {bubbles: true}));
            gradeInput.dispatchEvent(new Event('input', {bubbles: true}));
        } else {
            console.log('Grade input not found, trying alternative selectors...');
            // Try finding by label text
            var markLabels = formArea.querySelectorAll('label');
            markLabels.forEach(function(label) {
                if (label.textContent.toLowerCase().includes('mark')) {
                    var inputId = label.getAttribute('for');
                    if (inputId) {
                        var input = document.getElementById(inputId);
                        if (input) {
                            console.log('Found grade input via label:', input.id);
                            input.value = result.grade;
                            input.dispatchEvent(new Event('change', {bubbles: true}));
                        }
                    }
                }
            });
        }

        // Handle TinyMCE editor (Moodle 5 default)
        var commentTextarea = container.querySelector('textarea[name*="-comment"]') ||
                              formArea.querySelector('textarea[name*="-comment"]');

        if (commentTextarea) {
            var textareaId = commentTextarea.id;
            console.log('Found comment textarea:', textareaId);

            // Try to use TinyMCE API if available
            if (typeof window.tinymce !== 'undefined' && textareaId) {
                var editor = window.tinymce.get(textareaId);
                if (editor) {
                    console.log('Setting content via TinyMCE API');
                    editor.setContent('<p>' + result.feedback + '</p>');
                    // Also update the hidden textarea
                    commentTextarea.value = result.feedback;
                } else {
                    console.log('TinyMCE editor not found for id:', textareaId);
                    // Just set the textarea value directly
                    commentTextarea.value = result.feedback;
                }
            } else {
                // No TinyMCE, try Atto or plain textarea
                var attoContainer = container.querySelector('.editor_atto_wrap') ||
                                    formArea.querySelector('.editor_atto_wrap');

                if (attoContainer) {
                    var editorContent = attoContainer.querySelector('.editor_atto_content');
                    if (editorContent) {
                        console.log('Found Atto editor content area');
                        editorContent.innerHTML = '<p>' + result.feedback + '</p>';
                        editorContent.dispatchEvent(new Event('input', {bubbles: true}));
                    }
                }

                // Always set textarea value as fallback
                commentTextarea.value = result.feedback;
                commentTextarea.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }

        // Visual feedback that apply was successful
        container.style.backgroundColor = '#d4edda';
        container.style.transition = 'background-color 0.3s';
        setTimeout(function() {
            container.style.backgroundColor = '';
        }, 1500);
    };

    // ==================== ASSIGNMENT GRADING FUNCTIONS ====================

    /**
     * Setup buttons for assignment grading page.
     */
    var setupAssignmentButtons = function() {
        // Check if this is the submissions list page.
        if (config.issubmissionspage) {
            setupAssignmentSubmissionsButtons();
            return;
        }

        // Individual grading page.
        // Find the grade section in assignment grading.
        var gradeSection = document.querySelector('.submissionstatustable, .assignfeedback_editpdf_widget, [data-region="grading-panel"]');
        if (!gradeSection) {
            // Try alternative selector for grader page.
            gradeSection = document.querySelector('.gradingsummary, .submissionsummarytable');
        }

        // Check if button already exists.
        if (document.querySelector('.aigrading-assign-btn')) {
            return;
        }

        // Find a good place to insert the button.
        var insertTarget = document.querySelector('.gradingform, [data-region="grade"], .submission-full');
        if (!insertTarget) {
            insertTarget = document.querySelector('form.mform');
        }
        if (!insertTarget) {
            return;
        }

        // Create AI grade button.
        var btnContainer = document.createElement('div');
        btnContainer.className = 'aigrading-assign-container mb-3 p-3 bg-light rounded border';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-primary aigrading-assign-btn';
        btn.innerHTML = '<i class="fa fa-magic mr-2"></i>' + (strings.aisuggestgrade || 'AI Suggest Grade');

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleAssignmentGrade(btn);
        });

        btnContainer.appendChild(btn);

        // Insert before the target.
        insertTarget.parentNode.insertBefore(btnContainer, insertTarget);
    };

    /**
     * Handle assignment grade suggestion.
     *
     * @param {HTMLElement} btn The button clicked
     */
    var handleAssignmentGrade = function(btn) {
        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>' + (strings.processing || 'Processing...');

        var assignmentData = extractAssignmentData();

        // Determine which API to use.
        var hasOnlineText = assignmentData && assignmentData.submissionText;
        var apiMethod = hasOnlineText ? 'local_aigrading_suggest_grade' : 'local_aigrading_suggest_grade_file';
        var apiArgs = hasOnlineText ? {
            cmid: config.cmid,
            questiontext: assignmentData.assignmentDescription,
            answertext: assignmentData.submissionText,
            maxgrade: assignmentData.maxgrade,
            rubric: '',
            graderinfo: ''
        } : {
            cmid: config.cmid,
            userid: config.userid || getUserIdFromUrl(),
            assignmentdesc: assignmentData ? assignmentData.assignmentDescription : '',
            maxgrade: assignmentData ? assignmentData.maxgrade : 100
        };

        Ajax.call([{
            methodname: apiMethod,
            args: apiArgs
        }])[0].then(function(result) {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (result.success) {
                showAssignmentSuggestionModal(result, assignmentData ? assignmentData.maxgrade : 100);
            } else {
                Notification.addNotification({
                    message: result.error || 'Failed to get AI suggestion.',
                    type: 'error'
                });
            }
            return result;
        }).catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            Notification.exception(error);
        });
    };

    /**
     * Extract assignment data from the page.
     *
     * @returns {Object|null} Assignment data
     */
    var extractAssignmentData = function() {
        // Get assignment description/instructions.
        var descEl = document.querySelector('.activityinstance .instancename, .assignmentname, [data-region="assign-intro"]');
        var assignmentDescription = '';
        if (descEl) {
            assignmentDescription = descEl.textContent.trim();
        }

        // Try to get from intro section.
        var introEl = document.querySelector('.activityinstance, .intro, .submissionsummarytable td');
        if (introEl && !assignmentDescription) {
            assignmentDescription = introEl.textContent.trim().substring(0, 500);
        }

        // Get submission text (online text) - try multiple selectors.
        var submissionText = '';

        // Selector 1: Look for online text submission in grader panel.
        var selectors = [
            '[data-region="submission"] .submissionfull',
            '[data-region="submission"] .no-overflow',
            '.assignsubmission_onlinetext .no-overflow',
            '.assignsubmission_onlinetext .text_to_html',
            '.submission-full .no-overflow',
            '.submissionfull',
            '.onlinetext',
            '.assignsubmission_onlinetext_editor',
            '[data-region="submission"]',
            // For grader drawer - the submission content.
            '.drawer-content .submission-full',
            '.grading-panel .submissionfull',
            // Generic content areas.
            '.submission-status + div',
            '.box.py-3.submission-full'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var el = document.querySelector(selectors[i]);
            if (el) {
                var text = el.textContent.trim();
                if (text && text.length > 5) {
                    submissionText = text;
                    break;
                }
            }
        }

        // If still no text, try to find any text after "Submission" heading.
        if (!submissionText) {
            var submissionContainer = document.querySelector('[data-region="submission"]');
            if (submissionContainer) {
                submissionText = submissionContainer.innerText.trim();
            }
        }

        // Get max grade.
        var maxgrade = 100;
        var gradeInput = document.querySelector('input[name="grade"], input[name="quickgrade_-1"], [id*="id_grade"]');
        if (gradeInput) {
            var maxAttr = gradeInput.getAttribute('max');
            if (maxAttr) {
                maxgrade = parseFloat(maxAttr);
            }
        }

        // Try to find max from label.
        var gradeLabelEl = document.querySelector('.grader-out-of, .grade-max, [class*="outof"]');
        if (gradeLabelEl) {
            var match = gradeLabelEl.textContent.match(/(\d+(?:\.\d+)?)/);
            if (match) {
                maxgrade = parseFloat(match[1]);
            }
        }

        if (!submissionText) {
            return null;
        }

        return {
            assignmentDescription: assignmentDescription,
            submissionText: submissionText,
            maxgrade: maxgrade
        };
    };

    /**
     * Show suggestion modal for assignment.
     *
     * @param {Object} result AI result
     * @param {number} maxgrade Maximum grade
     */
    var showAssignmentSuggestionModal = function(result, maxgrade) {
        var confidence = result.confidence || 'medium';
        var templateContext = {
            grade: result.grade,
            maxgrade: maxgrade,
            feedback: result.feedback,
            explanation: result.explanation,
            confidence: confidence,
            confidenceHigh: confidence === 'high',
            confidenceMedium: confidence === 'medium',
            confidenceLow: confidence === 'low',
            strings: strings
        };

        Templates.render('local_aigrading/suggestion_modal', templateContext).then(function(html) {
            return ModalFactory.create({
                title: strings.suggestedgrade || 'Suggested Grade',
                body: html,
                type: ModalFactory.types.SAVE_CANCEL,
                large: true
            });
        }).then(function(modal) {
            modal.setSaveButtonText(strings.applygrade || 'Apply Grade');

            modal.getRoot().on(ModalEvents.save, function() {
                applyGradeToAssignment(result);
                Notification.addNotification({
                    message: strings.gradeapplied || 'Grade has been applied.',
                    type: 'success'
                });
            });

            modal.show();
            return modal;
        }).catch(function(error) {
            Notification.exception(error);
        });
    };

    /**
     * Apply grade to assignment form.
     *
     * @param {Object} result AI result with grade and feedback
     */
    var applyGradeToAssignment = function(result) {
        // Find and set grade input - use specific ID first.
        var gradeInput = document.getElementById('id_grade');
        if (!gradeInput) {
            gradeInput = document.querySelector('input[name="grade"]');
        }
        if (gradeInput) {
            gradeInput.value = result.grade;
            gradeInput.dispatchEvent(new Event('input', {bubbles: true}));
            gradeInput.dispatchEvent(new Event('change', {bubbles: true}));
        }

        // Find and set feedback - use specific assignment feedback textarea.
        var feedbackTextarea = document.getElementById('id_assignfeedbackcomments_editor');
        if (!feedbackTextarea) {
            feedbackTextarea = document.querySelector('textarea[name*="assignfeedbackcomments"]');
        }
        if (!feedbackTextarea) {
            feedbackTextarea = document.querySelector('textarea[name*="feedback_comments"]');
        }
        
        if (feedbackTextarea) {
            feedbackTextarea.value = result.feedback;
            feedbackTextarea.dispatchEvent(new Event('input', {bubbles: true}));
            feedbackTextarea.dispatchEvent(new Event('change', {bubbles: true}));

            // Handle TinyMCE if present.
            var textareaId = feedbackTextarea.id;
            if (textareaId && window.tinymce && window.tinymce.get(textareaId)) {
                window.tinymce.get(textareaId).setContent(result.feedback);
            }
        }
    };

    /**
     * Setup buttons for assignment submissions list page.
     */
    var setupAssignmentSubmissionsButtons = function() {
        // Check if button already exists.
        if (document.querySelector('.aigrading-bulk-assign-btn')) {
            return;
        }

        // Find the submissions table or grading actions area.
        var actionsArea = document.querySelector('.submissionlinks, [data-region="grading-actions"]');
        if (!actionsArea) {
            actionsArea = document.querySelector('.gradingoptionsform, .gradingbatchoperationsform');
        }
        if (!actionsArea) {
            actionsArea = document.querySelector('.submissionstable, table.flexible');
        }
        if (!actionsArea) {
            return;
        }

        // Create bulk grade button.
        var btnContainer = document.createElement('div');
        btnContainer.className = 'aigrading-bulk-assign-container mb-3 p-3 bg-light rounded border';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-danger btn-lg aigrading-bulk-assign-btn';
        btn.innerHTML = '<i class="fa fa-bolt mr-2"></i>' +
            (strings.autogradeall || 'Auto Grade ALL Submissions');

        var progressDiv = document.createElement('div');
        progressDiv.className = 'aigrading-bulk-progress mt-2 d-none';
        progressDiv.innerHTML = '<div class="spinner-border spinner-border-sm mr-2"></div>' +
            '<span class="progress-text">Processing...</span>';

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBulkAssignmentGrade(btn, progressDiv);
        });

        btnContainer.appendChild(btn);
        btnContainer.appendChild(progressDiv);

        // Insert before the table/area.
        actionsArea.parentNode.insertBefore(btnContainer, actionsArea);
    };

    /**
     * Handle bulk assignment grade request.
     *
     * @param {HTMLElement} btn Button element
     * @param {HTMLElement} progressDiv Progress element
     */
    var handleBulkAssignmentGrade = function(btn, progressDiv) {
        // Confirm with user.
        if (!confirm('Auto grade ALL ungraded online text submissions? ' +
                     'This will apply grades automatically.')) {
            return;
        }

        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>' +
            (strings.processing || 'Processing...');
        progressDiv.classList.remove('d-none');

        Ajax.call([{
            methodname: 'local_aigrading_auto_grade_assignment',
            args: {
                cmid: config.cmid
            }
        }])[0].then(function(result) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            progressDiv.classList.add('d-none');

            if (result.success) {
                Notification.addNotification({
                    message: 'Auto-grading complete: ' + result.graded +
                        ' graded, ' + result.failed + ' failed.',
                    type: result.failed > 0 ? 'warning' : 'success'
                });

                // Reload to show updated grades.
                if (result.graded > 0) {
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                Notification.addNotification({
                    message: result.message || 'Auto-grading failed.',
                    type: 'error'
                });
            }
            return result;
        }).catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            progressDiv.classList.add('d-none');
            Notification.exception(error);
        });
    };

    return {
        init: init
    };
});
