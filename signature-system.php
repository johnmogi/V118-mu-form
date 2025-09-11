<?php
/**
 * Simple Signature System
 * Standalone signature capture and storage system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Signature_System {
    
    public function __construct() {
        add_action('wp_ajax_save_signature', array($this, 'save_signature'));
        add_action('wp_ajax_nopriv_save_signature', array($this, 'save_signature'));
        add_action('wp_ajax_get_signature', array($this, 'get_signature'));
        add_action('wp_ajax_nopriv_get_signature', array($this, 'get_signature'));
        add_action('admin_menu', array($this, 'add_signature_admin_page'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_signature_scripts'));
        
        // Create signature table immediately
        add_action('init', array($this, 'create_signature_table'));
        
        // Debug logging
        error_log('Signature System initialized - AJAX endpoints registered');
    }
    
    /**
     * Create signatures table
     */
    public function create_signature_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'signatures';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            submission_id int(11) DEFAULT NULL,
            user_email varchar(255) DEFAULT '',
            signature_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT '',
            user_agent text,
            PRIMARY KEY (id),
            KEY submission_id (submission_id),
            KEY user_email (user_email),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log('Signature table created successfully');
    }
    
    /**
     * Check if table exists and create if needed
     */
    public function maybe_create_signature_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'signatures';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $this->create_signature_table();
        }
    }
    
    /**
     * Enqueue signature scripts
     */
    public function enqueue_signature_scripts() {
        wp_enqueue_script('signature-pad', 'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js', array(), '4.0.0', true);
        
        wp_localize_script('signature-pad', 'signature_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('signature_nonce')
        ));
    }
    
    /**
     * Save signature via AJAX
     */
    public function save_signature() {
        error_log('Save signature AJAX called - POST data: ' . print_r($_POST, true));
        
        // Verify nonce - accept both signature_nonce and acf_quiz_nonce
        $nonce_valid = wp_verify_nonce($_POST['nonce'], 'signature_nonce') || 
                      wp_verify_nonce($_POST['nonce'], 'acf_quiz_nonce');
        
        if (!$nonce_valid) {
            error_log('Signature save failed: Security check failed - nonce: ' . $_POST['nonce']);
            wp_send_json_error('Security check failed');
        }
        
        $signature_data = sanitize_textarea_field($_POST['signature_data'] ?? '');
        $submission_id = intval($_POST['submission_id'] ?? 0);
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        
        if (empty($signature_data)) {
            wp_send_json_error('No signature data provided');
        }
        
        // Validate base64 image data
        if (!preg_match('/^data:image\/png;base64,/', $signature_data)) {
            wp_send_json_error('Invalid signature format');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'signatures';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'submission_id' => $submission_id,
                'user_email' => $user_email,
                'signature_data' => $signature_data,
                'created_at' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('Signature save failed: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save signature: ' . $wpdb->last_error);
        }
        
        $signature_id = $wpdb->insert_id;
        error_log('Signature saved successfully with ID: ' . $signature_id);
        
        wp_send_json_success(array(
            'message' => 'Signature saved successfully',
            'signature_id' => $signature_id
        ));
    }
    
    /**
     * Get signature via AJAX
     */
    public function get_signature() {
        $submission_id = intval($_POST['submission_id'] ?? 0);
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        
        if (empty($submission_id) && empty($user_email)) {
            wp_send_json_error('No identifier provided');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'signatures';
        
        if ($submission_id > 0) {
            $signature = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE submission_id = %d ORDER BY created_at DESC LIMIT 1",
                $submission_id
            ));
        } else {
            $signature = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_email = %s ORDER BY created_at DESC LIMIT 1",
                $user_email
            ));
        }
        
        if ($signature) {
            wp_send_json_success(array(
                'signature_data' => $signature->signature_data,
                'created_at' => $signature->created_at
            ));
        } else {
            wp_send_json_error('No signature found');
        }
    }
    
    /**
     * Add admin page for signature management
     */
    public function add_signature_admin_page() {
        add_menu_page(
            'Signatures',
            'Signatures',
            'manage_options',
            'signature-manager',
            array($this, 'render_signature_admin_page'),
            'dashicons-edit-page',
            30
        );
    }
    
    /**
     * Render signature admin page
     */
    public function render_signature_admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && current_user_can('manage_options')) {
            $id = intval($_GET['id']);
            $wpdb->delete($table_name, array('id' => $id), array('%d'));
            echo '<div class="notice notice-success"><p>Signature deleted successfully.</p></div>';
        }
        
        // Get all signatures
        $signatures = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Signature Manager</h1>
            <p>Manage all digital signatures captured from the quiz form.</p>
            
            <?php if (empty($signatures)): ?>
                <div class="notice notice-info">
                    <p>No signatures found. Signatures will appear here when users complete the quiz form.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Submission ID</th>
                            <th>Email</th>
                            <th>Signature</th>
                            <th>Created</th>
                            <th>IP Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($signatures as $signature): ?>
                            <tr>
                                <td><?php echo esc_html($signature->id); ?></td>
                                <td><?php echo esc_html($signature->submission_id ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($signature->user_email ?: 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($signature->signature_data)): ?>
                                        <img src="<?php echo esc_attr($signature->signature_data); ?>" 
                                             style="max-width: 150px; height: auto; border: 1px solid #ccc;" 
                                             alt="Signature">
                                    <?php else: ?>
                                        No data
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($signature->created_at); ?></td>
                                <td><?php echo esc_html($signature->ip_address ?: 'N/A'); ?></td>
                                <td>
                                    <a href="?page=signature-manager&action=view&id=<?php echo $signature->id; ?>" class="button button-small">View</a>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a href="?page=signature-manager&action=delete&id=<?php echo $signature->id; ?>" 
                                           class="button button-small" 
                                           onclick="return confirm('Are you sure?')">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])): ?>
                <?php
                $id = intval($_GET['id']);
                $signature = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
                ?>
                
                <?php if ($signature): ?>
                    <div style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                        <h2>Signature Details - ID: <?php echo esc_html($signature->id); ?></h2>
                        
                        <table class="form-table">
                            <tr><th>Submission ID:</th><td><?php echo esc_html($signature->submission_id ?: 'Not linked'); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo esc_html($signature->user_email ?: 'Not provided'); ?></td></tr>
                            <tr><th>Created:</th><td><?php echo esc_html($signature->created_at); ?></td></tr>
                            <tr><th>IP Address:</th><td><?php echo esc_html($signature->ip_address ?: 'Not recorded'); ?></td></tr>
                            <tr><th>User Agent:</th><td><?php echo esc_html($signature->user_agent ?: 'Not recorded'); ?></td></tr>
                        </table>
                        
                        <h3>Digital Signature</h3>
                        <div style="border: 2px solid #ddd; padding: 20px; background: #f9f9f9; text-align: center;">
                            <?php if (!empty($signature->signature_data)): ?>
                                <img src="<?php echo esc_attr($signature->signature_data); ?>" 
                                     style="max-width: 100%; height: auto; border: 1px solid #ccc;" 
                                     alt="Digital Signature">
                                <div style="margin-top: 15px;">
                                    <button onclick="downloadSignature(<?php echo $signature->id; ?>)" class="button button-primary">
                                        📥 Download Signature
                                    </button>
                                </div>
                            <?php else: ?>
                                <p>No signature data available</p>
                            <?php endif; ?>
                        </div>
                        
                        <p><a href="?page=signature-manager" class="button">← Back to List</a></p>
                    </div>
                    
                    <script>
                    function downloadSignature(signatureId) {
                        const img = document.querySelector('img[alt="Digital Signature"]');
                        
                        if (img && img.src && img.src.startsWith('data:image/png;base64,')) {
                            const filename = `signature_${signatureId}_${new Date().toISOString().split('T')[0]}.png`;
                            
                            console.log('Original image src length:', img.src.length);
                            console.log('Base64 preview:', img.src.substring(0, 100));
                            
                            // Method 1: Canvas approach (more reliable for complex images)
                            const canvas = document.createElement('canvas');
                            const ctx = canvas.getContext('2d');
                            
                            const tempImg = new Image();
                            tempImg.crossOrigin = 'anonymous';
                            
                            tempImg.onload = function() {
                                console.log('Image loaded, dimensions:', tempImg.width, 'x', tempImg.height);
                                
                                canvas.width = tempImg.width || 400;
                                canvas.height = tempImg.height || 200;
                                
                                // Clear canvas with white background
                                ctx.fillStyle = 'white';
                                ctx.fillRect(0, 0, canvas.width, canvas.height);
                                
                                // Draw the signature
                                ctx.drawImage(tempImg, 0, 0);
                                
                                // Convert to blob
                                canvas.toBlob(function(blob) {
                                    console.log('Blob created, size:', blob.size, 'bytes');
                                    
                                    if (blob.size > 0) {
                                        const url = URL.createObjectURL(blob);
                                        const link = document.createElement('a');
                                        link.download = filename;
                                        link.href = url;
                                        link.style.display = 'none';
                                        
                                        document.body.appendChild(link);
                                        link.click();
                                        document.body.removeChild(link);
                                        
                                        setTimeout(() => URL.revokeObjectURL(url), 1000);
                                        console.log('✅ Canvas method: Signature downloaded as:', filename);
                                    } else {
                                        console.error('❌ Blob is empty');
                                        alert('Error: Generated image file is empty');
                                    }
                                }, 'image/png', 1.0);
                            };
                            
                            tempImg.onerror = function() {
                                console.error('❌ Failed to load image for canvas conversion');
                                
                                // Fallback: Direct base64 conversion
                                try {
                                    const base64Data = img.src.split(',')[1];
                                    console.log('Fallback: Base64 data length:', base64Data.length);
                                    
                                    const byteCharacters = atob(base64Data);
                                    const byteArray = new Uint8Array(byteCharacters.length);
                                    
                                    for (let i = 0; i < byteCharacters.length; i++) {
                                        byteArray[i] = byteCharacters.charCodeAt(i);
                                    }
                                    
                                    const blob = new Blob([byteArray], { type: 'image/png' });
                                    console.log('Fallback blob size:', blob.size);
                                    
                                    const url = URL.createObjectURL(blob);
                                    const link = document.createElement('a');
                                    link.download = filename;
                                    link.href = url;
                                    link.style.display = 'none';
                                    
                                    document.body.appendChild(link);
                                    link.click();
                                    document.body.removeChild(link);
                                    
                                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                                    console.log('✅ Fallback method: Signature downloaded as:', filename);
                                } catch (e) {
                                    console.error('❌ Fallback conversion failed:', e);
                                    alert('Error: Could not convert signature for download');
                                }
                            };
                            
                            tempImg.src = img.src;
                            
                        } else {
                            alert('No signature image found to download');
                        }
                    }
                    </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
}

// Initialize the signature system
new Simple_Signature_System();
