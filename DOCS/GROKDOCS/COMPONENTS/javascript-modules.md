# ACF Quiz System - JavaScript Modules & Components

## 🎯 JavaScript Architecture Overview

The ACF Quiz System uses a modular JavaScript architecture with a main `MultiStepQuiz` object that handles all quiz functionality. This document provides comprehensive documentation of all JavaScript components, methods, and event handling.

## 📁 JavaScript File Structure

### Main Files
- **`js/quiz-public.js`** - Core quiz functionality (800+ lines)
- **`css/quiz-public.css`** - Styling and responsive design (400+ lines)

### Inline JavaScript
- **Form validation logic** - Integrated into PHP template
- **Event handlers** - Inline jQuery event bindings
- **Dynamic CSS** - PHP-generated styles

## 🏗️ Core JavaScript Object: MultiStepQuiz

```javascript
const MultiStepQuiz = {
    // Properties
    form: null,
    currentStep: 1,
    totalSteps: 4,
    stepData: {},
    isSubmitting: false,

    // Core Methods
    init(): Initialize quiz system
    validateCurrentStep(): Validate current step
    showStep(): Display specific step
    handleNextStep(): Process step progression
    handleSubmit(): Process final submission

    // Utility Methods
    saveCurrentStepData(): Save data to server
    handleQuizResults(): Process results
    showError(): Display error messages
    showSuccess(): Display success messages
    setLoadingState(): Manage loading states
}
```

## 🔧 Method Documentation

### Initialization Methods

#### `init()` - System Initialization
```javascript
init: function() {
    // Initialize form elements
    this.form = $('#acf-quiz-form');
    this.nextButton = $('#next-step');
    this.prevButton = $('#prev-step');
    this.submitButton = $('#submit-form');

    // Bind event handlers
    this.bindEvents();

    // Initial setup
    this.updateStepDisplay();
    this.validateCurrentStep();
}
```
**Purpose**: Sets up the entire quiz system
**Dependencies**: jQuery, form HTML elements
**Side Effects**: Binds events, initializes validation

#### `bindEvents()` - Event Handler Setup
```javascript
bindEvents: function() {
    // Navigation buttons
    this.nextButton.on('click', this.handleNextStep.bind(this));
    this.prevButton.on('click', this.handlePrevStep.bind(this));
    this.submitButton.on('click', this.handleSubmit.bind(this));

    // Form validation
    this.form.on('input change', 'input, select, textarea', () => {
        this.validateCurrentStep(false);
    });

    // Answer selection
    $('.answer-input').on('change', (e) => {
        this.handleAnswerChange(e);
        this.validateCurrentStep(false);
    });
}
```
**Purpose**: Attaches all event listeners
**Event Types**:
- **Navigation**: Next/Previous/Submit button clicks
- **Validation**: Input changes for real-time validation
- **Quiz Interaction**: Radio button selections
- **Form State**: Loading states and error handling

### Navigation Methods

#### `handleNextStep()` - Step Progression
```javascript
handleNextStep: function() {
    // Validate current step with error display
    if (this.validateCurrentStep(true)) {
        this.saveCurrentStepData();
        if (this.currentStep < this.totalSteps) {
            this.currentStep++;
            this.showStep(this.currentStep);
        }
    } else {
        this.showValidationErrors();
        this.showError('אנא מלאו את כל השדות הנדרשים');
    }
}
```
**Purpose**: Processes progression to next step
**Validation**: Shows errors if validation fails
**Data Flow**: Saves current step data before advancing

#### `handlePrevStep()` - Step Regression
```javascript
handlePrevStep: function() {
    if (this.currentStep > 1) {
        this.currentStep--;
        this.showStep(this.currentStep);
    }
}
```
**Purpose**: Returns to previous step
**Validation**: No validation required for backward navigation

#### `showStep(stepNumber)` - Step Display
```javascript
showStep: function(stepNumber) {
    // Hide all steps
    $('.form-step').removeClass('active');

    // Show target step
    $(`.form-step[data-step="${stepNumber}"]`).addClass('active');

    // Update UI elements
    this.updateStepDisplay();
    this.validateCurrentStep(false);

    // HOTFIX: Remove error classes from step 2
    if (stepNumber === 2) {
        setTimeout(() => {
            $(`.form-step[data-step="2"] .field-input`).removeClass('error touched');
        }, 100);
    }
}
```
**Purpose**: Displays the specified step
**Features**:
- Visual step transitions
- Step indicator updates
- Validation reset
- Error class cleanup (hotfix for Step 2)

### Validation Methods

#### `validateCurrentStep(showErrors)` - Step Validation
```javascript
validateCurrentStep: function(showErrors = false) {
    let isValid = true;

    // Step-specific validation
    if (this.currentStep === 1) {
        const personalValidation = this.validatePersonalDetails(showErrors);
        isValid = personalValidation.isValid;
    } else if (this.currentStep === 2) {
        // Validate required fields
        currentStepElement.find('input[required], select[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                if (showErrors) $(this).addClass('error touched');
            }
        });
    } else if (this.currentStep === 3 || this.currentStep === 4) {
        // Quiz validation
        const answered = this.validateAllAnswered();
        if (!answered) isValid = false;
    }

    this.updateNavigationButtons(isValid);
    return isValid;
}
```
**Purpose**: Validates the current step based on its requirements
**Parameters**:
- `showErrors` (boolean): Whether to display validation errors
**Returns**: Boolean validation result

#### `validatePersonalDetails(showErrors)` - Personal Info Validation
```javascript
validatePersonalDetails: function(showErrors = false) {
    const fields = {
        first_name: $('#first_name'),
        last_name: $('#last_name'),
        user_phone: $('#user_phone'),
        user_email: $('#user_email')
    };

    let isValid = true;
    let errorMessage = '';

    // Validate each field
    if (!fields.first_name.val().trim()) {
        isValid = false;
        if (showErrors) {
            fields.first_name.addClass('error touched');
            errorMessage = 'אנא מלא את השם הפרטי';
        }
    }

    // Email validation with regex
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(fields.user_email.val())) {
        isValid = false;
        if (showErrors) {
            fields.user_email.addClass('error touched');
            errorMessage = errorMessage || 'אנא הזן כתובת אימייל תקינה';
        }
    }

    return { isValid: isValid, errorMessage: errorMessage };
}
```
**Purpose**: Validates personal information fields (Step 1)
**Validation Rules**:
- First/Last name: Required, non-empty
- Phone: Required, basic format
- Email: Required, valid email format

#### `validateAllAnswered()` - Quiz Validation
```javascript
validateAllAnswered: function() {
    const totalQuestions = $('.question-block').length;
    const answeredQuestions = $('.question-block').filter(function() {
        return $(this).find('.answer-input:checked').length > 0;
    }).length;

    return totalQuestions === answeredQuestions;
}
```
**Purpose**: Ensures all quiz questions are answered
**Logic**: Compares total questions to answered questions

### Submission Methods

#### `handleSubmit()` - Final Submission
```javascript
handleSubmit: function(e) {
    e.preventDefault();

    // Critical: Set submission flags immediately
    if (typeof window.formSubmitting !== 'undefined') {
        window.formSubmitting = true;
    }
    this.isSubmitting = true;

    // Validate final declaration
    const finalDeclaration = $('#final_declaration').is(':checked');
    if (!finalDeclaration) {
        this.showError('אנא אשר את ההצהרה הסופית');
        return;
    }

    // Collect all form data
    const allData = this.collectAllFormData();

    // Calculate score
    const totalScore = this.calculateScore(allData);
    const passed = totalScore >= 21;

    // Store submission and redirect
    this.storeFinalSubmission(allData, totalScore, passed);
    setTimeout(() => {
        this.handleQuizResults({ passed: passed, score: totalScore });
    }, 1000);
}
```
**Purpose**: Processes the complete quiz submission
**Critical Features**:
- Immediate submission flag setting (prevents exit confirmation)
- Final declaration validation
- Score calculation
- AJAX submission with redirect delay

#### `collectAllFormData()` - Data Collection
```javascript
collectAllFormData: function() {
    const allData = {};

    // Collect all form inputs
    this.form.find('input, select, textarea').each(function() {
        const $field = $(this);
        const name = $field.attr('name');
        const type = $field.attr('type');
        const value = $field.val();

        if (type === 'radio' || type === 'checkbox') {
            if ($field.is(':checked')) {
                allData[name] = value;
            }
        } else {
            allData[name] = value;
        }
    });

    return allData;
}
```
**Purpose**: Gathers all form data for submission
**Data Types Handled**:
- Text inputs
- Select dropdowns
- Radio buttons (only checked values)
- Checkboxes (only checked values)
- Textareas

#### `calculateScore(formData)` - Score Calculation
```javascript
calculateScore: function(formData) {
    let totalScore = 0;

    // Sum all question scores (10 questions × max 4 points)
    for (let i = 0; i < 10; i++) {
        const questionKey = 'question_' + i;
        if (formData[questionKey]) {
            totalScore += parseInt(formData[questionKey]);
        }
    }

    return totalScore;
}
```
**Purpose**: Calculates the total quiz score
**Logic**: Sums all question answer values (1-4 points each)

### AJAX Methods

#### `saveCurrentStepData()` - Step Data Saving
```javascript
saveCurrentStepData: function() {
    const stepData = {};

    // Collect current step data
    $(`.form-step[data-step="${this.currentStep}"]`).find('input, select, textarea').each(function() {
        const $field = $(this);
        const name = $field.attr('name');
        const value = $field.val();

        stepData[name] = value;
    });

    // AJAX save
    $.ajax({
        url: acfQuiz.ajaxUrl,
        type: 'POST',
        data: {
            action: 'save_step_data',
            quiz_nonce: acfQuiz.nonce,
            current_step: this.currentStep,
            step_data: stepData
        },
        success: (response) => {
            console.log('Step data saved successfully:', response);
        },
        error: (xhr, status, error) => {
            console.error('Error saving step data:', error, xhr.responseText);
        }
    });
}
```
**Purpose**: Saves current step data to server
**AJAX Endpoint**: `save_step_data`
**Data**: Current step form fields only

#### `storeFinalSubmission()` - Final Submission Storage
```javascript
storeFinalSubmission: function(allData, totalScore, passed) {
    const submissionData = {
        action: 'handle_quiz_submission',
        quiz_nonce: acfQuiz.nonce,
        quiz_data: allData,
        total_score: totalScore,
        passed: passed,
        completed: 1
    };

    $.ajax({
        url: acfQuiz.ajaxUrl,
        type: 'POST',
        data: submissionData,
        success: function(response) {
            console.log('Final submission stored successfully:', response);
        },
        error: function(xhr, status, error) {
            console.log('Final submission storage failed:', error);
        }
    });
}
```
**Purpose**: Stores complete quiz submission
**AJAX Endpoint**: `handle_quiz_submission`
**Data**: Complete form data + calculated results

#### `submitForm()` - Legacy Submission Method
```javascript
submitForm: function(quizData) {
    this.setLoadingState(true);

    $.ajax({
        url: acfQuiz.ajaxUrl,
        type: 'POST',
        data: {
            action: 'submit_quiz',
            quiz_nonce: acfQuiz.nonce,
            form_data: quizData
        },
        success: (response) => {
            if (response.success) {
                this.handleQuizResults(response.data);
            } else {
                this.showError(response.data.message);
            }
        },
        error: (xhr, status, error) => {
            this.showError('שגיאה בשליחת השאלון. אנא נסה שוב.');
        },
        complete: () => {
            this.isSubmitting = false;
            this.setLoadingState(false);
        }
    });
}
```
**Purpose**: Legacy form submission method
**Fallback**: Used when primary submission fails

### UI Management Methods

#### `updateNavigationButtons(isValid)` - Button State Management
```javascript
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
}
```
**Purpose**: Updates navigation button states based on validation
**Logic**:
- Previous button: Show on Step 2-4
- Next button: Show on Step 1-3, disable if invalid
- Submit button: Show on Step 4, disable if invalid

#### `updateStepDisplay()` - Step Header Update
```javascript
updateStepDisplay: function() {
    const stepTitles = {
        1: { title: 'שאלון התאמה', subtitle: 'שלב 1 מתוך 4' },
        2: { title: 'פרטים אישיים', subtitle: 'שלב 2 מתוך 4' },
        3: { title: 'שאלון התאמה - חלק ב׳', subtitle: 'שלב 3 מתוך 4' },
        4: { title: 'שאלון התאמה - חלק ב׳', subtitle: 'שלב 4 מתוך 4' }
    };

    $('#step-title').text(stepTitles[this.currentStep].title);
    $('#step-subtitle').text(stepTitles[this.currentStep].subtitle);
}
```
**Purpose**: Updates step title and subtitle in header
**Language**: Hebrew RTL text

#### `setLoadingState(loading)` - Loading State Management
```javascript
setLoadingState: function(loading) {
    if (loading) {
        this.submitButton.prop('disabled', true);
        this.submitButton.text('שולח...');
        this.submitButton.addClass('loading');
        this.nextButton.prop('disabled', true);
        this.prevButton.prop('disabled', true);
    } else {
        this.submitButton.removeClass('loading');
        this.nextButton.prop('disabled', false);
        this.prevButton.prop('disabled', false);
        this.validateCurrentStep();
    }
}
```
**Purpose**: Manages loading states during AJAX operations
**Visual Feedback**: Button text changes, disabled states

### Error Handling Methods

#### `showError(message)` - Error Display
```javascript
showError: function(message) {
    const errorContainer = $('#quiz-error');
    if (errorContainer.length === 0) {
        this.form.before('<div id="quiz-error" class="quiz-message error-message" dir="rtl"></div>');
    }

    $('#quiz-error')
        .html('<div class="message-content">' + message + '</div>')
        .removeClass('success-message')
        .addClass('error-message')
        .slideDown(300);

    setTimeout(() => {
        this.hideError();
    }, 5000);
}
```
**Purpose**: Displays error messages to user
**Features**:
- Auto-creation of error container
- Hebrew RTL support
- Auto-hide after 5 seconds
- Scroll to error location

#### `showValidationErrors()` - Field Validation Display
```javascript
showValidationErrors: function() {
    const currentStepElement = $(`.form-step[data-step="${this.currentStep}"]`);

    // Show errors on required empty fields
    currentStepElement.find('input[required], select[required], textarea[required]').each(function() {
        const $field = $(this);
        if (!$field.val()) {
            $field.addClass('error');
        }
    });

    // Special handling for checkboxes
    currentStepElement.find('.checkbox-group').each(function() {
        const $checkbox = $(this).find('input[type="checkbox"][required]');
        if ($checkbox.length && !$checkbox.is(':checked')) {
            $(this).addClass('error');
        }
    });
}
```
**Purpose**: Highlights validation errors on form fields
**Visual Indicators**: Red borders, error classes

### Quiz Interaction Methods

#### `handleAnswerChange(event)` - Answer Selection
```javascript
handleAnswerChange: function(e) {
    const $input = $(e.target);
    const questionBlock = $input.closest('.question-block');
    const questionName = $input.attr('name');

    // Remove 'answered' class from all options in this question
    $(`input[name="${questionName}"]`).closest('.question-block').removeClass('answered');

    // Add 'answered' class to current question block
    questionBlock.addClass('answered');

    // Visual feedback for selected answer
    $(`input[name="${questionName}"]`).closest('.answer-option').removeClass('selected');
    $input.closest('.answer-option').addClass('selected');

    // Update validation
    this.validateCurrentStep();
}
```
**Purpose**: Handles quiz answer selections
**Visual Feedback**:
- 'answered' class on question block
- 'selected' class on chosen answer
- Validation updates

#### `handleQuizResults(data)` - Results Processing
```javascript
handleQuizResults: function(data) {
    if (data.passed) {
        // Passed: Redirect to WooCommerce checkout
        const packageType = this.getPackageTypeFromUrl();
        const productId = acfQuiz.productIds[packageType];

        if (productId) {
            window.location.href = `/checkout/?add-to-cart=${productId}&quiz_passed=1&score=${data.score}`;
        } else {
            window.location.href = '/shop/?quiz_passed=1';
        }
    } else {
        // Failed: Show results or redirect
        this.showError('ציון: ' + data.score + '/40 - לא עברת את המבחן');
        setTimeout(() => {
            window.location.href = '/followup?score=' + data.score;
        }, 3000);
    }
}
```
**Purpose**: Processes quiz completion results
**Logic**:
- Passed: WooCommerce redirect with product
- Failed: Error message + redirect to follow-up

### Utility Methods

#### `scrollToResults()` - UI Scrolling
```javascript
scrollToResults: function() {
    $('html, body').animate({
        scrollTop: this.resultsContainer.offset().top - 100
    }, 500);
}
```
**Purpose**: Smooth scrolling to results section
**Animation**: 500ms duration with 100px offset

#### `hideError()` - Error Cleanup
```javascript
hideError: function() {
    $('#quiz-error').fadeOut(300, function() {
        $(this).remove();
    });
}
```
**Purpose**: Removes error messages from UI
**Animation**: Fade out over 300ms

## 🎨 CSS Architecture

### Key CSS Classes

#### Form Structure
```css
.acf-quiz-container    /* Main container with RTL */
.form-step            /* Individual step containers */
.form-step.active     /* Currently visible step */
.field-input          /* All form input fields */
```

#### Validation States
```css
.field-input.error              /* Invalid field styling */
.field-input.touched            /* User-interacted fields */
.field-input.error.touched      /* Invalid touched fields */
.field-input:invalid            /* Browser validation state */
```

#### Navigation
```css
.nav-btn               /* All navigation buttons */
.next-btn              /* Next step button */
.prev-btn              /* Previous step button */
.submit-btn            /* Final submit button */
.nav-btn:disabled      /* Disabled button styling */
```

#### Quiz Components
```css
.question-block        /* Individual question container */
.answer-option         /* Radio button answer option */
.answer-option.selected /* Selected answer styling */
.question-block.answered /* Answered question styling */
```

### Responsive Design

#### Mobile Optimizations
```css
@media (max-width: 768px) {
    .date-input-group {
        flex-direction: column;
    }

    .nav-btn {
        width: 100%;
        margin-bottom: 10px;
    }
}
```

#### RTL Support
```css
[dir="rtl"] .nav-btn {
    float: right;
}

[dir="rtl"] .answer-label {
    text-align: right;
}
```

## 🔧 Event Handling

### jQuery Events

#### Form Events
```javascript
// Real-time validation
$(document).on('input change', '#acf-quiz-form input, #acf-quiz-form select', function() {
    MultiStepQuiz.validateCurrentStep(false);
});

// Final declaration validation
$(document).on('change', '#final_declaration', function() {
    MultiStepQuiz.validateCurrentStep();
});
```

#### Button Events
```javascript
// Navigation buttons
$('#next-step').on('click', function(e) {
    if ($(this).prop('disabled')) {
        e.preventDefault();
        MultiStepQuiz.showValidationErrors();
        MultiStepQuiz.showError('אנא מלאו את כל השדות הנדרשים');
    }
});
```

#### Keyboard Navigation
```javascript
// Arrow key navigation for quiz answers
$(document).on('keydown', function(e) {
    if (e.target.classList.contains('answer-input')) {
        // Handle up/down arrow navigation
        const currentInput = $(e.target);
        // Navigation logic...
    }
});
```

## 🐛 Debugging Features

### Console Logging
```javascript
// Comprehensive logging throughout
console.log('=== STEP 2 HOTFIX: Removing error/touched classes ===');
console.log('Found step 2 fields:', step2Fields.length);
console.log('Field validation result:', isValid);
console.log('Quiz submission response:', response);
```

### Error Tracking
```javascript
// AJAX error handling
error: (xhr, status, error) => {
    console.error('AJAX error during quiz submission:', {xhr, status, error});
    console.error('Error details:', xhr.responseText);
    this.showError('שגיאה בשליחת השאלון. אנא נסה שוב.');
}
```

## 🔄 Integration Points

### WordPress Integration
```javascript
// Localized script variables
wp_localize_script('acf-quiz-public', 'acfQuiz', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('acf_quiz_nonce'),
    'productIds' => array(
        'trial' => get_option('quiz_trial_product_id'),
        'monthly' => get_option('quiz_monthly_product_id'),
        'yearly' => get_option('quiz_yearly_product_id')
    )
));
```

### WooCommerce Integration
```javascript
// Checkout field population
$(document.body).on('updated_checkout', function() {
    // Pre-fill fields from quiz session data
    $('#billing_first_name').val(sessionData.first_name);
    $('#billing_last_name').val(sessionData.last_name);
    $('#billing_email').val(sessionData.email);
});
```

This comprehensive JavaScript documentation covers all components, methods, and interactions in the ACF Quiz System frontend.
