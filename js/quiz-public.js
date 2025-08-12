/**
 * ACF Quiz System - Multi-Step Form JavaScript
 * RTL and Hebrew Support Enhanced
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Set RTL direction for the quiz container
    $('.acf-quiz-container').attr('dir', 'rtl');
    
    const MultiStepQuiz = {
        form: null,
        currentStep: 1,
        totalSteps: 4,
        stepData: {},
        isSubmitting: false,
        nextButton: null,
        prevButton: null,
        submitButton: null,
        resultsContainer: null,
        
        init: function() {
            this.form = $('#acf-quiz-form');
            this.nextButton = $('#next-step');
            this.prevButton = $('#prev-step');
            this.submitButton = $('#submit-form');
            this.resultsContainer = $('#quiz-results');
            
            if (this.form.length) {
                this.bindEvents();
                this.updateStepDisplay();
                this.validateCurrentStep();
            }
        },
        
        bindEvents: function() {
            // Navigation buttons
            this.nextButton.on('click', this.handleNextStep.bind(this));
            this.prevButton.on('click', this.handlePrevStep.bind(this));
            this.submitButton.on('click', this.handleSubmit.bind(this));
            
            // Form field validation - don't show errors on initial validation
            this.form.on('input change', 'input, select, textarea', () => {
                this.validateCurrentStep(false);
            });
            
            // Answer selection for quiz questions
            $('.answer-input').on('change', (e) => {
                this.handleAnswerChange(e);
                this.validateCurrentStep();
            });
            
            // Handle label clicks for radio buttons
            $('.answer-label').on('click', (e) => {
                const input = $(e.currentTarget).siblings('.answer-input');
                if (input.length) {
                    input.prop('checked', true).trigger('change');
                }
            });
        },
        
        handleNextStep: function() {
            if (this.validateCurrentStep(true)) {
                this.saveCurrentStepData();
                if (this.currentStep < this.totalSteps) {
                    this.currentStep++;
                    this.showStep(this.currentStep);
                }
            } else {
                this.showError('אנא מלא את כל השדות הנדרשים');
            }
        },
        
        handlePrevStep: function() {
            if (this.currentStep > 1) {
                this.currentStep--;
                this.showStep(this.currentStep);
            }
        },
        
        handleSubmit: function(e) {
            e.preventDefault();
            
            if (this.isSubmitting) {
                return false;
            }
            
            if (!this.validateCurrentStep(true)) {
                this.showError('אנא מלא את כל השדות הנדרשים');
                return false;
            }
            
            this.saveCurrentStepData();
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

        validateCurrentStep: function(showErrors = false) {
            const currentStepElement = $(`.form-step[data-step="${this.currentStep}"]`);
            let isValid = true;
            
            // Validate required fields in current step
            currentStepElement.find('input[required], select[required], textarea[required]').each(function() {
                const $field = $(this);
                const value = $field.val();
                
                if (!value || (value && value.trim() === '')) {
                    isValid = false;
                    if (showErrors) {
                        $field.addClass('error');
                    }
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Special validation for radio button groups in quiz steps
            if (this.currentStep === 3 || this.currentStep === 4) {
                const questionStart = this.currentStep === 3 ? 0 : 5;
                const questionEnd = this.currentStep === 3 ? 5 : 10;
                
                for (let i = questionStart; i < questionEnd; i++) {
                    const questionAnswered = currentStepElement.find(`input[name="question_${i}"]:checked`).length > 0;
                    if (!questionAnswered) {
                        isValid = false;
                        break;
                    }
                }
            }
            
            // Update navigation buttons
            this.updateNavigationButtons(isValid);
            
            return isValid;
        },
        
        showStep: function(stepNumber) {
            // Hide all steps
            $('.form-step').removeClass('active');
            
            // Show current step
            $(`.form-step[data-step="${stepNumber}"]`).addClass('active');
            
            // Update step indicator
            $('.step-indicator .step').removeClass('active completed');
            
            for (let i = 1; i <= stepNumber; i++) {
                if (i < stepNumber) {
                    $(`.step-indicator .step[data-step="${i}"]`).addClass('completed');
                } else if (i === stepNumber) {
                    $(`.step-indicator .step[data-step="${i}"]`).addClass('active');
                }
            }
            
            this.updateStepDisplay();
            this.validateCurrentStep(false); // Don't show errors on initial load
        },
        
        updateStepDisplay: function() {
            $('#step-title').text(acfQuiz.strings[`step${this.currentStep}Title`] || 'שאלון התאמה');
            $('#step-subtitle').text(acfQuiz.strings[`step${this.currentStep}Subtitle`] || `שלב ${this.currentStep} מתוך 4`);
        },
        
        updateNavigationButtons: function(isValid) {
            // Show/hide prev button
            if (this.currentStep > 1) {
                this.prevButton.show();
            } else {
                this.prevButton.hide();
            }
            
            // Show/hide next/submit buttons
            if (this.currentStep < this.totalSteps) {
                this.nextButton.show();
                this.submitButton.hide();
                this.nextButton.prop('disabled', !isValid);
            } else {
                this.nextButton.hide();
                this.submitButton.show();
                this.submitButton.prop('disabled', !isValid);
            }
        },
        
        saveCurrentStepData: function() {
            const currentStepElement = $(`.form-step[data-step="${this.currentStep}"]`);
            const stepData = {};
            
            // Collect all form data from current step
            currentStepElement.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const type = $field.attr('type');
                
                if (name) {
                    if (type === 'radio' || type === 'checkbox') {
                        if ($field.is(':checked')) {
                            stepData[name] = $field.val();
                        }
                    } else {
                        stepData[name] = $field.val();
                    }
                }
            });
            
            this.stepData[`step_${this.currentStep}`] = stepData;
            
            // Save to session via AJAX
            $.ajax({
                url: acfQuiz.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'save_step_data',
                    nonce: acfQuiz.nonce,
                    step: this.currentStep,
                    data: stepData
                },
                success: function(response) {
                    console.log('Step data saved:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error saving step data:', error);
                }
            });
        },
        
        submitQuiz: function() {
            this.isSubmitting = true;
            this.setLoadingState(true);
            
            // Collect all form data from all steps
            const allData = {};
            
            // Merge all step data
            Object.keys(this.stepData).forEach(stepKey => {
                Object.assign(allData, this.stepData[stepKey]);
            });
            
            // Add package information
            allData.package_selected = $('input[name="package_selected"]').val();
            allData.package_price = $('input[name="package_price"]').val();
            allData.package_source = $('input[name="package_source"]').val();
            
            // Submit via AJAX
            $.ajax({
                url: acfQuiz.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'submit_quiz',
                    quiz_nonce: $('#quiz_nonce').val(),
                    form_data: allData
                },
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
                this.nextButton.prop('disabled', true);
                this.prevButton.prop('disabled', true);
            } else {
                this.submitButton.removeClass('loading');
                this.nextButton.prop('disabled', false);
                this.prevButton.prop('disabled', false);
                this.validateCurrentStep();
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
    
    // Initialize the multi-step quiz system
    $(document).ready(function() {
        // Set RTL for the quiz container if not already set
        if (!$('.acf-quiz-container').attr('dir')) {
            $('.acf-quiz-container').attr('dir', 'rtl');
        }
        
        // Initialize the multi-step quiz system
        MultiStepQuiz.init();
        
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
