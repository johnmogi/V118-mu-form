# Auto Password Reset for New Users

## Description
This mu-plugin automatically sends password reset emails when new user accounts are created during WooCommerce checkout or user registration.

## Features
- Automatically triggers password reset email for new accounts created during checkout
- Sends bilingual emails (Hebrew + English)
- Uses WordPress native password reset system
- Integrates with existing `/my-account/lost-password/` functionality
- Logs email sending for debugging

## How it Works
1. When a new customer account is created during WooCommerce checkout
2. The plugin generates a secure password reset key
3. Sends a custom email with reset link to the user
4. User can set their password using the standard WordPress reset flow

## Email Content
The email includes:
- Welcome message in Hebrew and English
- Secure password reset link
- Instructions for setting password
- Professional formatting with site branding

## Technical Details
- Hooks into `woocommerce_created_customer` for checkout registrations
- Hooks into `user_register` for general registrations
- Uses WordPress `get_password_reset_key()` for security
- Leverages `wp_lostpassword_url()` for standard reset flow
- Includes proper error handling and logging

## Installation
This plugin is automatically loaded as a must-use plugin in WordPress.

## Customization
To modify email content or styling, edit the `send_custom_password_reset_email()` method in the main plugin file.
