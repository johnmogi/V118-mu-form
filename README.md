# ACF Quiz System - Multi-Step Hebrew Quiz with WooCommerce Integration

A comprehensive, production-ready quiz system for WordPress using Advanced Custom Fields PRO. This MU plugin provides a 4-step multi-page quiz form with full Hebrew/RTL support, lead capture, submission tracking, and seamless WooCommerce integration.

## 🎯 Overview

This system was built to handle complex quiz workflows with reliable lead capture and checkout integration. It includes robust error handling, multiple fallback systems, and comprehensive admin management tools.

## ✨ Key Features

- **4-Step Multi-Page Quiz** - Progressive form with validation at each step
- **Hebrew/RTL Support** - Complete right-to-left language compatibility
- **Dual Lead Capture System** - Primary + fallback methods for reliability
- **WooCommerce Integration** - Automatic product creation and checkout redirection
- **Admin Management** - Comprehensive submissions dashboard with bulk operations
- **Package Parameter Detection** - Automatic detection from URL parameters (?trial, ?monthly, ?yearly)
- **Session-Based Data Persistence** - Maintains form data across steps
- **AJAX Security** - Nonce verification and sanitization throughout

## 🔧 Technical Requirements

- WordPress 5.0+
- Advanced Custom Fields PRO
- WooCommerce (for checkout integration)
- PHP 7.4+
- MySQL 5.7+
- jQuery (included with WordPress)

## 📦 Installation

1. **Install Dependencies:**
   - Advanced Custom Fields PRO
   - WooCommerce (if using checkout integration)

2. **Deploy Plugin:**
   ```bash
   # Copy plugin file to mu-plugins directory
   cp acf-calculator-form.php /wp-content/mu-plugins/
   cp -r js/ /wp-content/mu-plugins/js/
   cp -r css/ /wp-content/mu-plugins/css/
   ```

3. **Database Setup:**
   - Plugin automatically creates `wp_quiz_submissions` table on activation
   - No manual database setup required

## 🚀 Usage

### Basic Implementation

```php
// Shortcode usage
[acf_quiz]

// With package parameters in URL
https://yoursite.com/quiz-page/?yearly
https://yoursite.com/quiz-page/?monthly
https://yoursite.com/quiz-page/?trial
```

### URL Parameters

The system automatically detects package types from URL parameters:
- `?trial` - Trial package (99₪)
- `?monthly` - Monthly package (199₪) 
- `?yearly` - Yearly package (1999₪)

### Admin Configuration

1. **Quiz Settings:**
   - Navigate to **Quiz System** → **Quiz System** (ACF Options Page)
   - Configure 10 questions with 4 answers each
   - Set point values (1-4 points per answer)
   - Passing score: 21+ points out of 40

2. **View Submissions:**
   - Navigate to **Quiz System** → **Submissions**
   - Filter by: All, Failed Only, Passed Only
   - Bulk delete functionality available
   - Statistics dashboard included

## 🔥 Critical Implementation Details

### Lead Submission System (Major Challenge Resolved)

**Problem:** Initial lead capture was unreliable due to AJAX/nonce issues.

**Solution:** Implemented dual-capture system with fallback:

```javascript
// Primary method: Direct PHP file
$.ajax({
    url: '/wp-content/capture-lead.php',
    type: 'POST',
    data: leadData,
    success: function(response) {
        console.log('Lead captured via direct method');
    },
    error: function() {
        // Fallback: WordPress AJAX
        $.ajax({
            url: acfQuiz.ajaxUrl,
            type: 'POST',
            data: {
                action: 'simple_lead_capture',
                ...leadData
            }
        });
    }
});
```

**Key Points:**
- Step 1 data is captured immediately when user proceeds to Step 2
- Both methods store to `wp_quiz_submissions` table
- Package parameters are detected from URL and stored
- Session data is maintained for WooCommerce integration

### WooCommerce Checkout Redirection (Major Challenge Resolved)

**Problem:** Cart redirection was failing due to hardcoded product IDs.

**Solution:** Dynamic product creation and ID management:

```php
// Automatic product creation on plugin init
public function create_quiz_products() {
    $packages = ['trial', 'monthly', 'yearly'];
    foreach ($packages as $package) {
        $product_id = $this->create_or_get_product($package);
        update_option("quiz_{$package}_product_id", $product_id);
    }
}

// JavaScript receives dynamic product IDs
wp_localize_script('acf-quiz-public', 'acfQuiz', array(
    'productIds' => array(
        'trial' => get_option('quiz_trial_product_id', ''),
        'monthly' => get_option('quiz_monthly_product_id', ''),
        'yearly' => get_option('quiz_yearly_product_id', '')
    )
));
```

**Key Points:**
- Products are created automatically if missing
- JavaScript uses dynamic product IDs for cart redirection
- Fallback to shop page if product ID not found
- Package type detection from URL parameters

### Final Submission Storage (Challenge Resolved)

**Problem:** Final quiz submissions weren't being stored before redirect.

**Solution:** Added 1-second delay and enhanced AJAX handling:

```javascript
// Store final submission with delay before redirect
this.storeFinalSubmission(allData, totalScore, passed);

setTimeout(() => {
    if (passed) {
        window.location.href = '/checkout/?add-to-cart=' + productId;
    } else {
        window.location.href = '/followup?score=' + totalScore;
    }
}, 1000); // 1-second delay for AJAX completion
```

**Key Points:**
- Final submissions are stored via AJAX before redirect
- 1-second delay ensures AJAX completion
- Both passed and failed quizzes are stored
- Completed flag distinguishes final submissions from leads

## 🗃️ Database Schema

### wp_quiz_submissions Table

```sql
CREATE TABLE wp_quiz_submissions (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_name varchar(255) DEFAULT NULL,
    user_phone varchar(20) DEFAULT NULL,
    user_email varchar(255) DEFAULT NULL,
    package_selected varchar(50) DEFAULT NULL,
    package_price decimal(10,2) DEFAULT 0.00,
    score int(11) DEFAULT 0,
    max_score int(11) DEFAULT 40,
    passed tinyint(1) DEFAULT 0,
    answers longtext DEFAULT NULL,
    current_step int(11) DEFAULT 1,
    completed tinyint(1) DEFAULT 0,
    submission_time datetime DEFAULT CURRENT_TIMESTAMP,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_email (user_email),
    KEY idx_completed (completed),
    KEY idx_passed (passed)
);
```

**Record Types:**
- `completed = 0` - Initial leads (Step 1 data only)
- `completed = 1, passed = 0` - Failed quiz attempts
- `completed = 1, passed = 1` - Successful quiz completions

## 🔐 Security Features

- **Nonce Verification:** All AJAX calls use WordPress nonces
- **Data Sanitization:** All user input is sanitized using WordPress functions
- **SQL Injection Protection:** Prepared statements throughout
- **XSS Prevention:** Output escaping with `esc_html()`, `esc_attr()`
- **Capability Checks:** Admin functions require `manage_options` capability

## 🎨 Frontend Integration

### CSS Classes for Styling

```css
.quiz-form-container { /* Main container */ }
.quiz-step { /* Individual step containers */ }
.quiz-step.active { /* Active step */ }
.quiz-question { /* Question containers */ }
.quiz-answers { /* Answer group containers */ }
.quiz-answer { /* Individual answer containers */ }
.quiz-navigation { /* Navigation button container */ }
.quiz-error { /* Error message styling */ }
```

### JavaScript Events

```javascript
// Custom events fired by the quiz system
$(document).on('quiz:stepChanged', function(e, stepNumber) {
    // Handle step changes
});

$(document).on('quiz:completed', function(e, result) {
    // Handle quiz completion
});
```

## 🛠️ Troubleshooting & Repair Guide

### Critical Issues & Solutions

#### 1. **Lead Capture System Failures**

**Symptoms:**
- No entries in submissions dashboard
- JavaScript console shows AJAX errors
- Debug log shows "Security check failed"

**Diagnosis Steps:**
```bash
# Check debug log for errors
tail -f /wp-content/debug.log | grep "quiz"

# Verify database table exists
mysql> SHOW TABLES LIKE '%quiz_submissions%';

# Check nonce generation
console.log(acfQuiz.nonce); // Should show valid nonce
```

**Solutions:**
```php
// Emergency nonce bypass (temporary debugging only)
// In handle_step_data() method, comment out nonce verification:
/*
if (!wp_verify_nonce($_POST['quiz_nonce'], 'acf_quiz_nonce')) {
    wp_send_json_error(array('message' => 'Security check failed'));
}
*/

// Regenerate nonces by clearing cache
wp_cache_flush();
```

**Fallback Lead Capture:**
If primary system fails, the dual-capture system automatically tries:
1. Direct PHP file: `/wp-content/capture-lead.php`
2. WordPress AJAX: `simple_lead_capture` action

#### 2. **WooCommerce Integration Failures**

**Symptoms:**
- Redirect to empty cart
- "Product not found" errors
- Checkout shows wrong prices

**Emergency Product Creation:**
```php
// Manual product creation (run in functions.php temporarily)
function emergency_create_quiz_products() {
    $packages = array(
        'trial' => array('name' => 'Trial Package', 'price' => 99),
        'monthly' => array('name' => 'Monthly Package', 'price' => 199),
        'yearly' => array('name' => 'Yearly Package', 'price' => 1999)
    );
    
    foreach ($packages as $type => $data) {
        $product = new WC_Product_Simple();
        $product->set_name($data['name']);
        $product->set_regular_price($data['price']);
        $product->set_status('publish');
        $product_id = $product->save();
        update_option("quiz_{$type}_product_id", $product_id);
        error_log("Created product: {$type} = {$product_id}");
    }
}
// Run once: emergency_create_quiz_products();
```

**Cart Redirect Debugging:**
```javascript
// Add to browser console for debugging
console.log('Product IDs:', acfQuiz.productIds);
console.log('Package detected:', packageType);
console.log('Redirect URL:', redirectUrl);
```

#### 3. **Database Connection Issues**

**Symptoms:**
- "Database connection failed" errors
- Submissions not saving despite successful AJAX

**Database Repair Commands:**
```sql
-- Check table structure
DESCRIBE wp_quiz_submissions;

-- Repair corrupted table
REPAIR TABLE wp_quiz_submissions;

-- Recreate table if corrupted
DROP TABLE IF EXISTS wp_quiz_submissions;
-- Then reactivate plugin to recreate
```

**Connection Testing:**
```php
// Test database connection (add to functions.php temporarily)
function test_quiz_db_connection() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_submissions';
    $result = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    error_log("Quiz DB test: " . ($result !== null ? "SUCCESS ($result records)" : "FAILED: " . $wpdb->last_error));
}
add_action('init', 'test_quiz_db_connection');
```

#### 4. **Session Management Problems**

**Symptoms:**
- Form data lost between steps
- WooCommerce checkout fields not populated
- "Session expired" errors

**Session Debugging:**
```php
// Add to handle_step_data() method for debugging
error_log('Session ID: ' . session_id());
error_log('Session data: ' . print_r($_SESSION, true));

// Force session start if needed
if (!session_id()) {
    session_start();
    error_log('Session started manually');
}
```

**Session Cleanup:**
```php
// Clear corrupted sessions (run once)
function cleanup_quiz_sessions() {
    if (session_id()) {
        session_destroy();
    }
    session_start();
    $_SESSION = array();
    error_log('Quiz sessions cleaned');
}
```

### Edge Cases & Advanced Repairs

#### 5. **Multi-Site (Network) Issues**

**Problem:** Plugin not working on multisite installations

**Solution:**
```php
// Network-wide activation
// Move plugin to /wp-content/mu-plugins/ (not /plugins/)
// Update table prefix handling:
global $wpdb;
$table_name = $wpdb->get_blog_prefix() . 'quiz_submissions'; // Instead of $wpdb->prefix
```

#### 6. **Memory Limit Exceeded**

**Symptoms:**
- White screen on form submission
- "Fatal error: Allowed memory size exhausted"

**Solutions:**
```php
// Increase memory limit (wp-config.php)
ini_set('memory_limit', '256M');
define('WP_MEMORY_LIMIT', '256M');

// Optimize large data handling
// In handle_quiz_submission(), limit answer storage:
$answers = array_slice($quiz_data, 0, 20); // Limit to 20 fields max
```

#### 7. **AJAX Timeout Issues**

**Symptoms:**
- Form submissions hang indefinitely
- "Request timeout" errors in console

**Solutions:**
```javascript
// Increase AJAX timeout (add to quiz-public.js)
$.ajaxSetup({
    timeout: 30000 // 30 seconds instead of default
});

// Add retry mechanism
function submitWithRetry(data, retries = 3) {
    return $.ajax({
        url: acfQuiz.ajaxUrl,
        type: 'POST',
        data: data,
        timeout: 15000
    }).fail(function() {
        if (retries > 0) {
            console.log('Retrying submission...');
            return submitWithRetry(data, retries - 1);
        }
    });
}
```

#### 8. **Character Encoding Issues (Hebrew/RTL)**

**Symptoms:**
- Hebrew text appears as question marks
- Database shows garbled characters

**Database Fixes:**
```sql
-- Convert table to proper UTF-8
ALTER TABLE wp_quiz_submissions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Fix specific columns
ALTER TABLE wp_quiz_submissions 
MODIFY user_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**WordPress Config:**
```php
// wp-config.php - ensure proper charset
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');
```

#### 9. **Plugin Conflicts**

**Common Conflicting Plugins:**
- **Caching plugins** (WP Rocket, W3 Total Cache)
- **Security plugins** (Wordfence, Sucuri)
- **Form plugins** (Contact Form 7, Gravity Forms)

**Conflict Resolution:**
```php
// Disable caching for quiz pages
// Add to .htaccess or use plugin settings
<IfModule mod_headers.c>
    <LocationMatch "/join">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
    </LocationMatch>
</IfModule>

// Exclude from security scanning
// Add quiz AJAX actions to security plugin whitelist:
// wp-admin/admin-ajax.php?action=save_step_data
// wp-admin/admin-ajax.php?action=handle_quiz_submission
```

#### 10. **Performance Issues with Large Datasets**

**Symptoms:**
- Admin submissions page loads slowly
- Database queries timeout

**Optimization:**
```sql
-- Add missing indexes
ALTER TABLE wp_quiz_submissions ADD INDEX idx_submission_time (submission_time);
ALTER TABLE wp_quiz_submissions ADD INDEX idx_package_selected (package_selected);
ALTER TABLE wp_quiz_submissions ADD INDEX idx_user_email_completed (user_email, completed);

-- Archive old submissions (run monthly)
CREATE TABLE wp_quiz_submissions_archive AS 
SELECT * FROM wp_quiz_submissions 
WHERE submission_time < DATE_SUB(NOW(), INTERVAL 6 MONTH);

DELETE FROM wp_quiz_submissions 
WHERE submission_time < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

### Emergency Recovery Procedures

#### Complete System Reset

**When everything fails:**
```bash
# 1. Backup current data
mysqldump -u username -p database_name wp_quiz_submissions > quiz_backup.sql

# 2. Deactivate plugin
mv acf-calculator-form.php acf-calculator-form.php.disabled

# 3. Clear all caches
rm -rf /wp-content/cache/*
wp cache flush

# 4. Restore plugin
mv acf-calculator-form.php.disabled acf-calculator-form.php

# 5. Recreate database table
# Plugin will auto-recreate on next page load
```

#### Data Recovery

**Recover lost submissions:**
```sql
-- Check for data in backup tables
SHOW TABLES LIKE '%quiz%';

-- Restore from backup
INSERT INTO wp_quiz_submissions 
SELECT * FROM wp_quiz_submissions_backup 
WHERE id NOT IN (SELECT id FROM wp_quiz_submissions);
```

### Monitoring & Maintenance

#### Health Check Script

```php
// Add to functions.php for regular monitoring
function quiz_system_health_check() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_submissions';
    
    $checks = array();
    
    // Check table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    $checks['table_exists'] = $table_exists;
    
    // Check recent submissions
    $recent_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE submission_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $checks['recent_submissions'] = $recent_count;
    
    // Check WooCommerce products
    $trial_product = get_option('quiz_trial_product_id');
    $checks['wc_products'] = !empty($trial_product);
    
    // Log results
    error_log('Quiz Health Check: ' . json_encode($checks));
    
    return $checks;
}

// Run daily
if (!wp_next_scheduled('quiz_health_check')) {
    wp_schedule_event(time(), 'daily', 'quiz_health_check');
}
add_action('quiz_health_check', 'quiz_system_health_check');
```

### Debug Mode & Logging

**Enhanced Debug Configuration:**
```php
// wp-config.php - comprehensive debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
define('SAVEQUERIES', true);

// Custom quiz logging
function quiz_debug_log($message, $data = null) {
    if (WP_DEBUG) {
        $log_message = '[QUIZ DEBUG] ' . $message;
        if ($data) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }
        error_log($log_message);
    }
}
```

**Log Analysis Commands:**
```bash
# Monitor quiz-specific logs
tail -f /wp-content/debug.log | grep -i quiz

# Count submission attempts
grep "Quiz lead inserted successfully" /wp-content/debug.log | wc -l

# Find error patterns
grep -i "error\|failed\|exception" /wp-content/debug.log | grep -i quiz
```

### Performance Monitoring

**Key Metrics to Track:**
- Lead capture success rate
- Form completion rate (Step 1 to Step 4)
- WooCommerce conversion rate
- Average form completion time
- Database query performance

**Monitoring Queries:**
```sql
-- Conversion funnel analysis
SELECT 
    COUNT(*) as total_leads,
    SUM(completed) as completed_quizzes,
    SUM(passed) as passed_quizzes,
    ROUND(SUM(completed)/COUNT(*)*100, 2) as completion_rate,
    ROUND(SUM(passed)/COUNT(*)*100, 2) as pass_rate
FROM wp_quiz_submissions;

-- Daily performance
SELECT 
    DATE(submission_time) as date,
    COUNT(*) as submissions,
    SUM(completed) as completed,
    AVG(score) as avg_score
FROM wp_quiz_submissions 
WHERE submission_time > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(submission_time)
ORDER BY date DESC;
```

## 📊 Admin Features

### Submissions Dashboard

- **Statistics Overview:** Total, Completed, Leads, Failed, Passed counts
- **Filtering:** View all, failed only, or passed only submissions
- **Bulk Operations:** Select and delete multiple submissions
- **Export Ready:** Data structure supports CSV/Excel export

### Bulk Delete Functionality

```javascript
// Select all functionality
$('#cb-select-all-1').on('change', function() {
    $('input[name="submission_ids[]"]').prop('checked', this.checked);
});

// Bulk delete with confirmation
onclick="return confirm('Are you sure you want to delete the selected submissions?')"
```

## 🔄 Integration Points

### WooCommerce Checkout

```php
// Session data passed to checkout
$_SESSION['quiz_user_data'] = array(
    'first_name' => $first_name,
    'last_name' => $last_name,
    'phone' => $user_phone,
    'email' => $user_email,
    'package_type' => $package_type
);

// Checkout field population
add_action('woocommerce_checkout_process', array($this, 'populate_checkout_fields'));
```

### Email Notifications (Planned)

A comprehensive email notification system is planned with:
- New lead notifications
- Quiz completion alerts  
- Daily/weekly summary reports
- Customizable templates and recipients

## 📝 Development Notes

### Code Structure

- **Main Plugin File:** `acf-calculator-form.php` (1,600+ lines)
- **Frontend JavaScript:** `js/quiz-public.js` (800+ lines)
- **Frontend CSS:** `css/quiz-public.css` (400+ lines)
- **Database Integration:** Custom table with WordPress $wpdb
- **Admin Interface:** WordPress admin pages with ACF integration

### Performance Considerations

- **AJAX Optimization:** Minimal data transfer, efficient queries
- **Database Indexing:** Key fields indexed for fast queries
- **Session Management:** Efficient session handling for multi-step forms
- **Asset Loading:** Scripts/styles loaded only when needed

## 🚀 Production Deployment

### Pre-Deployment Checklist

- [ ] ACF PRO license activated
- [ ] WooCommerce configured and tested
- [ ] Database backup completed
- [ ] Debug logging disabled (`WP_DEBUG_DISPLAY = false`)
- [ ] SSL certificate installed
- [ ] Quiz questions configured in admin
- [ ] Package prices set in ACF options
- [ ] Test complete quiz flow end-to-end

### Monitoring

- Monitor `/wp-content/debug.log` for errors
- Check admin submissions dashboard regularly
- Verify WooCommerce product creation
- Test lead capture functionality weekly

## 📞 Support

For technical support or customization requests, refer to the development team. This system has been thoroughly tested and includes comprehensive error handling and fallback mechanisms for production reliability.

---

**Version:** 1.0.0  
**Last Updated:** August 2025  
**Compatibility:** WordPress 5.0+, ACF PRO, WooCommerce
   - Go to Quiz System → Submissions
   - Filter by pass/fail status
   - View detailed submission data
   - Export submissions as needed

## Hooks & Filters

### Actions
- `acf_quiz_before_form` - Before quiz form
- `acf_quiz_after_form` - After quiz form
- `acf_quiz_before_questions` - Before questions section
- `acf_quiz_after_questions` - After questions section

### Filters
- `acf_quiz_pass_threshold` - Modify the passing score (default: 21)
- `acf_quiz_required_fields` - Modify required form fields
- `acf_quiz_success_message` - Customize the success message
- `acf_quiz_failure_message` - Customize the failure message

## Database Schema

Table: `wp_quiz_submissions`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Auto-increment ID |
| name | VARCHAR(100) | User's full name |
| phone | VARCHAR(50) | User's phone number |
| package | VARCHAR(100) | Selected package |
| price | DECIMAL(10,2) | Package price |
| source | VARCHAR(100) | Traffic source |
| score | INT | Quiz score (10-40) |
| passed | TINYINT(1) | 1 if passed, 0 if failed |
| answers | LONGTEXT | JSON-encoded user answers |
| ip_address | VARCHAR(45) | User's IP address |
| user_agent | TEXT | User's browser info |
| created_at | DATETIME | Submission timestamp |

## Integration

### WooCommerce Checkout
On quiz pass, user data can be passed to WooCommerce checkout:
- Name and phone are pre-filled
- Package/price information is included
- Source tracking is maintained

### Elementor Integration
1. Create a button in Elementor
2. Set the link to: `/quiz/?package=premium&price=199&source=landing`
3. The quiz will open with pre-filled package info

## Styling

The plugin includes responsive RTL-ready CSS. To customize:

```css
.acf-quiz-container {
    /* Your custom styles */
}

.rtl .acf-quiz-question {
    /* RTL-specific styles */
}
```

## Troubleshooting

1. **Questions not saving?**
   - Verify ACF PRO is active
   - Check user capabilities
   - Check browser console for JavaScript errors

2. **Submission not working?**
   - Verify nonce is being generated
   - Check PHP error log
   - Ensure required fields are filled

## License

GPLv2 or later

## Changelog

### 1.0.0
- Initial release with complete quiz functionality
- RTL and Hebrew support
- Admin submission tracking
- WooCommerce integration ready
