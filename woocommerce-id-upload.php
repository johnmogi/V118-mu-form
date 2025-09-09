<?php
/**
 * WooCommerce ID Photo Upload
 * Adds ID photo upload field to checkout form
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerce_ID_Upload {
    
    public function __construct() {
        // Add upload field to checkout
        add_action('woocommerce_after_checkout_billing_form', array($this, 'add_id_upload_field'));
        
        // Validate upload on checkout
        add_action('woocommerce_checkout_process', array($this, 'validate_id_upload'));
        
        // Save upload data
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_id_upload_data'));
        
        // Display in admin order details
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_id_upload_in_admin'));
        
        // Handle AJAX file upload
        add_action('wp_ajax_upload_id_photo', array($this, 'handle_id_upload'));
        add_action('wp_ajax_nopriv_upload_id_photo', array($this, 'handle_id_upload'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_upload_scripts'));
        
        // Create uploads directory
        add_action('init', array($this, 'create_uploads_directory'));
    }
    
    /**
     * Create uploads directory for ID photos
     */
    public function create_uploads_directory() {
        $upload_dir = wp_upload_dir();
        $id_photos_dir = $upload_dir['basedir'] . '/id-photos';
        
        if (!file_exists($id_photos_dir)) {
            wp_mkdir_p($id_photos_dir);
            
            // Create .htaccess to protect directory
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            file_put_contents($id_photos_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Enqueue upload scripts
     */
    public function enqueue_upload_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('id-upload-js', plugin_dir_url(__FILE__) . 'js/id-upload.js', array('jquery'), '1.0.0', true);
            wp_enqueue_style('id-upload-css', plugin_dir_url(__FILE__) . 'css/id-upload.css', array(), '1.0.0');
            
            wp_localize_script('id-upload-js', 'id_upload_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('id_upload_nonce'),
                'max_file_size' => '5MB',
                'allowed_types' => 'JPG, JPEG, PNG, PDF'
            ));
        }
    }
    
    /**
     * Add ID upload field to checkout form
     */
    public function add_id_upload_field($checkout) {
        echo '<div id="id_upload_field" class="id-upload-section">';
        echo '<h3>' . __('זיהוי אישי', 'woocommerce') . '</h3>';
        echo '<p class="form-row form-row-wide" id="id_photo_upload_field">';
        echo '<label for="id_photo_upload">' . __('העלאת תמונת תעודת זהות', 'woocommerce') . ' <span class="optional">(אופציונלי)</span></label>';
        echo '<div class="id-upload-container">';
        echo '<input type="file" id="id_photo_upload" name="id_photo_upload" accept="image/*,.pdf">';
        echo '<div class="upload-progress" style="display: none;">';
        echo '<div class="progress-bar"><div class="progress-fill"></div></div>';
        echo '<span class="progress-text">מעלה קובץ...</span>';
        echo '</div>';
        echo '<div class="upload-success" style="display: none;">';
        echo '<span class="success-icon">✓</span>';
        echo '<span class="success-text">הקובץ הועלה בהצלחה</span>';
        echo '</div>';
        echo '<div class="upload-error" style="display: none;">';
        echo '<span class="error-icon">✗</span>';
        echo '<span class="error-text"></span>';
        echo '</div>';
        echo '<input type="hidden" id="uploaded_id_photo" name="uploaded_id_photo" value="">';
        echo '</div>';
        echo '<small class="form-text">העלה תמונה ברורה של תעודת הזהות (JPG, PNG או PDF, עד 5MB)</small>';
        echo '</p>';
        echo '</div>';
        
        // Add CSS for RTL support
        echo '<style>
        .id-upload-section {
            direction: rtl;
            text-align: right;
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .id-upload-container {
            position: relative;
            margin-top: 10px;
        }
        #id_photo_upload {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            background: white;
            cursor: pointer;
        }
        #id_photo_upload:hover {
            border-color: #999;
        }
        .upload-progress, .upload-success, .upload-error {
            margin-top: 10px;
            padding: 10px;
            border-radius: 3px;
        }
        .upload-progress {
            background: #e3f2fd;
            border: 1px solid #2196f3;
        }
        .upload-success {
            background: #e8f5e8;
            border: 1px solid #4caf50;
            color: #2e7d32;
        }
        .upload-error {
            background: #ffebee;
            border: 1px solid #f44336;
            color: #c62828;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin: 5px 0;
        }
        .progress-fill {
            height: 100%;
            background: #2196f3;
            width: 0%;
            transition: width 0.3s ease;
        }
        .success-icon, .error-icon {
            font-weight: bold;
            margin-left: 5px;
        }
        </style>';
    }
    
    /**
     * Validate ID upload on checkout (now optional)
     */
    public function validate_id_upload() {
        // ID upload is now optional - no validation needed
        return true;
    }
    
    /**
     * Save ID upload data to order meta
     */
    public function save_id_upload_data($order_id) {
        if (!empty($_POST['uploaded_id_photo'])) {
            update_post_meta($order_id, '_id_photo_filename', sanitize_text_field($_POST['uploaded_id_photo']));
            update_post_meta($order_id, '_id_photo_uploaded_at', current_time('mysql'));
        }
    }
    
    /**
     * Display ID upload in admin order details
     */
    public function display_id_upload_in_admin($order) {
        $id_photo = get_post_meta($order->get_id(), '_id_photo_filename', true);
        $uploaded_at = get_post_meta($order->get_id(), '_id_photo_uploaded_at', true);
        
        if ($id_photo) {
            echo '<div class="address">';
            echo '<p><strong>' . __('תמונת תעודת זהות:', 'woocommerce') . '</strong></p>';
            echo '<p>';
            echo '<a href="' . admin_url('admin-ajax.php?action=download_id_photo&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce('download_id_' . $order->get_id())) . '" class="button button-secondary">';
            echo __('הורד תמונת תעודת זהות', 'woocommerce');
            echo '</a>';
            echo '</p>';
            if ($uploaded_at) {
                echo '<p><small>' . __('הועלה בתאריך:', 'woocommerce') . ' ' . date('d/m/Y H:i', strtotime($uploaded_at)) . '</small></p>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Handle AJAX file upload
     */
    public function handle_id_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'id_upload_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check if file was uploaded
        if (empty($_FILES['id_photo'])) {
            wp_send_json_error('לא נבחר קובץ');
        }
        
        $file = $_FILES['id_photo'];
        
        // Validate file
        $validation = $this->validate_uploaded_file($file);
        if (!$validation['valid']) {
            wp_send_json_error($validation['message']);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = 'id_' . uniqid() . '_' . time() . '.' . $file_extension;
        
        // Upload directory
        $upload_dir = wp_upload_dir();
        $id_photos_dir = $upload_dir['basedir'] . '/id-photos';
        $file_path = $id_photos_dir . '/' . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_success(array(
                'message' => 'הקובץ הועלה בהצלחה',
                'filename' => $unique_filename
            ));
        } else {
            wp_send_json_error('שגיאה בהעלאת הקובץ');
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_uploaded_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array('valid' => false, 'message' => 'שגיאה בהעלאת הקובץ');
        }
        
        // Check file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $max_size) {
            return array('valid' => false, 'message' => 'הקובץ גדול מדי (מקסימום 5MB)');
        }
        
        // Check file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'application/pdf');
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            return array('valid' => false, 'message' => 'סוג קובץ לא נתמך (רק JPG, PNG או PDF)');
        }
        
        // Additional security check for images
        if (strpos($file_type, 'image/') === 0) {
            $image_info = getimagesize($file['tmp_name']);
            if ($image_info === false) {
                return array('valid' => false, 'message' => 'הקובץ אינו תמונה תקינה');
            }
        }
        
        return array('valid' => true, 'message' => 'הקובץ תקין');
    }
}

// Initialize the class
new WooCommerce_ID_Upload();

// Add download handler for admin
add_action('wp_ajax_download_id_photo', 'handle_id_photo_download');
function handle_id_photo_download() {
    $order_id = intval($_GET['order_id']);
    $nonce = $_GET['nonce'];
    
    // Verify nonce
    if (!wp_verify_nonce($nonce, 'download_id_' . $order_id)) {
        wp_die('Security check failed');
    }
    
    // Check user permissions
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Insufficient permissions');
    }
    
    $filename = get_post_meta($order_id, '_id_photo_filename', true);
    if (!$filename) {
        wp_die('File not found');
    }
    
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/id-photos/' . $filename;
    
    if (!file_exists($file_path)) {
        wp_die('File does not exist');
    }
    
    // Set headers for download
    $file_extension = pathinfo($filename, PATHINFO_EXTENSION);
    $content_type = $file_extension === 'pdf' ? 'application/pdf' : 'image/' . $file_extension;
    
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="id_photo_order_' . $order_id . '.' . $file_extension . '"');
    header('Content-Length: ' . filesize($file_path));
    
    readfile($file_path);
    exit;
}
