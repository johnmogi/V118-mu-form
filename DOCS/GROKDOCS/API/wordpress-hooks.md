# ACF Quiz System - WordPress Hooks & Filters

## 🎣 WordPress Integration Points

This document details all WordPress hooks, filters, and integration points available in the ACF Quiz System for customization and extension.

## 📋 Action Hooks

### Form Rendering Hooks

#### `acf_quiz_before_form` - Pre-Form Display
```php
add_action('acf_quiz_before_form', 'my_custom_pre_form_content', 10, 1);

function my_custom_pre_form_content($atts) {
    // Add custom content before quiz form
    echo '<div class="custom-intro">';
    echo '<h2>Welcome to our Quiz!</h2>';
    echo '<p>Take this quiz to find the perfect package for you.</p>';
    echo '</div>';

    // Access shortcode attributes
    $package = $atts['package'] ?? 'trial';
    echo "<p>Selected package: {$package}</p>";
}
```
**Parameters:**
- `$atts` (array) - Shortcode attributes passed to the quiz
**Timing:** Before quiz form HTML is rendered
**Use Cases:**
- Add custom introduction content
- Display package-specific messaging
- Inject tracking pixels or analytics
- Add custom CSS/JS for specific implementations

#### `acf_quiz_after_form` - Post-Form Display
```php
add_action('acf_quiz_after_form', 'my_custom_post_form_content', 10, 1);

function my_custom_post_form_content($form_html) {
    // Add content after the quiz form
    echo '<div class="quiz-footer">';
    echo '<p>Need help? <a href="/contact">Contact our support team</a></p>';
    echo '<div class="social-share">';
    echo '<!-- Social sharing buttons -->';
    echo '</div>';
    echo '</div>';

    // Log form rendering
    error_log('Quiz form rendered for user: ' . get_current_user_id());
}
```
**Parameters:**
- `$form_html` (string) - Complete quiz form HTML
**Timing:** After quiz form HTML is generated
**Use Cases:**
- Add footer content or legal disclaimers
- Inject social sharing functionality
- Add custom tracking or analytics
- Implement A/B testing variations

#### `acf_quiz_before_questions` - Pre-Questions Content
```php
add_action('acf_quiz_before_questions', 'my_custom_instructions');

function my_custom_instructions() {
    // Add custom instructions before questions
    echo '<div class="custom-instructions">';
    echo '<h3>Important Instructions:</h3>';
    echo '<ul>';
    echo '<li>Answer all questions honestly for the best results</li>';
    echo '<li>You can change your answers before submitting</li>';
    echo '<li>The quiz takes about 5 minutes to complete</li>';
    echo '</ul>';
    echo '</div>';
}
```
**Parameters:** None
**Timing:** Before question blocks are rendered
**Use Cases:**
- Add custom quiz instructions
- Display progress indicators
- Show timer or countdown functionality
- Add encouragement messages

#### `acf_quiz_after_questions` - Post-Questions Content
```php
add_action('acf_quiz_after_questions', 'my_custom_post_questions');

function my_custom_post_questions() {
    // Add content after questions
    echo '<div class="progress-indicator">';
    echo '<p>You are almost done! Please review your answers and submit.</p>';
    echo '</div>';

    // Add custom validation or encouragement
    echo '<div class="encouragement-message">';
    echo '<p>Great job! You\'re about to discover your perfect investment package.</p>';
    echo '</div>';
}
```
**Parameters:** None
**Timing:** After question blocks are rendered
**Use Cases:**
- Add progress indicators
- Display encouragement messages
- Show summary of answers
- Add custom validation feedback

### Submission Processing Hooks

#### `acf_quiz_before_submission` - Pre-Submission Processing
```php
add_action('acf_quiz_before_submission', 'my_pre_submission_validation', 10, 1);

function my_pre_submission_validation($quiz_data) {
    // Custom validation before submission
    $errors = array();

    // Check email domain restrictions
    $blocked_domains = array('spam.com', 'temp-mail.org');
    $email_domain = substr(strrchr($quiz_data['user_email'], "@"), 1);

    if (in_array($email_domain, $blocked_domains)) {
        $errors[] = 'This email domain is not allowed';
    }

    // Check for suspicious patterns
    if (strlen($quiz_data['first_name']) < 2) {
        $errors[] = 'First name must be at least 2 characters';
    }

    if (!empty($errors)) {
        // Prevent submission and show errors
        wp_send_json_error(array(
            'message' => 'Validation failed',
            'errors' => $errors
        ));
        exit;
    }

    // Log submission attempt
    error_log('Quiz submission attempt from: ' . $quiz_data['user_email']);
}
```
**Parameters:**
- `$quiz_data` (array) - Complete quiz submission data
**Timing:** Before quiz processing begins
**Use Cases:**
- Custom validation logic
- Spam prevention
- Fraud detection
- Audit logging

#### `acf_quiz_after_submission` - Post-Submission Processing
```php
add_action('acf_quiz_after_submission', 'my_post_submission_actions', 10, 2);

function my_post_submission_actions($submission_id, $quiz_data) {
    // Actions after successful submission
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_submissions';

    // Send custom email notification
    $admin_email = get_option('admin_email');
    $subject = 'New Quiz Submission';
    $message = "New quiz submission from: {$quiz_data['first_name']} {$quiz_data['last_name']}\n";
    $message .= "Email: {$quiz_data['user_email']}\n";
    $message .= "Score: {$quiz_data['score']}/{$quiz_data['max_score']}\n";
    $message .= "Passed: " . ($quiz_data['passed'] ? 'Yes' : 'No');

    wp_mail($admin_email, $subject, $message);

    // Update external CRM system
    my_crm_integration($quiz_data);

    // Trigger custom events
    do_action('my_custom_quiz_completed', $submission_id, $quiz_data);
}
```
**Parameters:**
- `$submission_id` (int) - Database ID of the submission
- `$quiz_data` (array) - Complete quiz submission data
**Timing:** After successful database insertion
**Use Cases:**
- Email notifications
- CRM integrations
- Analytics tracking
- Custom business logic

### Admin Interface Hooks

#### `acf_quiz_admin_scripts` - Admin Assets
```php
add_action('acf_quiz_admin_scripts', 'my_admin_customizations');

function my_admin_customizations() {
    // Add custom admin CSS
    wp_add_inline_style('acf-quiz-admin', '
        .quiz-submission-row.passed { background-color: #e8f5e8; }
        .quiz-submission-row.failed { background-color: #ffeaea; }
    ');

    // Add custom admin JS
    wp_add_inline_script('acf-quiz-admin', '
        jQuery(document).ready(function($) {
            // Custom admin functionality
            $(".quiz-export-btn").on("click", function() {
                // Custom export functionality
            });
        });
    ');
}
```
**Parameters:** None
**Timing:** When admin assets are enqueued
**Use Cases:**
- Custom admin styling
- Additional admin functionality
- Enhanced user interface

## 🔧 Filter Hooks

### Configuration Filters

#### `acf_quiz_passing_score` - Modify Passing Threshold
```php
add_filter('acf_quiz_passing_score', 'my_custom_passing_score', 10, 1);

function my_custom_passing_score($default_score) {
    // Different passing scores based on package type
    $current_package = get_query_var('package', 'trial');

    $custom_scores = array(
        'trial' => 18,    // Easier for trial
        'monthly' => 21,  // Standard score
        'yearly' => 25    // Harder for yearly
    );

    return $custom_scores[$current_package] ?? $default_score;
}
```
**Parameters:**
- `$default_score` (int) - Default passing score (21)
**Returns:** Modified passing score
**Use Cases:**
- Dynamic difficulty levels
- Package-specific requirements
- A/B testing different thresholds

#### `acf_quiz_required_fields` - Customize Required Fields
```php
add_filter('acf_quiz_required_fields', 'my_custom_required_fields', 10, 1);

function my_custom_required_fields($default_fields) {
    // Add custom required fields
    $default_fields[] = array(
        'name' => 'custom_field',
        'label' => 'Custom Field',
        'type' => 'text',
        'validation' => 'my_custom_validation'
    );

    // Make phone optional for certain users
    foreach ($default_fields as &$field) {
        if ($field['name'] === 'user_phone') {
            $field['required'] = false;
        }
    }

    return $default_fields;
}
```
**Parameters:**
- `$default_fields` (array) - Default required field configuration
**Returns:** Modified field configuration
**Use Cases:**
- Add custom fields
- Modify validation rules
- Conditional requirements

#### `acf_quiz_questions` - Modify Quiz Questions
```php
add_filter('acf_quiz_questions', 'my_custom_questions', 10, 1);

function my_custom_questions($questions) {
    // Add custom question
    $questions[] = array(
        'question_text' => 'What is your investment experience?',
        'answers' => array(
            array('answer_text' => 'Beginner', 'points' => 1),
            array('answer_text' => 'Intermediate', 'points' => 2),
            array('answer_text' => 'Advanced', 'points' => 3),
            array('answer_text' => 'Expert', 'points' => 4)
        )
    );

    // Modify existing questions
    foreach ($questions as &$question) {
        if (strpos($question['question_text'], 'experience') !== false) {
            // Customize experience-related questions
            $question['question_text'] .= ' (Please be honest)';
        }
    }

    return $questions;
}
```
**Parameters:**
- `$questions` (array) - Default quiz questions
**Returns:** Modified questions array
**Use Cases:**
- Add industry-specific questions
- Customize question wording
- Implement dynamic question sets

### Content Filters

#### `acf_quiz_success_message` - Customize Success Message
```php
add_filter('acf_quiz_success_message', 'my_custom_success_message', 10, 2);

function my_custom_success_message($default_message, $quiz_data) {
    $first_name = $quiz_data['first_name'] ?? 'there';
    $score = $quiz_data['score'] ?? 0;
    $percentage = $quiz_data['score_percentage'] ?? 0;

    $custom_message = "<h2>Congratulations, {$first_name}!</h2>";
    $custom_message .= "<p>You scored {$score}/40 points ({$percentage}%)!</p>";
    $custom_message .= "<p>You've qualified for our premium investment package.</p>";
    $custom_message .= "<p>Proceed to checkout to secure your spot.</p>";

    return $custom_message;
}
```
**Parameters:**
- `$default_message` (string) - Default success message
- `$quiz_data` (array) - Quiz submission data
**Returns:** Customized success message
**Use Cases:**
- Personalized messaging
- Dynamic content based on score
- Branding customization

#### `acf_quiz_failure_message` - Customize Failure Message
```php
add_filter('acf_quiz_failure_message', 'my_custom_failure_message', 10, 2);

function my_custom_failure_message($default_message, $quiz_data) {
    $first_name = $quiz_data['first_name'] ?? 'there';
    $score = $quiz_data['score'] ?? 0;

    $custom_message = "<h2>Thanks for taking our quiz, {$first_name}!</h2>";
    $custom_message .= "<p>You scored {$score}/40 points.</p>";
    $custom_message .= "<p>While you didn't qualify for our premium package this time, ";
    $custom_message .= "we have other options that might be perfect for you.</p>";
    $custom_message .= "<p><a href='/contact'>Contact us</a> to discuss your options.</p>";

    return $custom_message;
}
```
**Parameters:**
- `$default_message` (string) - Default failure message
- `$quiz_data` (array) - Quiz submission data
**Returns:** Customized failure message
**Use Cases:**
- Encourage alternative paths
- Provide helpful next steps
- Maintain positive user experience

#### `acf_quiz_form_html` - Modify Form HTML
```php
add_filter('acf_quiz_form_html', 'my_custom_form_modifications', 10, 2);

function my_custom_form_modifications($form_html, $atts) {
    // Add custom wrapper
    $form_html = '<div class="my-custom-wrapper">' . $form_html . '</div>';

    // Add progress bar
    $progress_html = '<div class="quiz-progress">';
    $progress_html .= '<div class="progress-bar" style="width: 0%"></div>';
    $progress_html .= '</div>';

    // Insert progress bar after form opening
    $form_html = preg_replace(
        '/(<form[^>]*>)/',
        '$1' . $progress_html,
        $form_html
    );

    return $form_html;
}
```
**Parameters:**
- `$form_html` (string) - Complete form HTML
- `$atts` (array) - Shortcode attributes
**Returns:** Modified form HTML
**Use Cases:**
- Add custom wrappers
- Inject progress indicators
- Modify form structure
- Add custom styling containers

### WooCommerce Integration Filters

#### `acf_quiz_package_price` - Modify Package Prices
```php
add_filter('acf_quiz_package_price', 'my_dynamic_pricing', 10, 2);

function my_dynamic_pricing($price, $package_type) {
    // Dynamic pricing based on time of day
    $hour = date('H');
    if ($hour >= 18 && $hour <= 22) { // Evening discount
        $discount = 0.1; // 10% off
        $price = $price * (1 - $discount);
    }

    // Package-specific pricing adjustments
    switch ($package_type) {
        case 'trial':
            $price = $price * 0.8; // 20% off trial
            break;
        case 'yearly':
            $price = $price * 0.85; // 15% off yearly
            break;
    }

    return $price;
}
```
**Parameters:**
- `$price` (float) - Default package price
- `$package_type` (string) - Package type (trial/monthly/yearly)
**Returns:** Modified price
**Use Cases:**
- Dynamic pricing
- Promotional discounts
- Geographic pricing
- Custom pricing rules

#### `acf_quiz_checkout_fields` - Modify Checkout Data
```php
add_filter('acf_quiz_checkout_fields', 'my_custom_checkout_fields', 10, 1);

function my_custom_checkout_fields($checkout_fields) {
    // Add custom fields to checkout
    $checkout_fields['quiz_custom_field'] = array(
        'type' => 'text',
        'label' => 'Custom Quiz Field',
        'required' => true,
        'priority' => 25
    );

    // Modify existing fields
    if (isset($checkout_fields['billing_company'])) {
        $checkout_fields['billing_company']['label'] = 'Organization';
        $checkout_fields['billing_company']['required'] = false;
    }

    return $checkout_fields;
}
```
**Parameters:**
- `$checkout_fields` (array) - WooCommerce checkout fields
**Returns:** Modified checkout fields
**Use Cases:**
- Add custom checkout fields
- Modify field requirements
- Customize field labels
- Implement conditional fields

## 🎨 Frontend Integration Filters

### JavaScript Variable Filters

#### `acf_quiz_js_variables` - Modify JavaScript Variables
```php
add_filter('acf_quiz_js_variables', 'my_custom_js_variables', 10, 1);

function my_custom_js_variables($variables) {
    // Add custom JavaScript variables
    $variables['customSettings'] = array(
        'enableAnalytics' => true,
        'trackingId' => 'GA123456',
        'customMessages' => array(
            'validation_error' => 'Please check your input',
            'success' => 'Thank you for completing the quiz!'
        )
    );

    // Modify existing variables
    $variables['strings']['submit'] = 'Complete Quiz';

    return $variables;
}
```
**Parameters:**
- `$variables` (array) - JavaScript variables for localization
**Returns:** Modified variables array
**Use Cases:**
- Add custom JavaScript configuration
- Modify UI strings
- Enable/disable features
- Add analytics tracking

### CSS Class Filters

#### `acf_quiz_css_classes` - Modify CSS Classes
```php
add_filter('acf_quiz_css_classes', 'my_custom_css_classes', 10, 2);

function my_custom_css_classes($classes, $context) {
    // Add custom CSS classes based on context
    switch ($context) {
        case 'form':
            $classes[] = 'my-custom-form';
            $classes[] = 'rtl-form'; // For RTL languages
            break;

        case 'button':
            $classes[] = 'btn';
            $classes[] = 'btn-primary';
            break;

        case 'step':
            $classes[] = 'step-container';
            if (is_user_logged_in()) {
                $classes[] = 'logged-in-user';
            }
            break;
    }

    return $classes;
}
```
**Parameters:**
- `$classes` (array) - CSS classes array
- `$context` (string) - Context where classes are applied
**Returns:** Modified classes array
**Use Cases:**
- Add theme-specific styling
- Implement responsive design
- Add user-specific styling
- Support different languages/directions

## 🔧 Advanced Customization Hooks

### Database Operation Hooks

#### `acf_quiz_before_db_insert` - Pre-Database Insertion
```php
add_action('acf_quiz_before_db_insert', 'my_pre_insert_processing', 10, 1);

function my_pre_insert_processing($data) {
    // Add custom data before database insertion
    $data['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    $data['custom_field'] = 'additional_data';

    // Hash sensitive data
    if (!empty($data['id_number'])) {
        $data['id_number_hash'] = wp_hash($data['id_number']);
        // Optionally remove original sensitive data
        // unset($data['id_number']);
    }

    // Log data processing
    error_log('Processing quiz submission for: ' . $data['user_email']);

    return $data;
}
```
**Parameters:**
- `$data` (array) - Data to be inserted
**Returns:** Modified data array
**Use Cases:**
- Add tracking information
- Hash sensitive data
- Implement data validation
- Add audit trails

#### `acf_quiz_after_db_insert` - Post-Database Insertion
```php
add_action('acf_quiz_after_db_insert', 'my_post_insert_actions', 10, 2);

function my_post_insert_actions($submission_id, $data) {
    // Actions after successful database insertion
    global $wpdb;

    // Update external systems
    my_external_api_sync($submission_id, $data);

    // Send notifications
    if ($data['passed']) {
        my_send_success_notification($data);
    } else {
        my_send_followup_notification($data);
    }

    // Update statistics
    $stats_table = $wpdb->prefix . 'quiz_statistics';
    $wpdb->query($wpdb->prepare("
        UPDATE {$stats_table}
        SET total_submissions = total_submissions + 1,
            passed_submissions = passed_submissions + %d
        WHERE date = CURDATE()
    ", $data['passed'] ? 1 : 0));

    // Trigger custom workflows
    do_action('my_quiz_submission_processed', $submission_id, $data);
}
```
**Parameters:**
- `$submission_id` (int) - Database ID of inserted record
- `$data` (array) - Inserted data
**Returns:** None
**Use Cases:**
- External API integrations
- Notification systems
- Statistics tracking
- Custom business logic

### Error Handling Hooks

#### `acf_quiz_error_occurred` - Error Handling
```php
add_action('acf_quiz_error_occurred', 'my_error_handling', 10, 3);

function my_error_handling($error_type, $error_message, $context) {
    // Custom error handling and logging
    $log_message = sprintf(
        '[QUIZ ERROR] Type: %s, Message: %s, Context: %s, User: %s, Time: %s',
        $error_type,
        $error_message,
        json_encode($context),
        get_current_user_id(),
        current_time('mysql')
    );

    error_log($log_message);

    // Send alert for critical errors
    if (in_array($error_type, array('db_connection_failed', 'security_breach'))) {
        wp_mail(
            get_option('admin_email'),
            'Critical Quiz Error: ' . $error_type,
            $log_message
        );
    }

    // Store error for analysis
    my_store_error_for_analysis($error_type, $error_message, $context);
}
```
**Parameters:**
- `$error_type` (string) - Type of error occurred
- `$error_message` (string) - Error message
- `$context` (array) - Additional context information
**Use Cases:**
- Custom error logging
- Alert systems
- Error analysis
- User experience improvement

## 📊 Analytics & Tracking Hooks

### Event Tracking Hooks

#### `acf_quiz_step_completed` - Step Completion Tracking
```php
add_action('acf_quiz_step_completed', 'my_step_tracking', 10, 3);

function my_step_tracking($step_number, $user_email, $completion_time) {
    // Track step completion for analytics
    if (function_exists('my_analytics_track')) {
        my_analytics_track('quiz_step_completed', array(
            'step' => $step_number,
            'user_email' => $user_email,
            'time_spent' => $completion_time,
            'timestamp' => time()
        ));
    }

    // Update user progress in external system
    my_update_user_progress($user_email, $step_number);

    // Send progress notifications for long forms
    if ($step_number === 2) {
        $message = "Great progress! You've completed step 2 of 4.";
        my_send_progress_notification($user_email, $message);
    }
}
```
**Parameters:**
- `$step_number` (int) - Completed step number
- `$user_email` (string) - User email address
- `$completion_time` (int) - Time spent on step (seconds)
**Use Cases:**
- Analytics tracking
- Progress notifications
- User engagement monitoring

#### `acf_quiz_conversion_tracked` - Conversion Tracking
```php
add_action('acf_quiz_conversion_tracked', 'my_conversion_tracking', 10, 2);

function my_conversion_tracking($submission_id, $conversion_type) {
    // Track conversions for marketing attribution
    global $wpdb;

    $tracking_data = array(
        'submission_id' => $submission_id,
        'conversion_type' => $conversion_type, // 'passed', 'checkout', 'payment'
        'timestamp' => current_time('mysql'),
        'source' => $_COOKIE['utm_source'] ?? '',
        'campaign' => $_COOKIE['utm_campaign'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR']
    );

    $wpdb->insert(
        $wpdb->prefix . 'quiz_conversions',
        $tracking_data
    );

    // Send to external analytics
    if (function_exists('my_external_analytics')) {
        my_external_analytics('quiz_conversion', $tracking_data);
    }

    // Update conversion rates
    my_update_conversion_rates();
}
```
**Parameters:**
- `$submission_id` (int) - Quiz submission ID
- `$conversion_type` (string) - Type of conversion
**Use Cases:**
- Marketing attribution
- Conversion rate tracking
- Analytics integration

### Performance Monitoring Hooks

#### `acf_quiz_performance_metric` - Performance Tracking
```php
add_action('acf_quiz_performance_metric', 'my_performance_tracking', 10, 2);

function my_performance_tracking($metric_name, $value) {
    // Track performance metrics
    static $metrics = array();

    $metrics[$metric_name] = $value;

    // Log performance data
    if ($metric_name === 'form_submission_time') {
        error_log("Form submission took: {$value} seconds");

        // Alert if too slow
        if ($value > 5) {
            my_send_performance_alert('Slow form submission', $value);
        }
    }

    // Store metrics for analysis
    my_store_performance_metric($metric_name, $value, time());

    // Send to monitoring service
    if (function_exists('my_monitoring_service')) {
        my_monitoring_service($metric_name, $value);
    }
}
```
**Parameters:**
- `$metric_name` (string) - Name of the metric
- `$value` (mixed) - Metric value
**Use Cases:**
- Performance monitoring
- Bottleneck identification
- User experience optimization

## 🚀 Extending the Quiz System

### Creating Custom Question Types

#### Register Custom Question Type
```php
add_filter('acf_quiz_question_types', 'my_custom_question_types', 10, 1);

function my_custom_question_types($types) {
    $types['slider'] = array(
        'label' => 'Slider Question',
        'render_callback' => 'my_render_slider_question',
        'validate_callback' => 'my_validate_slider_question',
        'score_callback' => 'my_score_slider_question'
    );

    return $types;
}

function my_render_slider_question($question, $question_index) {
    $html = '<div class="slider-question">';
    $html .= '<label>' . esc_html($question['question_text']) . '</label>';
    $html .= '<input type="range" name="question_' . $question_index . '" min="1" max="10" value="5">';
    $html .= '<div class="slider-labels">';
    $html .= '<span>Strongly Disagree</span>';
    $html .= '<span>Neutral</span>';
    $html .= '<span>Strongly Agree</span>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}
```
**Use Cases:**
- Custom input types
- Enhanced user experience
- Specialized question formats

### Implementing Custom Scoring Logic

#### Custom Scoring Algorithm
```php
add_filter('acf_quiz_scoring_algorithm', 'my_custom_scoring', 10, 2);

function my_custom_scoring($score, $answers) {
    // Implement custom scoring logic
    $weighted_score = 0;

    foreach ($answers as $question_index => $answer_value) {
        // Apply different weights based on question importance
        $weight = my_get_question_weight($question_index);
        $weighted_score += ($answer_value * $weight);
    }

    // Apply normalization
    $max_possible_score = my_calculate_max_score($answers);
    $normalized_score = ($weighted_score / $max_possible_score) * 40;

    return round($normalized_score);
}

function my_get_question_weight($question_index) {
    // Define question weights (1-3 scale)
    $weights = array(
        0 => 3, // Experience - very important
        1 => 2, // Investment amount - important
        2 => 2, // Time commitment - important
        3 => 1, // Tools knowledge - less important
        4 => 2, // Market reaction - important
        5 => 2, // Investment horizon - important
        6 => 2, // Risk tolerance - important
        7 => 2, // Investment goals - important
        8 => 1, // Volatility tolerance - less important
        9 => 1  // Financial analysis - less important
    );

    return $weights[$question_index] ?? 1;
}
```
**Use Cases:**
- Weighted scoring
- Dynamic difficulty adjustment
- Personalized assessment results

This comprehensive hooks and filters documentation provides all the integration points needed to customize and extend the ACF Quiz System according to specific business requirements.
