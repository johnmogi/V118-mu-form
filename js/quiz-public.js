/**
 * ACF Quiz System - Public JavaScript
 * RTL and Hebrew Support Enhanced
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Set RTL direction for the quiz container
    $('.acf-quiz-container').attr('dir', 'rtl');
    
    const QuizSystem = {
        form: null,
        submitButton: null,
        resultsContainer: null,
        isSubmitting: false,
        
        init: function() {
            this.form = $('#acf-quiz-form');
            this.submitButton = $('.quiz-submit-btn');
            this.resultsContainer = $('#quiz-results');
            
            if (this.form.length) {
                this.bindEvents();
                this.validateForm();
            }
        },
        
        bindEvents: function() {
            // Form submission - use click on the button instead of form submit
            this.submitButton.on('click', this.handleSubmit.bind(this));
            
            // Answer selection - handle both click and change events
            $('.answer-input').on('click', (e) => {
                // Allow the radio button to be selected
                e.stopPropagation();
                // Trigger change event after a short delay to ensure selection is processed
                setTimeout(() => {
                    $(e.target).trigger('change');
                }, 10);
            });
            
            // Handle change event for validation and visual feedback
            $('.answer-input').on('change', (e) => {
                this.handleAnswerChange(e);
                this.validateForm();
            });
            
            // Handle label clicks to ensure proper selection
            $('.answer-label').on('click', (e) => {
                const input = $(e.currentTarget).siblings('.answer-input');
                if (input.length) {
                    input.prop('checked', true).trigger('change');
                }
            });
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            if (this.isSubmitting) {
                return false;
            }
            
            if (!this.validateAllAnswered()) {
                this.showError(acfQuiz.strings.pleaseAnswerAll);
                return false;
            }
            
            this.submitQuiz();
        },
        
        handleAnswerChange: function(e) {
            const $input = $(e.target);
            const questionBlock = $input.closest('.question-block');
            const questionName = $input.attr('name');
            
            // Remove 'answered' class from all questions with the same name
            $(`input[name="${questionName}"]`).closest('.question-block').removeClass('answered');
            
            // Add 'answered' class to the current question block
            questionBlock.addClass('answered');
            
            // Visual feedback for the selected answer
            $(`input[name="${questionName}"]`).closest('.answer-option').removeClass('selected');
            $input.closest('.answer-option').addClass('selected');
            
            // Update progress
            this.updateProgress();
        },
        
        validateAllAnswered: function() {
            const totalQuestions = $('.question-block').length;
            const answeredQuestions = $('.question-block').filter(function() {
                return $(this).find('.answer-input:checked').length > 0;
            }).length;
            
            return totalQuestions === answeredQuestions;
        },
        
        validatePersonalDetails: function(showErrors = false) {
            let isValid = true;
            let errorMessage = '';
            
            // Get field values
            const $nameField = $('#user_name');
            const $phoneField = $('#user_phone');
            const $consentField = $('#contact_consent');
            const $consentGroup = $consentField.closest('.checkbox-group');
            
            const userName = $nameField.val().trim();
            const userPhone = $phoneField.val().trim();
            const contactConsent = $consentField.is(':checked');
            
            // Only validate and show errors if showErrors is true
            if (showErrors) {
                if (!userName) {
                    isValid = false;
                    $nameField.addClass('error');
                    errorMessage = 'אנא מלא את השם המלא';
                } else {
                    $nameField.removeClass('error');
                }
                
                if (!userPhone) {
                    isValid = false;
                    $phoneField.addClass('error');
                    if (!errorMessage) errorMessage = 'אנא מלא את מספר הטלפון';
                } else {
                    $phoneField.removeClass('error');
                }
                
                if (!contactConsent) {
                    isValid = false;
                    $consentGroup.addClass('error');
                    if (!errorMessage) errorMessage = 'אנא אשר את ההסכמה ליצירת קשר';
                } else {
                    $consentGroup.removeClass('error');
                }
            } else {
                // Just validate without showing errors
                if (!userName || !userPhone || !contactConsent) {
                    isValid = false;
                }
            }
            
            return { isValid: isValid, errorMessage: errorMessage };
        },

        validateForm: function(showErrors = false) {
            const personalValidation = this.validatePersonalDetails(showErrors);
            const allAnswered = this.validateAllAnswered();
            const isFormValid = personalValidation.isValid && allAnswered;
            
            this.submitButton.prop('disabled', !isFormValid);
            
            if (isFormValid) {
                this.submitButton.removeClass('disabled');
                this.hideError();
            } else if (showErrors) {
                this.submitButton.addClass('disabled');
                if (!personalValidation.isValid) {
                    this.showError(personalValidation.errorMessage);
                } else if (!allAnswered) {
                    this.showError('אנא ענה על כל השאלות');
                }
            }
            
            return isFormValid;
        },
        
        updateProgress: function() {
            const totalQuestions = $('.question-block').length;
            const answeredQuestions = $('.question-block.answered').length;
            const progress = Math.round((answeredQuestions / totalQuestions) * 100);
            
            // You can add a progress bar here if needed
            console.log('Progress: ' + progress + '%');
        },
        
        submitQuiz: function() {
            this.isSubmitting = true;
            this.setLoadingState(true);
            
            // Collect all answers with their point values
            const formData = new FormData();
            formData.append('action', 'submit_quiz');
            formData.append('quiz_nonce', $('#quiz_nonce').val());
            
            // Add each answer to form data
            $('.answer-input:checked').each(function() {
                const name = $(this).attr('name'); // question_0, question_1, etc.
                const value = $(this).val(); // points value (1-4)
                formData.append(name, value);
            });
            
            // Submit via AJAX
            $.ajax({
                url: acfQuiz.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: this.handleSuccess.bind(this),
                error: this.handleError.bind(this),
                complete: this.handleComplete.bind(this)
            });
        },
        
        handleSuccess: function(response) {
            if (response.success && response.data) {
                this.displayResults(response.data);
                this.scrollToResults();
            } else {
                this.showError(response.data.message || acfQuiz.strings.error);
            }
        },
        
        handleError: function(xhr, status, error) {
            console.error('Quiz submission error:', error);
            this.showError(acfQuiz.strings.error);
        },
        
        handleComplete: function() {
            this.isSubmitting = false;
            this.setLoadingState(false);
        },
        
        displayResults: function(data) {
            // Update score display with RTL support
            const scoreText = data.score + '/40';
            const maxScore = data.max_score;
            const percentage = data.score_percentage;
            
            $('#quiz-score').text(scoreText);
            $('#result-message').html('<strong>' + data.message + '</strong>');
            
            // Add pass/fail class with RTL support
            this.resultsContainer.removeClass('passed failed');
            this.resultsContainer.addClass(data.passed ? 'passed' : 'failed');
            this.resultsContainer.attr('dir', 'rtl');
            
            // Show score message
            let scoreMessage = 'ציון: ' + data.score + '/40 (' + percentage + '%)';
            $('#score-message').text(scoreMessage);
            
            // Show results
            this.resultsContainer.slideDown(500);
            
            // Hide form
            this.form.slideUp(300);
            
            // Show explanations if available
            if (data.detailed_results) {
                this.showExplanations(data.detailed_results);
            }
        },
        
        showExplanations: function(results) {
            results.forEach(function(result, index) {
                const questionBlock = $('.question-block[data-question="' + result.question_index + '"]');
                const explanation = questionBlock.find('.question-explanation');
                
                // Mark correct/incorrect answers
                const selectedAnswer = questionBlock.find('.answer-input:checked');
                const answerOption = selectedAnswer.closest('.answer-option');
                
                if (result.is_correct) {
                    answerOption.addClass('correct-answer');
                } else {
                    answerOption.addClass('incorrect-answer');
                    
                    // Highlight the correct answer
                    const correctAnswer = questionBlock.find('.answer-input[data-correct="1"]');
                    correctAnswer.closest('.answer-option').addClass('correct-answer-highlight');
                }
                
                // Show explanation if available
                if (explanation.length && result.explanation) {
                    explanation.slideDown(300);
                }
            });
        },
        
        setLoadingState: function(loading) {
            if (loading) {
                this.submitButton.prop('disabled', true);
                this.submitButton.text(acfQuiz.strings.submitting);
                this.submitButton.addClass('loading');
            } else {
                this.submitButton.prop('disabled', false);
                this.submitButton.text(acfQuiz.strings.submit);
                this.submitButton.removeClass('loading');
            }
        },
        
        showError: function(message) {
            // Remove any existing error messages
            $('.quiz-error-message').remove();
            
            // Add error message
            const errorHtml = `
                <div class="quiz-error-message" style="
                    background: #f8d7da; 
                    color: #721c24; 
                    padding: 12px; 
                    border-radius: 4px; 
                    margin: 15px 0; 
                    border: 1px solid #f5c6cb;
                    text-align: right;
                    direction: rtl;
                ">
                    <i class="dashicons dashicons-warning"></i>
                    ${message}
                </div>
            `;
            
            this.form.prepend(errorHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.hideError();
            }, 5000);
        },
        
        hideError: function() {
            $('.quiz-error-message').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        scrollToResults: function() {
            $('html, body').animate({
                scrollTop: this.resultsContainer.offset().top - 100
            }, 500);
        }
    };
    
    // Initialize the quiz system with RTL support
    $(document).ready(function() {
        // Set RTL for the quiz container if not already set
        if (!$('.acf-quiz-container').attr('dir')) {
            $('.acf-quiz-container').attr('dir', 'rtl');
        }
        
        // Initialize the quiz system
        QuizSystem.init();
        
        // Add RTL class to form elements
        $('.acf-quiz-container input, .acf-quiz-container textarea, .acf-quiz-container select').addClass('rtl-input');
        
        // Ensure all text is right-aligned
        $('.question-text, .answer-text').css('text-align', 'right');
    });
    
    // Add some additional styling via JavaScript for better UX
    $('.answer-input').each(function() {
        $(this).on('focus', function() {
            $(this).closest('.answer-option').addClass('focused');
        }).on('blur', function() {
            $(this).closest('.answer-option').removeClass('focused');
        });
    });
    
    // Add keyboard navigation
    $(document).on('keydown', function(e) {
        if (e.target.classList.contains('answer-input')) {
            const currentInput = $(e.target);
            const questionBlock = currentInput.closest('.question-block');
            const allInputs = questionBlock.find('.answer-input');
            const currentIndex = allInputs.index(currentInput);
            
            let nextIndex = -1;
            
            switch(e.keyCode) {
                case 38: // Up arrow
                    e.preventDefault();
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : allInputs.length - 1;
                    break;
                case 40: // Down arrow
                    e.preventDefault();
                    nextIndex = currentIndex < allInputs.length - 1 ? currentIndex + 1 : 0;
                    break;
                case 32: // Space
                    e.preventDefault();
                    currentInput.prop('checked', true).trigger('change');
                    break;
            }
            
            if (nextIndex >= 0) {
                allInputs.eq(nextIndex).focus();
            }
        }
    });
    
    // Add smooth transitions
    $('.question-block').each(function(index) {
        $(this).css({
            'opacity': '0',
            'transform': 'translateY(20px)'
        }).delay(index * 100).animate({
            'opacity': '1'
        }, 500).css('transform', 'translateY(0px)');
    });
});
