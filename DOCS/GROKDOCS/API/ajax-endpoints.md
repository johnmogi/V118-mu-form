# ACF Quiz System - AJAX API Endpoints

## 🌐 AJAX API Architecture

This document provides comprehensive documentation of all AJAX endpoints, WordPress hooks, and integration points in the ACF Quiz System.

## 📡 AJAX Endpoints

### Core Quiz Endpoints

#### `wp_ajax_save_step_data` - Step Data Storage
**Purpose**: Saves form data for each step during quiz progression
**Endpoint**: `/wp-admin/admin-ajax.php?action=save_step_data`
**Method**: POST
**Authentication**: WordPress nonce required

**Request Parameters:**
```javascript
{
    action: 'save_step_data',
    quiz_nonce: 'generated_nonce_string',
    current_step: 1, // 1-4
    step_data: {
        first_name: 'John',
        last_name: 'Doe',
        user_phone: '+972501234567',
        user_email: 'john@example.com'
        // ... step-specific fields
    }
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "message": "Step data saved successfully",
        "step": 1,
        "next_step": 2
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "data": {
        "message": "Security check failed",
        "code": "nonce_invalid"
    }
}
```

**Processing Logic:**
1. Verify WordPress nonce
2. Sanitize all input data
3. Update database record
4. Maintain session data
5. Return success/error response

**Error Codes:**
- `nonce_invalid`: Security verification failed
- `invalid_step`: Step number out of range
- `db_error`: Database update failed
- `validation_failed`: Input validation failed

#### `wp_ajax_handle_quiz_submission` - Final Submission
**Purpose**: Processes complete quiz submission and scoring
**Endpoint**: `/wp-admin/admin-ajax.php?action=handle_quiz_submission`
**Method**: POST
**Authentication**: WordPress nonce required

**Request Parameters:**
```javascript
{
    action: 'handle_quiz_submission',
    quiz_nonce: 'generated_nonce_string',
    quiz_data: {
        // Complete form data from all steps
        first_name: 'John',
        last_name: 'Doe',
        // ... all form fields
        answers: {
            question_0: '1', // Answer values (1-4)
            question_1: '2',
            // ... all 10 questions
        }
    }
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "passed": true,
        "score": 32,
        "max_score": 40,
        "percentage": 80,
        "redirect_url": "https://example.com/checkout/?add-to-cart=123"
    }
}
```

**Response (Error):**
```json
{
    "success": false,
    "data": {
        "message": "Quiz submission failed",
        "code": "validation_error",
        "errors": ["Email format invalid", "Missing required field"]
    }
}
```

**Scoring Algorithm:**
```javascript
function calculateScore(answers) {
    let totalScore = 0;
    const maxScore = 40; // 10 questions × 4 points max

    for (let i = 0; i < 10; i++) {
        const questionKey = 'question_' + i;
        if (answers[questionKey]) {
            totalScore += parseInt(answers[questionKey]);
        }
    }

    const percentage = Math.round((totalScore / maxScore) * 100);
    const passed = totalScore >= 21; // 21+ points required

    return { totalScore, percentage, passed };
}
```

**Processing Logic:**
1. Validate complete form data
2. Calculate quiz score (21+ points = pass)
3. Store submission in database
4. Trigger WooCommerce integration
5. Return results with redirect URL

### Fallback Endpoints

#### `wp_ajax_simple_lead_capture` - Backup Lead Capture
**Purpose**: Fallback method for lead capture when primary AJAX fails
**Endpoint**: `/wp-admin/admin-ajax.php?action=simple_lead_capture`
**Method**: POST
**Authentication**: WordPress nonce required

**Request Parameters:**
```javascript
{
    action: 'simple_lead_capture',
    first_name: 'John',
    last_name: 'Doe',
    user_phone: '+972501234567',
    user_email: 'john@example.com',
    package_param: 'trial'
}
```

**Response (Success):**
```json
{
    "success": true,
    "data": {
        "message": "Lead captured successfully",
        "lead_id": 123
    }
}
```

**Use Cases:**
- Primary AJAX endpoint fails
- Network connectivity issues
- JavaScript errors blocking requests
- Plugin conflicts interfering with AJAX

### Legacy Endpoints

#### `wp_ajax_submit_quiz` - Legacy Submission
**Purpose**: Legacy quiz submission endpoint (maintained for compatibility)
**Endpoint**: `/wp-admin/admin-ajax.php?action=submit_quiz`
**Method**: POST
**Authentication**: WordPress nonce required

**Status**: Deprecated but functional
**Recommendation**: Use `handle_quiz_submission` for new implementations

## 🔧 WordPress Hooks & Filters

### Action Hooks

#### `acf_quiz_before_form` - Pre-Form Rendering
```php
do_action('acf_quiz_before_form', $atts);
```
**Parameters**: `$atts` (array) - Shortcode attributes
**Timing**: Before quiz form HTML is rendered
**Use Cases**: Add custom content, modify form parameters

#### `acf_quiz_after_form` - Post-Form Rendering
```php
do_action('acf_quiz_after_form', $form_html);
```
**Parameters**: `$form_html` (string) - Complete form HTML
**Timing**: After quiz form HTML is generated
**Use Cases**: Add tracking scripts, modify output

#### `acf_quiz_before_questions` - Pre-Questions Display
```php
do_action('acf_quiz_before_questions');
```
**Parameters**: None
**Timing**: Before question blocks are rendered
**Use Cases**: Add custom instructions, modify question display

#### `acf_quiz_after_questions` - Post-Questions Display
```php
do_action('acf_quiz_after_questions');
```
**Parameters**: None
**Timing**: After question blocks are rendered
**Use Cases**: Add custom content after questions

### Filter Hooks

#### `acf_quiz_passing_score` - Modify Passing Threshold
```php
$passing_score = apply_filters('acf_quiz_passing_score', 21);
```
**Parameters**:
- `$passing_score` (int) - Default passing score (21)
**Returns**: Modified passing score
**Use Cases**: Adjust difficulty level, customize scoring

#### `acf_quiz_required_fields` - Customize Required Fields
```php
$required_fields = apply_filters('acf_quiz_required_fields', $default_fields);
```
**Parameters**:
- `$required_fields` (array) - Default required field configuration
**Returns**: Modified required fields array
**Use Cases**: Add/remove required fields, modify validation rules

#### `acf_quiz_success_message` - Customize Success Message
```php
$success_message = apply_filters('acf_quiz_success_message', $default_message);
```
**Parameters**:
- `$success_message` (string) - Default success message
**Returns**: Modified success message
**Use Cases**: Localization, branding customization

#### `acf_quiz_failure_message` - Customize Failure Message
```php
$failure_message = apply_filters('acf_quiz_failure_message', $default_message);
```
**Parameters**:
- `$failure_message` (string) - Default failure message
**Returns**: Modified failure message
**Use Cases**: Encourage retakes, provide alternative paths

## 🎨 Frontend Integration Hooks

### JavaScript Events

#### `quiz:stepChanged` - Step Navigation Event
```javascript
$(document).on('quiz:stepChanged', function(e, stepNumber) {
    console.log('Quiz moved to step:', stepNumber);
    // Custom tracking, analytics, etc.
});
```
**Parameters**:
- `stepNumber` (int) - New step number (1-4)
**Timing**: After step transition completes
**Use Cases**: Analytics tracking, custom UI updates

#### `quiz:completed` - Quiz Completion Event
```javascript
$(document).on('quiz:completed', function(e, result) {
    console.log('Quiz completed:', result);
    // result = { passed: true/false, score: 32, percentage: 80 }
});
```
**Parameters**:
- `result` (object) - Quiz completion results
**Timing**: After final submission processing
**Use Cases**: Conversion tracking, custom success/failure handling

### CSS Customization Hooks

#### Form Container Classes
```css
.acf-quiz-container { /* Main container */ }
.quiz-form { /* Form wrapper */ }
.form-step { /* Individual steps */ }
.form-step.active { /* Active step */ }
```

#### Navigation Elements
```css
.form-navigation { /* Navigation container */ }
.nav-btn { /* Navigation buttons */ }
.next-btn { /* Next step button */ }
.prev-btn { /* Previous step button */ }
.submit-btn { /* Submit button */ }
```

## 🔗 WooCommerce Integration API

### Session Data Transfer
```php
// Quiz data passed to WooCommerce checkout
$_SESSION['quiz_user_data'] = array(
    'first_name' => 'John',
    'last_name' => 'Doe',
    'user_phone' => '+972501234567',
    'user_email' => 'john@example.com',
    'package_type' => 'trial'
);
```

### Checkout Field Population
```php
// Hook into WooCommerce checkout
add_filter('woocommerce_checkout_get_value', function($value, $input) {
    $quiz_data = $_SESSION['quiz_user_data'] ?? array();

    switch($input) {
        case 'billing_first_name':
            return $quiz_data['first_name'] ?? $value;
        case 'billing_last_name':
            return $quiz_data['last_name'] ?? $value;
        case 'billing_email':
            return $quiz_data['user_email'] ?? $value;
        default:
            return $value;
    }
}, 10, 2);
```

### Product Management
```php
// Dynamic product creation
$product_id = $this->create_or_get_product($package_type);

// Cart population
WC()->cart->add_to_cart($product_id, 1);

// Price customization
add_action('woocommerce_before_calculate_totals', function($cart) {
    foreach($cart->get_cart() as $cart_item) {
        if (isset($cart_item['_quiz_package'])) {
            // Apply custom pricing logic
        }
    }
});
```

## 📊 Admin API Endpoints

### Submissions Management
```php
// Bulk operations
add_action('wp_ajax_bulk_delete_submissions', function() {
    // Handle bulk delete requests
    $submission_ids = $_POST['submission_ids'] ?? array();

    foreach($submission_ids as $id) {
        // Delete submission logic
    }

    wp_send_json_success(array(
        'message' => 'Submissions deleted successfully'
    ));
});
```

### Statistics API
```php
// Get quiz statistics
add_action('wp_ajax_get_quiz_stats', function() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_submissions';

    $stats = $wpdb->get_row("
        SELECT
            COUNT(*) as total_submissions,
            SUM(CASE WHEN completed = 1 THEN 1 ELSE 0 END) as completed_quizzes,
            SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_quizzes,
            ROUND(AVG(CASE WHEN completed = 1 THEN score END), 1) as avg_score
        FROM {$table_name}
    ");

    wp_send_json_success($stats);
});
```

## 🔐 Security Implementation

### Nonce Generation & Verification
```php
// Generate nonce for frontend
wp_localize_script('acf-quiz-public', 'acfQuiz', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('acf_quiz_nonce')
));

// Verify nonce in AJAX handlers
if (!wp_verify_nonce($_POST['quiz_nonce'], 'acf_quiz_nonce')) {
    wp_send_json_error(array('message' => 'Security check failed'));
    return;
}
```

### Data Sanitization
```php
// Sanitize all user inputs
$user_data = array(
    'first_name' => sanitize_text_field($_POST['first_name']),
    'last_name' => sanitize_text_field($_POST['last_name']),
    'user_email' => sanitize_email($_POST['user_email']),
    'user_phone' => sanitize_text_field($_POST['user_phone'])
);
```

### SQL Injection Prevention
```php
// Use prepared statements
$submission = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$table_name}
    WHERE user_email = %s AND completed = 1
    ORDER BY submission_time DESC
    LIMIT 1
", $user_email));
```

## 🧪 Testing API Endpoints

### Manual Testing
```bash
# Test AJAX endpoint manually
curl -X POST https://example.com/wp-admin/admin-ajax.php \
  -d "action=save_step_data" \
  -d "quiz_nonce=your_nonce_here" \
  -d "current_step=1" \
  -d "step_data[first_name]=John" \
  -d "step_data[last_name]=Doe"
```

### JavaScript Testing
```javascript
// Test AJAX endpoint from browser console
fetch('/wp-admin/admin-ajax.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'action=save_step_data&quiz_nonce=' + acfQuiz.nonce + '&current_step=1'
})
.then(response => response.json())
.then(data => console.log('Response:', data));
```

### Integration Testing
```php
// Test WooCommerce integration
function test_quiz_checkout_flow() {
    // Simulate quiz completion
    $_SESSION['quiz_user_data'] = array(
        'first_name' => 'Test',
        'last_name' => 'User',
        'user_email' => 'test@example.com',
        'package_type' => 'trial'
    );

    // Test product creation
    $product_id = get_option('quiz_trial_product_id');
    assert($product_id > 0, 'Trial product should exist');

    // Test cart addition
    WC()->cart->add_to_cart($product_id, 1);
    assert(WC()->cart->get_cart_contents_count() === 1, 'Product should be in cart');

    echo "All integration tests passed!";
}
```

## 📈 Monitoring & Analytics

### API Usage Tracking
```php
// Track AJAX endpoint usage
add_action('wp_ajax_save_step_data', function() {
    // Log API usage
    error_log('AJAX Call: save_step_data - ' . date('Y-m-d H:i:s'));
}, 1);

add_action('wp_ajax_handle_quiz_submission', function() {
    // Log submission
    error_log('AJAX Call: handle_quiz_submission - ' . date('Y-m-d H:i:s'));
}, 1);
```

### Performance Monitoring
```php
// Track API response times
add_action('wp_ajax_save_step_data', function() {
    $start_time = microtime(true);
    // Store in global for later access
    $GLOBALS['api_start_time'] = $start_time;
}, 1);

add_action('wp_ajax_save_step_data', function() {
    if (isset($GLOBALS['api_start_time'])) {
        $response_time = microtime(true) - $GLOBALS['api_start_time'];
        error_log("API Response Time (save_step_data): {$response_time}s");
    }
}, PHP_INT_MAX);
```

## 🔄 Version Compatibility

### WordPress Version Support
- **Minimum**: WordPress 5.0+
- **Recommended**: WordPress 5.8+
- **Tested up to**: WordPress 6.4+

### ACF Version Requirements
- **Required**: Advanced Custom Fields PRO
- **Minimum Version**: ACF PRO 5.8+
- **Recommended**: Latest ACF PRO version

### WooCommerce Compatibility
- **Required**: WooCommerce plugin
- **Minimum Version**: WooCommerce 5.0+
- **Recommended**: WooCommerce 7.0+

### PHP Version Support
- **Minimum**: PHP 7.4+
- **Recommended**: PHP 8.0+
- **Maximum Tested**: PHP 8.2+

This comprehensive API documentation provides all the technical details needed to integrate with, extend, and maintain the ACF Quiz System.
