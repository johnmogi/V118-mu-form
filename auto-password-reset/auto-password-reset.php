<?php
/**
 * Plugin Name: Auto Password Reset for New Users
 * Description: Automatically sends password reset email when new user accounts are created during checkout
 * Version: 1.0.0
 * Author: Vider Finance
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AutoPasswordReset {
    
    public function __construct() {
        add_action('woocommerce_created_customer', array($this, 'send_password_reset_email'), 10, 3);
        add_action('user_register', array($this, 'send_password_reset_for_registration'), 10, 1);
    }
    
    /**
     * Send password reset email when customer is created during checkout
     * 
     * @param int $customer_id The customer ID
     * @param array $new_customer_data Customer data
     * @param string $password_generated Whether password was generated
     */
    public function send_password_reset_email($customer_id, $new_customer_data, $password_generated) {
        // Only send if this is a new account created during checkout
        if ($password_generated) {
            $this->trigger_password_reset($customer_id);
        }
    }
    
    /**
     * Send password reset email for general user registration
     * 
     * @param int $user_id The user ID
     */
    public function send_password_reset_for_registration($user_id) {
        // Check if this is during checkout process
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        // Only for new registrations, not admin created users
        if (!current_user_can('manage_options')) {
            $this->trigger_password_reset($user_id);
        }
    }
    
    /**
     * Trigger the password reset email
     * 
     * @param int $user_id The user ID
     */
    private function trigger_password_reset($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user || !$user->user_email) {
            return;
        }
        
        // Generate password reset key
        $key = get_password_reset_key($user);
        
        if (is_wp_error($key)) {
            return;
        }
        
        // Send the password reset email
        $this->send_custom_password_reset_email($user, $key);
        
        // Log the action
        error_log("Auto password reset email sent to: " . $user->user_email);
    }
    
    /**
     * Send custom password reset email
     * 
     * @param WP_User $user The user object
     * @param string $key The password reset key
     */
    private function send_custom_password_reset_email($user, $key) {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $site_url = home_url();
        
        // Create reset link
        $reset_url = add_query_arg(
            array(
                'action' => 'rp',
                'key'    => $key,
                'login'  => rawurlencode($user->user_login),
            ),
            wp_lostpassword_url()
        );
        
        // Email subject
        $subject = sprintf(__('[%s] Set Your Password'), $site_name);
        
        // Email message in Hebrew and English
        $message = sprintf(
            "שלום %s,\n\n" .
            "חשבון המשתמש שלך נוצר בהצלחה באתר %s.\n\n" .
            "כדי להגדיר את הסיסמה שלך, אנא לחץ על הקישור הבא:\n" .
            "%s\n\n" .
            "אם לא ביקשת איפוס סיסמה, אנא התעלם מהודעה זו.\n\n" .
            "---\n\n" .
            "Hello %s,\n\n" .
            "Your user account has been successfully created on %s.\n\n" .
            "To set your password, please click the following link:\n" .
            "%s\n\n" .
            "If you did not request a password reset, please ignore this message.\n\n" .
            "תודה,\n" .
            "צוות %s",
            $user->display_name,
            $site_name,
            $reset_url,
            $user->display_name,
            $site_name,
            $reset_url,
            $site_name
        );
        
        // Email headers
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@' . parse_url($site_url, PHP_URL_HOST) . '>'
        );
        
        // Send the email
        wp_mail($user->user_email, $subject, $message, $headers);
    }
}

// Initialize the plugin
new AutoPasswordReset();
