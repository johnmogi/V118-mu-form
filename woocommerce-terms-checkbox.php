<?php
/**
 * WooCommerce Terms and Conditions Checkbox
 * Adds terms acceptance checkbox to checkout form
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerce_Terms_Checkbox {
    
    public function __construct() {
        // Add terms checkbox to checkout
        add_action('woocommerce_checkout_after_customer_details', array($this, 'add_terms_checkbox'));
        
        // Validate terms acceptance
        add_action('woocommerce_checkout_process', array($this, 'validate_terms_acceptance'));
        
        // Save terms acceptance data
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terms_acceptance'));
        
        // Display in admin order details
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_terms_in_admin'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_terms_scripts'));
    }
    
    /**
     * Enqueue terms scripts
     */
    public function enqueue_terms_scripts() {
        if (is_checkout()) {
            wp_enqueue_style('terms-checkbox-css', plugin_dir_url(__FILE__) . 'css/terms-checkbox.css', array(), '1.0.0');
        }
    }
    
    /**
     * Add terms checkbox to checkout form
     */
    public function add_terms_checkbox() {
        echo '<div id="terms_acceptance_field" class="terms-acceptance-section">';
        echo '<h3>' . __('תנאי השימוש', 'woocommerce') . '</h3>';
        echo '<p class="form-row form-row-wide validate-required" id="terms_acceptance_field_wrapper">';
        echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
        echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="terms_acceptance" id="terms_acceptance" required>';
        echo '<span class="woocommerce-form__label-text">';
        echo __('אני מאשר/ת את ', 'woocommerce');
        echo '<a href="/terms-and-conditions" target="_blank" class="terms-link">' . __('תנאי השימוש', 'woocommerce') . '</a>';
        echo __(' ו', 'woocommerce');
        echo '<a href="/privacy-policy" target="_blank" class="privacy-link">' . __('מדיניות הפרטיות', 'woocommerce') . '</a>';
        echo ' <abbr class="required" title="נדרש">*</abbr>';
        echo '</span>';
        echo '</label>';
        echo '</p>';
        echo '</div>';
        
        // Add CSS for RTL support and styling
        echo '<style>
        .terms-acceptance-section {
            direction: rtl;
            text-align: right;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
        }
        .terms-acceptance-section.woocommerce-invalid {
            border-color: #e2401c;
            background: #fff5f5;
        }
        .terms-acceptance-section h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }
        .terms-acceptance-section .checkbox {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
            line-height: 1.5;
        }
        .terms-acceptance-section input[type="checkbox"] {
            margin: 0 10px 0 0;
            flex-shrink: 0;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .terms-acceptance-section .woocommerce-form__label-text {
            font-size: 14px;
            color: #333;
        }
        .terms-acceptance-section .terms-link,
        .terms-acceptance-section .privacy-link {
            color: #007cba;
            text-decoration: underline;
            font-weight: 500;
        }
        .terms-acceptance-section .terms-link:hover,
        .terms-acceptance-section .privacy-link:hover {
            color: #005a87;
        }
        .terms-acceptance-section .required {
            color: #e2401c;
        }
        @media (max-width: 768px) {
            .terms-acceptance-section {
                margin: 15px 0;
                padding: 15px;
            }
            .terms-acceptance-section h3 {
                font-size: 16px;
            }
            .terms-acceptance-section .woocommerce-form__label-text {
                font-size: 13px;
            }
        }
        </style>';
    }
    
    /**
     * Validate terms acceptance on checkout
     */
    public function validate_terms_acceptance() {
        if (empty($_POST['terms_acceptance'])) {
            wc_add_notice(__('אנא אשר את תנאי השימוש ומדיניות הפרטיות.', 'woocommerce'), 'error');
        }
    }
    
    /**
     * Save terms acceptance data to order meta
     */
    public function save_terms_acceptance($order_id) {
        if (!empty($_POST['terms_acceptance'])) {
            update_post_meta($order_id, '_terms_accepted', 'yes');
            update_post_meta($order_id, '_terms_accepted_at', current_time('mysql'));
            update_post_meta($order_id, '_terms_accepted_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        }
    }
    
    /**
     * Display terms acceptance in admin order details
     */
    public function display_terms_in_admin($order) {
        $terms_accepted = get_post_meta($order->get_id(), '_terms_accepted', true);
        $accepted_at = get_post_meta($order->get_id(), '_terms_accepted_at', true);
        $accepted_ip = get_post_meta($order->get_id(), '_terms_accepted_ip', true);
        
        if ($terms_accepted === 'yes') {
            echo '<div class="address">';
            echo '<p><strong>' . __('תנאי השימוש:', 'woocommerce') . '</strong></p>';
            echo '<p style="color: #46b450;">';
            echo '✓ ' . __('אושרו על ידי הלקוח', 'woocommerce');
            echo '</p>';
            if ($accepted_at) {
                echo '<p><small>' . __('תאריך אישור:', 'woocommerce') . ' ' . date('d/m/Y H:i', strtotime($accepted_at)) . '</small></p>';
            }
            if ($accepted_ip) {
                echo '<p><small>' . __('כתובת IP:', 'woocommerce') . ' ' . $accepted_ip . '</small></p>';
            }
            echo '</div>';
        }
    }
}

// Initialize the class
new WooCommerce_Terms_Checkbox();
