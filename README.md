# ACF Quiz System

A powerful, RTL-ready quiz system for WordPress using Advanced Custom Fields PRO. This MU plugin allows you to create and manage quizzes with full Hebrew support, submission tracking, and WooCommerce integration.

## Features

- **Dynamic Quiz Builder** - Create quizzes with 10 questions and 4 answers each
- **Point-based Scoring** - Each answer can be worth 1-4 points
- **RTL & Hebrew Support** - Fully compatible with right-to-left languages
- **Personal Data Collection** - Collect name, phone, and consent at quiz start
- **Package Integration** - Track quiz sources with package/price parameters
- **Comprehensive Admin** - View and filter all quiz submissions
- **WooCommerce Ready** - Pass user data to checkout on successful completion

## Requirements

- WordPress 5.0+
- Advanced Custom Fields PRO
- PHP 7.4+
- MySQL 5.7+

## Installation

1. Install and activate Advanced Custom Fields PRO
2. Copy `acf-calculator-form.php` to your `wp-content/mu-plugins/` directory
3. The plugin will automatically create the required database table

## Usage

### Shortcode

```php
[acf_quiz package="basic" price="99" source="homepage"]
```

### URL Parameters

Pass these parameters to pre-fill package information:
- `?package=package_name` - The selected package name
- `?price=99` - The package price
- `?source=page_name` - Traffic source tracking

### Admin Interface

1. **Quiz Questions**
   - Navigate to Settings → ACF Quiz System
   - Add/Edit questions and answers
   - Set point values (1-4) for each answer

2. **View Submissions**
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
