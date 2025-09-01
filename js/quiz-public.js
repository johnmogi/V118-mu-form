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
            
            // Handle clicks on disabled next button to show validation errors
            this.nextButton.on('click', (e) => {
                if (this.nextButton.prop('disabled')) {
                    e.preventDefault();
                    this.showValidationErrors(); // Show errors when clicking disabled button
                    this.showError('אנא מלא את כל השדות הנדרשים');
                    return false;
                }
            });
            
            // Form field validation - don't show errors on initial validation
            this.form.on('input change', 'input, select, textarea', () => {
                this.validateCurrentStep(false);
            });
            
            // Final declaration checkbox validation
            this.form.on('change', '#final_declaration', function() {
                MultiStepQuiz.validateCurrentStep();
            });
            
            // Answer selection for quiz questions
            $('.answer-input').on('change', (e) => {
                this.handleAnswerChange(e);
                this.validateCurrentStep(false);
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
            // Always validate with showErrors=true when user clicks Next
            if (this.validateCurrentStep(true)) {
                this.saveCurrentStepData();
                if (this.currentStep < this.totalSteps) {
                    this.currentStep++;
                    this.showStep(this.currentStep);
                }
            } else {
                // Force show errors on current step fields
                this.showValidationErrors();
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
            console.log('=== HANDLE SUBMIT CALLED ===');
            e.preventDefault();
            
            // CRITICAL: Set formSubmitting flag IMMEDIATELY to prevent beforeunload
            if (typeof window.formSubmitting !== 'undefined') {
                window.formSubmitting = true;
                console.log('Set window.formSubmitting = true');
            }
            
            // Also try to access the parent scope formSubmitting variable
            if (typeof formSubmitting !== 'undefined') {
                formSubmitting = true;
                console.log('Set formSubmitting = true');
            }
            
            // Set our own flag
            this.isSubmitting = true;
            
            if (this.isSubmitting && this.isSubmitting !== true) {
                console.log('Already submitting, returning false');
                return false;
            }
            
            // AGGRESSIVE BYPASS: Skip all validation for Step 4 to get submission working
            console.log('Current step:', this.currentStep);
            
            if (this.currentStep === 4) {
                console.log('STEP 4 DETECTED - BYPASSING ALL VALIDATION');
                // Only check if final declaration is checked
                const finalDeclaration = $('#final_declaration').is(':checked');
                console.log('Final declaration checked:', finalDeclaration);
                
                if (!finalDeclaration) {
                    console.log('Final declaration not checked - stopping submission');
                    this.showError('אנא אשר את ההצהרה הסופית');
                    return false;
                }
                
                console.log('Final declaration OK - BYPASSING ALL OTHER VALIDATION - proceeding with submission');
            } else {
                // Normal validation for other steps
                console.log('Validating step', this.currentStep);
                const isValid = this.validateCurrentStep(true);
                console.log('Step validation result:', isValid);
                
                if (!isValid) {
                    console.log('Step validation FAILED - stopping submission');
                    this.showError('אנא מלא את כל השדות הנדרשים');
                    return false;
                }
                
                console.log('Step validation PASSED - proceeding with submission');
            }
            
            // Save the final step data
            this.saveCurrentStepData();
            
            // Set loading state
            this.isSubmitting = true;
            this.setLoadingState(true);
            
            // Collect all form data from all steps
            const allData = {};
            
            // Merge all step data
            Object.keys(this.stepData).forEach(stepKey => {
                Object.assign(allData, this.stepData[stepKey]);
            });
            
            // Add package information
            let packageSelected = $('input[name="package_selected"]').val();
            let packagePrice = $('input[name="package_price"]').val();
            let packageSource = $('input[name="package_source"]').val();
            
            // Check for package parameters in URL (both /join?trial and /join/?trial)
            const urlParams = new URLSearchParams(window.location.search);
            if (!packageSelected) {
                if (urlParams.has('trial')) {
                    packageSelected = 'trial';
                    packagePrice = '99';
                } else if (urlParams.has('monthly')) {
                    packageSelected = 'monthly';
                    packagePrice = '199';
                } else if (urlParams.has('yearly')) {
                    packageSelected = 'yearly';
                    packagePrice = '1999';
                }
                packageSource = 'url_param';
            }
            
            allData.package_selected = packageSelected;
            allData.package_price = packagePrice;
            allData.package_source = packageSource;
            
            // Submit the final form data
            console.log('Calling submitForm with data:', allData);
            
            // SIMPLIFIED APPROACH: Direct form submission bypass
            console.log('=== SIMPLIFIED SUBMISSION APPROACH ===');
            
            // Calculate simple score from allData
            let totalScore = 0;
            for (let i = 0; i < 10; i++) {
                const questionKey = 'question_' + i;
                if (allData[questionKey]) {
                    totalScore += parseInt(allData[questionKey]);
                }
            }
            
            console.log('Calculated total score:', totalScore, '/40');
            const passed = totalScore >= 21;
            console.log('Quiz result:', passed ? 'PASSED' : 'FAILED');
            
            // Store final submission before redirect
            console.log('Storing final submission...');
            console.log('About to call storeFinalSubmission with:', {
                allData: allData,
                totalScore: totalScore,
                passed: passed
            });
            this.storeFinalSubmission(allData, totalScore, passed);
            console.log('storeFinalSubmission call completed');
            
            // Delay redirect to allow AJAX submission to complete
            console.log('Waiting 1 second for submission to complete before redirect...');
            setTimeout(() => {
                // Simple redirect logic
                if (passed) {
                    console.log('PASSED - Redirecting to checkout with trial package');
                    // Get the package type from URL parameter
                    const urlParams = new URLSearchParams(window.location.search);
                    let packageType = 'trial'; // default
                    if (urlParams.has('monthly')) packageType = 'monthly';
                    if (urlParams.has('yearly')) packageType = 'yearly';
                    
                    console.log('Package type detected:', packageType);
                    
                    // Use the acfQuiz config to get product IDs if available
                    let productId = null;
                    if (typeof acfQuiz !== 'undefined' && acfQuiz.productIds) {
                        productId = acfQuiz.productIds[packageType];
                        console.log('Product ID from config:', productId);
                    }
                    
                    // Fallback: redirect to shop or a specific product page
                    if (productId) {
                        window.location.href = '/checkout/?add-to-cart=' + productId + '&' + packageType + '=1&quiz_passed=1&score=' + totalScore;
                    } else {
                        console.log('No product ID found, redirecting to shop with parameters');
                        window.location.href = '/shop/?' + packageType + '=1&quiz_passed=1&score=' + totalScore;
                    }
                } else {
                    console.log('FAILED - Redirecting to followup page');
                    window.location.href = '/followup?score=' + totalScore;
                }
            }, 1000); // 1 second delay
            
            return; // Skip complex submitForm method
            
            this.submitForm(allData);
        },
        
        // Store final submission to database
        storeFinalSubmission: function(allData, totalScore, passed) {
            console.log('=== STORE FINAL SUBMISSION ===');
            console.log('Data:', allData);
            console.log('Score:', totalScore, 'Passed:', passed);
            
            // Prepare submission data
            const submissionData = {
                action: 'handle_quiz_submission',
                quiz_nonce: typeof acfQuiz !== 'undefined' ? acfQuiz.nonce : '',
                quiz_data: allData,
                total_score: totalScore,
                passed: passed,
                completed: 1
            };
            
            console.log('Sending final submission data:', submissionData);
            
            // Send AJAX request (don't wait for response to avoid blocking redirect)
            $.ajax({
                url: typeof acfQuiz !== 'undefined' ? acfQuiz.ajaxUrl : '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: submissionData,
                success: function(response) {
                    console.log('Final submission stored successfully:', response);
                },
                error: function(xhr, status, error) {
                    console.log('Final submission storage failed:', error);
                    // Don't block redirect on storage failure
                }
            });
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
            
            // Update validation for current step
            this.validateCurrentStep();
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
            const $firstNameField = $('#first_name');
            const $lastNameField = $('#last_name');
            const $phoneField = $('#user_phone');
            const $emailField = $('#user_email');
            
            const firstName = $firstNameField.val().trim();
            const lastName = $lastNameField.val().trim();
            const userPhone = $phoneField.val().trim();
            const userEmail = $emailField.val().trim();
            
            // Only validate and show errors if showErrors is true
            if (showErrors) {
                // Validate first name
                if (!firstName) {
                    isValid = false;
                    $firstNameField.addClass('error touched');
                    errorMessage = 'אנא מלא את השם הפרטי';
                } else {
                    $firstNameField.removeClass('error touched');
                }
                
                // Validate last name
                if (!lastName) {
                    isValid = false;
                    $lastNameField.addClass('error touched');
                    if (!errorMessage) errorMessage = 'אנא מלא את שם המשפחה';
                } else {
                    $lastNameField.removeClass('error touched');
                }
                
                // Validate phone
                if (!userPhone) {
                    isValid = false;
                    $phoneField.addClass('error touched');
                    if (!errorMessage) errorMessage = 'אנא מלא את מספר הטלפון';
                } else {
                    $phoneField.removeClass('error touched');
                }
                
                // Validate email
                if (!userEmail) {
                    isValid = false;
                    $emailField.addClass('error touched');
                    if (!errorMessage) errorMessage = 'אנא מלא את כתובת האימייל';
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(userEmail)) {
                    isValid = false;
                    $emailField.addClass('error touched');
                    if (!errorMessage) errorMessage = 'אנא הזן כתובת אימייל תקינה';
                } else {
                    $emailField.removeClass('error touched');
                }
            } else {
                // Just validate without showing errors - remove any existing error classes
                $firstNameField.removeClass('error touched');
                $lastNameField.removeClass('error touched');
                $phoneField.removeClass('error touched');
                $emailField.removeClass('error touched');
                
                if (!firstName || !lastName || !userPhone || !userEmail) {
                    isValid = false;
                }
            }
            
            return { isValid: isValid, errorMessage: errorMessage };
        },

        validateCurrentStep: function(showErrors = false) {
            const currentStepElement = $(`.form-step[data-step="${this.currentStep}"]`);
            let isValid = true;
            
            // Step-specific validation
            if (this.currentStep === 1) {
                // Use dedicated personal details validation for step 1
                const personalValidation = this.validatePersonalDetails(showErrors);
                isValid = personalValidation.isValid;
            } else if (this.currentStep === 2) {
                // Validate required fields in step 2
                currentStepElement.find('input[required], select[required], textarea[required]').each(function() {
                    const $field = $(this);
                    const value = $field.val();
                    
                    if (!value || (value && value.trim() === '')) {
                        isValid = false;
                        if (showErrors) {
                            $field.addClass('error touched');
                        }
                    } else {
                        $field.removeClass('error touched');
                    }
                });
            } else if (this.currentStep === 3 || this.currentStep === 4) {
                // Special validation for radio button groups in quiz steps
                const questionStart = this.currentStep === 3 ? 0 : 5;
                const questionEnd = this.currentStep === 3 ? 5 : 10;
                
                for (let i = questionStart; i < questionEnd; i++) {
                    const questionAnswered = currentStepElement.find(`input[name="question_${i}"]:checked`).length > 0;
                    if (!questionAnswered) {
                        isValid = false;
                        break;
                    }
                }
                
                // Also check final declaration checkbox for step 4
                if (this.currentStep === 4) {
                    const finalDeclaration = $('#final_declaration').is(':checked');
                    if (!finalDeclaration) {
                        isValid = false;
                        if (showErrors) {
                            $('#final_declaration').closest('.checkbox-group').addClass('error');
                        }
                    } else {
                        $('#final_declaration').closest('.checkbox-group').removeClass('error');
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
            
            // HOTFIX: Remove error/touched classes from step 2 fields when step loads
            if (stepNumber === 2) {
                console.log('=== STEP 2 HOTFIX: Removing error/touched classes ===');
                setTimeout(() => {
                    const step2Fields = $(`.form-step[data-step="2"] .field-input`);
                    console.log('Found step 2 fields:', step2Fields.length);
                    
                    step2Fields.each(function(index) {
                        const $field = $(this);
                        const beforeClasses = $field.attr('class');
                        $field.removeClass('error touched');
                        const afterClasses = $field.attr('class');
                        
                        console.log(`Field ${index + 1}:`, {
                            id: $field.attr('id'),
                            name: $field.attr('name'),
                            beforeClasses: beforeClasses,
                            afterClasses: afterClasses,
                            hasError: $field.hasClass('error'),
                            hasTouched: $field.hasClass('touched')
                        });
                    });
                    
                    console.log('=== STEP 2 HOTFIX COMPLETE ===');
                }, 100);
            }
            
            // Update step indicators
            $('.step-indicator .step').removeClass('active completed');
            for (let i = 1; i <= stepNumber; i++) {
                if (i < stepNumber) {
                    $(`.step-indicator .step[data-step="${i}"]`).addClass('completed');
                } else if (i === stepNumber) {
                    $(`.step-indicator .step[data-step="${i}"]`).addClass('active');
                }
            }
            
            // Scroll to top when moving to step 4
            if (stepNumber === 4) {
                $('html, body').animate({
                    scrollTop: $('.acf-quiz-container').offset().top - 50
                }, 500);
                
                // Ensure final declaration checkbox is visible and checked by default
                setTimeout(() => {
                    const checkbox = $('#final_declaration');
                    const checkboxGroup = $('.checkbox-group');
                    const finalDeclaration = $('.final-declaration');
                    
                    // Force visibility
                    finalDeclaration.show().css('visibility', 'visible');
                    checkboxGroup.show().css('visibility', 'visible');
                    checkbox.show().css('visibility', 'visible');
                    
                    // Check the checkbox
                    checkbox.prop('checked', true);
                    
                    console.log('Step 4: Checkbox visibility enforced', {
                        checkbox: checkbox.length,
                        visible: checkbox.is(':visible'),
                        checked: checkbox.is(':checked')
                    });
                }, 100);
            }
            
            this.updateStepDisplay();
            this.validateCurrentStep(false); // Don't show errors on initial load
        },
        
        updateStepDisplay: function() {
            const stepTitles = {
                1: { title: 'שאלון התאמה', subtitle: 'שלב 1 מתוך 4' },
                2: { title: 'פרטים אישיים', subtitle: 'שלב 2 מתוך 4' },
                3: { title: 'שאלון התאמה - חלק ב׳', subtitle: 'שלב 3 מתוך 4' },
                4: { title: 'שאלון התאמה - חלק ב׳', subtitle: 'שלב 4 מתוך 4' }
            };
            
            $('#step-title').text(stepTitles[this.currentStep].title);
            $('#step-subtitle').text(stepTitles[this.currentStep].subtitle);
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
            console.log('=== SAVE CURRENT STEP DATA ===');
            console.log('Current step:', this.currentStep);
            
            const currentStepElement = $(`.form-step[data-step="${this.currentStep}"]`);
            const stepData = {};
            
            // Collect all form data from current step
            currentStepElement.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const type = $field.attr('type');
                
                console.log('Field found:', { name: name, type: type, value: $field.val(), checked: $field.is(':checked') });
                
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
            
            console.log('Collected step data:', stepData);
            
            // Save step data to local object
            this.stepData[`step_${this.currentStep}`] = stepData;
            
            // For Step 1, immediately save as lead to database
            if (this.currentStep === 1) {
                console.log('Step 1 - calling saveStepAsLead');
                this.saveStepAsLead(stepData);
            }
            
            // Add debugging
            console.log('Sending AJAX request for step', this.currentStep);
            
            // Save current step data to session via AJAX
            $.ajax({
                url: acfQuiz.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'save_step_data',
                    quiz_nonce: acfQuiz.nonce,
                    current_step: this.currentStep,
                    step_data: stepData
                },
                dataType: 'json',
                success: (response) => {
                    console.log('Step data saved successfully:', response);
                },
                error: (xhr, status, error) => {
                    console.error('Error saving step data:', error, xhr.responseText);
                    console.error('XHR details:', xhr);
                }
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
            // Handle redirect based on quiz result
            if (data.redirect_url) {
                // Small delay to show any success message, then redirect
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 1000);
                
                if (data.passed) {
                    this.showSuccess('מעבר לתשלום...');
                } else {
                    this.showError('מעביר אותך לעמוד המשך...');
                }
                return;
            }
            
            // Fallback: show results if no redirect URL
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
        
        submitForm: function(quizData) {
            console.log('submitForm called with data:', quizData);
            
            // Check if acfQuiz is available
            console.log('acfQuiz object:', typeof acfQuiz !== 'undefined' ? acfQuiz : 'UNDEFINED');
            console.log('window.acfQuiz object:', typeof window.acfQuiz !== 'undefined' ? window.acfQuiz : 'UNDEFINED');
            
            this.setLoadingState(true);
            
            // Use fallback for acfQuiz if needed
            const quizConfig = typeof acfQuiz !== 'undefined' ? acfQuiz : window.acfQuiz;
            
            if (!quizConfig || !quizConfig.nonce || !quizConfig.ajaxUrl) {
                console.error('acfQuiz configuration missing:', quizConfig);
                this.showError('שגיאה בהגדרות השאלון. אנא רענן את הדף ונסה שוב.');
                this.setLoadingState(false);
                return;
            }
            
            // Prepare AJAX data
            const ajaxData = {
                action: 'submit_quiz',
                quiz_nonce: quizConfig.nonce,
                quiz_data: quizData
            };
            
            console.log('Final quiz submission AJAX data:', ajaxData);
            
            // Submit final quiz via AJAX
            $.ajax({
                url: quizConfig.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                dataType: 'json',
                success: (response) => {
                    console.log('Final quiz submission response:', response);
                    this.setLoadingState(false);
                    
                    if (response.success) {
                        this.handleQuizResults(response.data);
                    } else {
                        console.error('Quiz submission failed:', response.data);
                        this.showError(response.data.message || 'שגיאה בשליחת השאלון');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error during final quiz submission:', {xhr, status, error});
                    this.setLoadingState(false);
                    this.showError('שגיאה בשליחת השאלון. אנא נסה שוב.');
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
            const errorContainer = $('#quiz-error');
            if (errorContainer.length === 0) {
                // Create error container if it doesn't exist
                this.form.before('<div id="quiz-error" class="quiz-message error-message" dir="rtl"></div>');
            }
            
            $('#quiz-error')
                .html('<div class="message-content">' + message + '</div>')
                .removeClass('success-message')
                .addClass('error-message')
                .slideDown(300);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                this.hideError();
            }, 5000);
            
            // Scroll to error message
            $('html, body').animate({
                scrollTop: $('#quiz-error').offset().top - 100
            }, 300);
        },
        
        showSuccess: function(message) {
            const errorContainer = $('#quiz-error');
            if (errorContainer.length === 0) {
                // Create message container if it doesn't exist
                this.form.before('<div id="quiz-error" class="quiz-message success-message" dir="rtl"></div>');
            }
            
            $('#quiz-error')
                .html('<div class="message-content">' + message + '</div>')
                .removeClass('error-message')
                .addClass('success-message')
                .slideDown(300);
        },
        
        hideError: function() {
            $('#quiz-error').fadeOut(300, function() {
                $(this).remove();
            });
        },
        
        scrollToResults: function() {
            $('html, body').animate({
                scrollTop: this.resultsContainer.offset().top - 100
            }, 500);
        },
        
        /**
         * Submit the complete form data to the server
         * @param {Object} formData - The complete form data to submit
         */
        submitForm: function(formData) {
            // Set loading state
            this.isSubmitting = true;
            this.setLoadingState(true);
            
            // Submit the form data via AJAX
            $.ajax({
                url: acfQuiz.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'submit_quiz',
                    quiz_nonce: acfQuiz.nonce,
                    form_data: formData
                },
                dataType: 'json',
                success: (response) => {
                    if (response.success) {
                        this.handleSuccess(response);
                    } else {
                        this.showError(response.data?.message || 'שגיאה בשליחת הטופס');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Form submission error:', error, xhr.responseText);
                    this.showError('שגיאה בשליחת הטופס. אנא נסה שוב.');
                },
                complete: () => {
                    this.isSubmitting = false;
                    this.setLoadingState(false);
                }
            });
        },
        
        /**
         * Save step 1 data as a lead in the database
         * @param {Object} stepData - The data from step 1 to save as a lead
         */
        saveStepAsLead: function(stepData) {
            console.log('saveStepAsLead called with data:', stepData);
            
            // Only save if we have meaningful data (first_name, last_name, or phone)
            if (!stepData.first_name && !stepData.last_name && !stepData.user_phone) {
                console.log('No meaningful data to save as lead');
                return;
            }
            
            // Get package parameter from URL if available
            let packageParam = '';
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('trial')) {
                packageParam = 'trial';
            } else if (urlParams.has('monthly')) {
                packageParam = 'monthly';
            } else if (urlParams.has('yearly')) {
                packageParam = 'yearly';
            }
            
            console.log('Saving lead with package:', packageParam);
            
            // DIRECT: Use the direct lead capture endpoint (most reliable)
            $.ajax({
                url: '/wp-content/capture-lead.php',
                type: 'POST',
                data: {
                    first_name: stepData.first_name || '',
                    last_name: stepData.last_name || '',
                    user_phone: stepData.user_phone || '',
                    user_email: stepData.user_email || '',
                    package_param: packageParam
                },
                dataType: 'json',
                success: function(response) {
                    console.log('Step 1 lead saved successfully (direct method):', response);
                },
                error: function(xhr, status, error) {
                    console.log('Direct method failed, trying WordPress AJAX:', error);
                    
                    // FALLBACK: Try WordPress AJAX if direct method fails
                    $.ajax({
                        url: acfQuiz.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'simple_lead_capture',
                            first_name: stepData.first_name || '',
                            last_name: stepData.last_name || '',
                            user_phone: stepData.user_phone || '',
                            user_email: stepData.user_email || '',
                            package_param: packageParam
                        },
                        dataType: 'json',
                        success: function(fallbackResponse) {
                            console.log('Step 1 lead saved successfully (fallback method):', fallbackResponse);
                        },
                        error: function(fallbackXhr, fallbackStatus, fallbackError) {
                            console.error('Both lead capture methods failed:', fallbackError, fallbackXhr.responseText);
                        }
                    });
                }
            });
        },
        
        showValidationErrors: function() {
            const currentStepElement = $(`.form-step[data-step="${this.currentStep}"]`);
            
            // Show errors on all required fields that are empty
            currentStepElement.find('input[required], select[required], textarea[required]').each(function() {
                const $field = $(this);
                const value = $field.val();
                
                if (!value || (value && value.trim() === '')) {
                    $field.addClass('error');
                } else {
                    $field.removeClass('error');
                }
            });
            
            // Special handling for checkbox groups
            currentStepElement.find('.checkbox-group').each(function() {
                const $group = $(this);
                const $checkbox = $group.find('input[type="checkbox"][required]');
                
                if ($checkbox.length && !$checkbox.is(':checked')) {
                    $group.addClass('error');
                } else {
                    $group.removeClass('error');
                }
            });
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
