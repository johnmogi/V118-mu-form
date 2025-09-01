# ACF Quiz System - PHP Classes & Functions

## 🏗️ Core PHP Architecture

This document provides comprehensive documentation of all PHP classes, methods, and functions in the ACF Quiz System.

## 📁 Class Hierarchy

### Main Plugin Class: `ACF_Quiz_System`

```php
class ACF_Quiz_System {
    // Singleton pattern implementation
    private static $instance = null;

    // Plugin paths and URLs
    private $plugin_url;
    private $plugin_path;

    // Core methods
    public static function get_instance()
    private function __construct()
    private function init_hooks()
    public function remove_css_js_version($src)

    // ACF Integration
    public function add_options_page()
    public function register_fields()
    public function acf_notice()

    // Database Operations
    public function create_submissions_table()

    // WordPress Integration
    public function enqueue_public_assets()
    public function enqueue_admin_assets()
    public function render_quiz_form($atts)
    public function add_submissions_page()

    // AJAX Handlers
    public function handle_step_data()
    public function handle_quiz_submission()
    public function simple_lead_capture()
    public function save_step_data()

    // WooCommerce Integration
    public function populate_checkout_fields()
    public function get_checkout_field_value($value, $input)
    public function handle_custom_cart_item($cart_item_data, $product_id, $variation_id)
    public function set_custom_cart_item_price($cart)
    public function create_quiz_products()
}
```

## 🔧 Method Documentation

### Constructor & Initialization

#### `get_instance()` - Singleton Accessor
```php
public static function get_instance(): ACF_Quiz_System
```
**Purpose**: Returns the singleton instance of the ACF_Quiz_System class
**Returns**: `ACF_Quiz_System` instance
**Usage**: `$quiz_system = ACF_Quiz_System::get_instance();`

#### `__construct()` - Class Constructor
```php
private function __construct()
```
**Purpose**: Initializes the plugin with dependency checks and path setup
**Dependencies**: ACF PRO plugin
**Side Effects**: Registers activation hook, sets up plugin paths
**Throws**: None (graceful degradation with admin notice)

#### `init_hooks()` - WordPress Hook Registration
```php
private function init_hooks(): void
```
**Purpose**: Registers all WordPress hooks and filters
**Hooks Registered**:
- `acf/init` - ACF options page and field registration
- `wp_enqueue_scripts` - Frontend assets
- `admin_enqueue_scripts` - Admin assets
- `wp_ajax_*` - AJAX endpoints
- `woocommerce_*` - WooCommerce integration

### ACF Integration Methods

#### `add_options_page()` - ACF Admin Page
```php
public function add_options_page(): void
```
**Purpose**: Creates ACF options page for quiz settings
**Page Details**:
- Title: "Quiz Settings"
- Menu: "Quiz System"
- Slug: "quiz-settings"
- Capability: `manage_options`

#### `register_fields()` - ACF Field Registration
```php
public function register_fields(): void
```
**Purpose**: Registers all ACF field groups for quiz configuration
**Field Groups**:
1. **Package Prices Tab**
   - Trial price (default: 99₪)
   - Monthly price (default: 199₪)
   - Yearly price (default: 1999₪)

2. **Quiz Settings Tab**
   - Step 1 intro content
   - Legal notices
   - Button text
   - Final declaration text
   - Quiz questions (repeater field)
   - Passing score (default: 21)

3. **Questions Repeater**
   - Question text
   - Answer options (repeater)
   - Point values (1-4 per answer)

### Database Methods

#### `create_submissions_table()` - Database Setup
```php
public function create_submissions_table(): void
```
**Purpose**: Creates the `wp_quiz_submissions` table on plugin activation
**Table Schema**:
- Personal information fields (Steps 1-2)
- Quiz answers and scoring
- Package information
- Completion tracking
- Metadata (timestamps, IP, user agent)

**Indexes Created**:
- Primary key on `id`
- Performance indexes on `passed`, `completed`, `submission_time`, `user_email`

### Frontend Asset Methods

#### `enqueue_public_assets()` - Frontend Assets
```php
public function enqueue_public_assets(): void
```
**Purpose**: Loads CSS and JavaScript files for the frontend quiz
**Assets Loaded**:
- `acf-quiz-public.css` - Main styling
- `quiz-public.js` - Multi-step form logic
- Inline CSS for dynamic styling

**Conditional Loading**: Only loads on pages with quiz shortcode

#### `enqueue_admin_assets()` - Admin Assets
```php
public function enqueue_admin_assets(): void
```
**Purpose**: Loads admin-specific CSS and JavaScript
**Assets Loaded**:
- Admin dashboard styling
- Submissions management scripts

### Shortcode Methods

#### `render_quiz_form($atts)` - Quiz Form Rendering
```php
public function render_quiz_form(array $atts): string
```
**Purpose**: Renders the complete quiz form HTML
**Parameters**:
- `$atts` (array) - Shortcode attributes
**Returns**: HTML string of the quiz form

**Form Structure**:
1. **Step 1**: Personal information
2. **Step 2**: Detailed personal info
3. **Step 3**: Quiz questions (first 5)
4. **Step 4**: Quiz questions (last 5) + declaration

### AJAX Handler Methods

#### `handle_step_data()` - Step Progression
```php
public function handle_step_data(): void
```
**Purpose**: Processes AJAX requests for step data saving
**Security**: Verifies nonce and user capabilities
**Data Processing**:
- Sanitizes all input data
- Updates database record
- Maintains session data
- Returns JSON response

**Parameters Expected**:
- `quiz_nonce` - WordPress nonce
- `current_step` - Current step number (1-4)
- `step_data` - Form data for current step

#### `handle_quiz_submission()` - Final Submission
```php
public function handle_quiz_submission(): void
```
**Purpose**: Processes the complete quiz submission
**Process**:
1. Validates all form data
2. Calculates quiz score
3. Determines pass/fail status
4. Updates database record
5. Redirects to appropriate page

**Scoring Logic**:
- 10 questions × maximum 4 points each = 40 max score
- Passing threshold: 21+ points
- Score percentage calculation

#### `simple_lead_capture()` - Fallback Lead Capture
```php
public function simple_lead_capture(): void
```
**Purpose**: Backup method for lead capture when primary AJAX fails
**Use Case**: Network issues, JavaScript errors, or AJAX conflicts
**Process**:
- Direct PHP processing (no AJAX dependency)
- Stores minimal lead data
- Returns simple success/error response

#### `save_step_data()` - Session Storage
```php
public function save_step_data(): void
```
**Purpose**: Saves step data to WordPress session for persistence
**Use Case**: Maintains form data across page reloads/navigation
**Storage**: Uses `$_SESSION['quiz_step_data']`

### WooCommerce Integration Methods

#### `create_quiz_products()` - Product Creation
```php
public function create_quiz_products(): void
```
**Purpose**: Creates WooCommerce products for quiz packages
**Products Created**:
- Trial Package (99₪)
- Monthly Package (199₪)
- Yearly Package (1999₪)

**Process**:
1. Checks if products already exist
2. Creates missing products
3. Stores product IDs in options
4. Updates product prices from ACF settings

#### `populate_checkout_fields()` - Checkout Pre-fill
```php
public function populate_checkout_fields(): void
```
**Purpose**: Pre-fills WooCommerce checkout with quiz data
**Data Sources**: Session data from quiz completion
**Fields Populated**:
- Billing first name
- Billing last name
- Billing phone
- Billing email

#### `handle_custom_cart_item()` - Cart Customization
```php
public function handle_custom_cart_item(array $cart_item_data, int $product_id, int $variation_id): array
```
**Purpose**: Adds custom data to cart items from quiz
**Custom Data Added**:
- Package type
- Lead information
- Source tracking
- Quiz completion status

#### `set_custom_cart_item_price()` - Price Management
```php
public function set_custom_cart_item_price(WC_Cart $cart): void
```
**Purpose**: Sets correct prices for quiz products in cart
**Price Sources**:
- ACF option values
- Package-specific pricing
- Dynamic price updates

### Utility Methods

#### `remove_css_js_version($src)` - Asset Versioning
```php
public function remove_css_js_version(string $src): string
```
**Purpose**: Controls CSS/JS file versioning for cache management
**Process**:
- Strips version parameters
- Adds file modification time as version
- Helps with cache busting during development

## 🔄 Data Flow Methods

### Step Data Processing

#### Personal Information Validation (Step 1)
```php
// Validates required fields
$required_fields = ['first_name', 'last_name', 'user_phone', 'user_email'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        $errors[] = "Missing required field: $field";
    }
}

// Email format validation
if (!is_email($_POST['user_email'])) {
    $errors[] = "Invalid email format";
}
```

#### Quiz Score Calculation
```php
$score = 0;
$max_score = 40; // 10 questions × 4 points max

foreach ($quiz_answers as $question_id => $answer_value) {
    $score += intval($answer_value);
}

$percentage = round(($score / $max_score) * 100, 2);
$passed = ($score >= 21); // Passing threshold
```

### WooCommerce Integration Flow

#### Checkout Field Mapping
```php
// Map quiz session data to WooCommerce fields
$checkout_fields = [
    'billing_first_name' => $_SESSION['quiz_user_data']['first_name'],
    'billing_last_name' => $_SESSION['quiz_user_data']['last_name'],
    'billing_phone' => $_SESSION['quiz_user_data']['phone'],
    'billing_email' => $_SESSION['quiz_user_data']['email']
];
```

#### Product ID Resolution
```php
// Dynamic product ID lookup
$product_ids = [
    'trial' => get_option('quiz_trial_product_id'),
    'monthly' => get_option('quiz_monthly_product_id'),
    'yearly' => get_option('quiz_yearly_product_id')
];

// Fallback for missing products
if (empty($product_ids[$package_type])) {
    $product_ids[$package_type] = $this->create_or_get_product($package_type);
}
```

## 🛡️ Security Implementation

### Input Sanitization
```php
// All user inputs sanitized
$user_data = [
    'first_name' => sanitize_text_field($_POST['first_name']),
    'last_name' => sanitize_text_field($_POST['last_name']),
    'user_email' => sanitize_email($_POST['user_email']),
    'user_phone' => sanitize_text_field($_POST['user_phone'])
];
```

### Nonce Verification
```php
// All AJAX requests verified
if (!wp_verify_nonce($_POST['quiz_nonce'], 'acf_quiz_nonce')) {
    wp_send_json_error(['message' => 'Security check failed']);
    return;
}
```

### SQL Injection Prevention
```php
// All database queries use prepared statements
$wpdb->insert(
    $table_name,
    $user_data,
    ['%s', '%s', '%s', '%s'] // Data type placeholders
);
```

## 🔧 Error Handling

### AJAX Error Responses
```php
// Structured error handling
if (!empty($errors)) {
    wp_send_json_error([
        'message' => 'Validation failed',
        'errors' => $errors
    ]);
    return;
}

wp_send_json_success([
    'message' => 'Data saved successfully',
    'next_step' => $current_step + 1
]);
```

### Database Error Handling
```php
// Transaction safety
$wpdb->query('START TRANSACTION');

try {
    // Database operations
    $result = $wpdb->insert($table_name, $data);

    if ($result === false) {
        throw new Exception('Database insertion failed');
    }

    $wpdb->query('COMMIT');
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
    error_log('Quiz submission error: ' . $e->getMessage());
}
```

## 📊 Performance Optimization

### Query Optimization
```php
// Indexed queries for performance
$submissions = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$table_name}
    WHERE user_email = %s
    AND completed = 1
    ORDER BY submission_time DESC
    LIMIT 10
", $user_email));
```

### Asset Loading Optimization
```php
// Conditional asset loading
if (has_shortcode($post->post_content, 'acf_quiz')) {
    wp_enqueue_style('acf-quiz-public', $this->plugin_url . 'css/quiz-public.css');
    wp_enqueue_script('acf-quiz-public', $this->plugin_url . 'js/quiz-public.js');
}
```

## 🔗 Integration Points

### WordPress Hooks Used
- `acf/init` - ACF initialization
- `wp_enqueue_scripts` - Frontend assets
- `wp_ajax_*` - AJAX endpoints
- `woocommerce_checkout_*` - Checkout integration
- `woocommerce_add_cart_item_data` - Cart customization

### Custom Hooks Available
```php
// Allow customization of quiz behavior
do_action('acf_quiz_before_form', $atts);
do_action('acf_quiz_after_form', $form_html);
do_action('acf_quiz_before_questions');
do_action('acf_quiz_after_questions');
```

### Filter Hooks
```php
// Customizable quiz parameters
$passing_score = apply_filters('acf_quiz_passing_score', 21);
$required_fields = apply_filters('acf_quiz_required_fields', $default_fields);
$success_message = apply_filters('acf_quiz_success_message', $default_message);
```

This comprehensive documentation covers all PHP classes and methods in the ACF Quiz System, providing the technical details needed for development, maintenance, and customization.
