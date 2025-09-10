/**
 * ACF Quiz System - Multi-Step Form JavaScript
 * RTL and Hebrew Support Enhanced
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Main quiz object
    const MultiStepQuiz = {
        currentStep: 1,
        totalSteps: 4,
        stepData: {},
        isSubmitting: false,
        
        // Cache DOM elements
        form: null,
        nextButton: null,
        prevButton: null,
        submitButton: null,
        
        init: function() {
            console.log('Initializing MultiStepQuiz...');
            
            // Cache DOM elements
            this.form = $('#acf-quiz-form');
            this.nextButton = $('#next-step');
            this.prevButton = $('#prev-step');
            this.submitButton = $('#submit-form');
            
            if (!this.form.length) {
                console.error('Quiz form not found');
                return;
            }
            
            // Bind events
            this.bindEvents();
            
            // Initialize first step
            this.showStep(1);
            
            // Setup conditional elements
            this.setupConditionalElements();
            
            console.log('MultiStepQuiz initialized successfully');
        },
        
        bindEvents: function() {
            // Navigation buttons
            this.nextButton.on('click', this.handleNextStep.bind(this));
            this.prevButton.on('click', this.handlePrevStep.bind(this));
            this.submitButton.on('click', this.handleSubmit.bind(this));
            
            // Form field changes
            this.form.on('input change', 'input, select, textarea', this.handleAnswerChange.bind(this));
            
            // Prevent form submission on Enter
            this.form.on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Handle browser back/forward
            $(window).on('popstate', function() {
                // Prevent navigation away from form
                return false;
            });
            
            // Warn before leaving page
            $(window).on('beforeunload', function() {
                if (!MultiStepQuiz.isSubmitting) {
                    return 'יציאה מהטופס תגרום לאיבוד כל המידע שמילאת. האם אתה בטוח?';
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
            
            // Prevent double submission
            if (this.isSubmitting) {
                console.log('Form submission already in progress');
                return false;
            }
            
            // Store scroll position
            const scrollPosition = window.scrollY || document.documentElement.scrollTop;
            
            // Set submission flags
            this.isSubmitting = true;
            if (typeof window.formSubmitting !== 'undefined') {
                window.formSubmitting = true;
            }
            
            console.log('Current step:', this.currentStep);
            
            // For step 4, validate final declaration and signature
            if (this.currentStep === 4) {
                // Check final declaration
                const finalDeclaration = document.getElementById('final_declaration');
                if (!finalDeclaration || !finalDeclaration.checked) {
                    console.log('Final declaration not accepted');
                    this.showError('יש לאשר את הצהרת ההסכמה לפני שליחת הטופס');
                    this.isSubmitting = false;
                    // Restore scroll position
                    window.scrollTo(0, scrollPosition);
                    return false;
                }
                
                // Check signature
                const signatureData = document.getElementById('signature_data').value;
                if (!signatureData) {
                    console.log('No signature provided');
                    this.showError('נא לחתום על הטופס');
                    this.isSubmitting = false;
                    // Restore scroll position
                    window.scrollTo(0, scrollPosition);
                    return false;
                }
                
                console.log('All validations passed, preparing submission');
            }
            
            // Save current step data
            this.saveCurrentStepData();
            
            // Get all form data
            const formData = this.getAllFormData();
            
            // Add signature data if available
            const signatureData = document.getElementById('signature_data').value;
            if (signatureData) {
                formData.append('signature_data', signatureData);
            }
            
            console.log('Form data prepared for submission');
            
            // Show loading state
            this.setLoadingState(true);
            
            // Prevent any further scrolling during submission
            document.body.style.overflow = 'hidden';
            
            // Submit the form
            this.submitForm(formData);
            
            // Restore scroll after a short delay
            setTimeout(() => {
                window.scrollTo(0, scrollPosition);
                document.body.style.overflow = '';
            }, 100);
        },
        
        handleAnswerChange: function(e) {
            // Validate current step when answers change
            setTimeout(() => {
                this.validateCurrentStep();
            }, 100);
        },
        
        validateCurrentStep: function(showErrors = false) {
            console.log('=== VALIDATE CURRENT STEP ===');
            console.log('Current step:', this.currentStep);
            
            let isValid = true;
            const $currentStep = $(`.form-step[data-step="${this.currentStep}"]`);
            
            // Clear previous errors
            $currentStep.find('.error').removeClass('error');
            $currentStep.find('.has-error').removeClass('has-error');
            
            if (this.currentStep === 1) {
                // Step 1: Basic contact information
                const requiredFields = ['first_name', 'last_name', 'phone', 'email'];
                
                requiredFields.forEach(fieldName => {
                    const $field = $(`input[name="${fieldName}"]`);
                    const value = $field.val();
                    
                    if (!value || value.trim() === '') {
                        isValid = false;
                        if (showErrors) {
                            $field.addClass('error');
                            $field.closest('.form-group').addClass('has-error');
                        }
                    } else {
                        $field.removeClass('error');
                        $field.closest('.form-group').removeClass('has-error');
                    }
                });
                
            } else if (this.currentStep === 2) {
                // Step 2: Personal details
                const requiredFields = ['id_number', 'gender', 'birth_day', 'birth_month', 'birth_year', 
                                      'citizenship', 'address', 'marital_status', 'employment_status', 
                                      'education', 'profession'];
                
                requiredFields.forEach(fieldName => {
                    const $field = $(`[name="${fieldName}"]`);
                    let value = $field.val();
                    
                    if ($field.is('select')) {
                        value = $field.find('option:selected').val();
                    }
                    
                    if (!value || value === '' || value === 'בחר') {
                        isValid = false;
                        if (showErrors) {
                            $field.addClass('error');
                            $field.closest('.form-group').addClass('has-error');
                        }
                    } else {
                        $field.removeClass('error');
                        $field.closest('.form-group').removeClass('has-error');
                    }
                });
                
            } else if (this.currentStep === 3) {
                // Step 3: Quiz questions 1-5
                for (let i = 0; i < 5; i++) {
                    const $question = $(`.form-step[data-step="3"] input[name="question_${i}"]:checked`);
                    
                    if ($question.length === 0) {
                        isValid = false;
                        if (showErrors) {
                            $(`.form-step[data-step="3"] .question-block[data-question="${i}"]`).addClass('has-error');
                        }
                    } else {
                        $(`.form-step[data-step="3"] .question-block[data-question="${i}"]`).removeClass('has-error');
                    }
                }
                
            } else if (this.currentStep === 4) {
                // Step 4: Quiz questions 6-10 + declaration + signature
                
                // Check quiz questions 6-10
                for (let i = 5; i < 10; i++) {
                    const $question = $(`.form-step[data-step="4"] input[name="question_${i}"]:checked`);
                    
                    if ($question.length === 0) {
                        isValid = false;
                        if (showErrors) {
                            $(`.form-step[data-step="4"] .question-block[data-question="${i}"]`).addClass('has-error');
                        }
                    } else {
                        $(`.form-step[data-step="4"] .question-block[data-question="${i}"]`).removeClass('has-error');
                    }
                }
                
                // Check final declaration checkbox
                const $finalDeclaration = $('#final_declaration');
                
                if (!$finalDeclaration.is(':checked')) {
                    isValid = false;
                    if (showErrors) {
                        $finalDeclaration.addClass('error');
                        $finalDeclaration.closest('.declaration-checkbox').addClass('has-error');
                    }
                } else {
                    $finalDeclaration.removeClass('error');
                    $finalDeclaration.closest('.declaration-checkbox').removeClass('has-error');
                }
                
                // Check signature (required for step 4 completion)
                const signatureData = $('#signature_data').val();
                
                if (!signatureData) {
                    isValid = false;
                    if (showErrors) {
                        $('.signature-section').addClass('has-error');
                    }
                } else {
                    $('.signature-section').removeClass('has-error');
                }
            }
            
            console.log('Step validation result:', isValid);
            
            // Update navigation buttons based on validation
            this.updateNavigationButtons(isValid);
            
            return isValid;
        },
        
        showStep: function(stepNumber) {
            console.log('Showing step:', stepNumber);
            
            // Hide all steps
            $('.form-step').hide();
            
            // Show current step
            $(`.form-step[data-step="${stepNumber}"]`).show();
            
            // Update step indicators
            $('.step-indicator .step').removeClass('active');
            $(`.step-indicator .step[data-step="${stepNumber}"]`).addClass('active');
            
            // Update step display
            this.updateStepDisplay();
            
            // Validate current step and update buttons
            const isValid = this.validateCurrentStep();
            this.updateNavigationButtons(isValid);
            
            // Initialize signature pad when step 4 is shown
            if (stepNumber === 4) {
                setTimeout(() => {
                    if (typeof initSignaturePad === 'function') {
                        initSignaturePad();
                    }
                }, 200);
            }
            
            // Trigger custom event
            $(document).trigger('formStepChanged', [stepNumber]);
            
            // Scroll to top of form smoothly
            const formElement = $('#acf-quiz-form');
            if (formElement.length) {
                $('html, body').animate({
                    scrollTop: formElement.offset().top - 100
                }, 300);
            }
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
            // Show/hide prev button - only show from step 2 onwards
            if (this.currentStep > 1) {
                this.prevButton.show();
            } else {
                this.prevButton.hide();
            }
            
            // Handle next/submit buttons visibility
            if (this.currentStep < this.totalSteps) {
                // On steps 1-3, show next button if valid
                this.nextButton.show();
                this.submitButton.hide();
                this.nextButton.prop('disabled', !isValid);
            } else {
                // Only on step 4, show submit button and hide next button
                this.nextButton.hide();
                this.submitButton.show();
                this.submitButton.prop('disabled', !isValid);
            }
        },
        
        saveCurrentStepData: function() {
            console.log('Saving current step data...');
            
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
            
            // Save step data to local object
            this.stepData[`step_${this.currentStep}`] = stepData;
        },
        
        getAllFormData: function() {
            const formData = new FormData();
            
            // Add all step data
            Object.keys(this.stepData).forEach(stepKey => {
                const stepData = this.stepData[stepKey];
                Object.keys(stepData).forEach(fieldName => {
                    formData.append(fieldName, stepData[fieldName]);
                });
            });
            
            // Add current step data
            this.saveCurrentStepData();
            const currentStepData = this.stepData[`step_${this.currentStep}`];
            if (currentStepData) {
                Object.keys(currentStepData).forEach(fieldName => {
                    formData.append(fieldName, currentStepData[fieldName]);
                });
            }
            
            return formData;
        },
        
        submitForm: function(formData) {
            console.log('Submitting form...');
            
            $.ajax({
                url: acf_quiz_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    console.log('Form submitted successfully:', response);
                    this.handleSuccess(response);
                },
                error: (xhr, status, error) => {
                    console.error('Form submission error:', error);
                    this.handleError(xhr, status, error);
                },
                complete: () => {
                    this.setLoadingState(false);
                    this.isSubmitting = false;
                }
            });
        },
        
        handleSuccess: function(response) {
            this.showSuccess('הטופס נשלח בהצלחה!');
            // Redirect or show results
        },
        
        handleError: function(xhr, status, error) {
            this.showError('אירעה שגיאה בשליחת הטופס. אנא נסה שוב.');
        },
        
        setLoadingState: function(loading) {
            if (loading) {
                this.submitButton.prop('disabled', true).text('שולח...');
            } else {
                this.submitButton.prop('disabled', false).text('שלח שאלון');
            }
        },
        
        showError: function(message) {
            // Remove existing error messages
            $('.error-message').remove();
            
            // Create and show error message
            const errorHtml = `<div class="error-message alert alert-danger" style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">${message}</div>`;
            
            // Insert at top of current step
            $(`.form-step[data-step="${this.currentStep}"]`).prepend(errorHtml);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                $('.error-message').fadeOut();
            }, 5000);
        },
        
        showSuccess: function(message) {
            // Remove existing messages
            $('.success-message, .error-message').remove();
            
            // Create and show success message
            const successHtml = `<div class="success-message alert alert-success" style="margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">${message}</div>`;
            
            // Insert at top of form
            this.form.prepend(successHtml);
        },
        
        setupConditionalElements: function() {
            // Setup any conditional form elements based on package type or other factors
            console.log('Setting up conditional elements...');
        }
    };
    
    // Initialize the multi-step quiz system
    MultiStepQuiz.init();
    
    // Make it globally available
    window.MultiStepQuiz = MultiStepQuiz;
});
