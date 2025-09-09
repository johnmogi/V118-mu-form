<?php
/**
 * Direct Signature Test - Bypass all complexity
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add AJAX endpoint for direct signature test
add_action('wp_ajax_test_signature_direct', 'test_signature_direct');
add_action('wp_ajax_nopriv_test_signature_direct', 'test_signature_direct');

function test_signature_direct() {
    global $wpdb;
    
    error_log('Direct signature test called');
    
    // Create table if not exists
    $table_name = $wpdb->prefix . 'signatures';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        submission_id int(11) DEFAULT NULL,
        user_email varchar(255) DEFAULT '',
        signature_data longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45) DEFAULT '',
        user_agent text DEFAULT '',
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $wpdb->query($sql);
    
    // Insert test signature
    $test_signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'submission_id' => 999,
            'user_email' => 'test@direct.com',
            'signature_data' => $test_signature,
            'created_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => 'Direct Test'
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        error_log('Direct signature test failed: ' . $wpdb->last_error);
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    } else {
        $signature_id = $wpdb->insert_id;
        error_log('Direct signature test success: ID ' . $signature_id);
        wp_send_json_success(array(
            'message' => 'Direct signature test successful',
            'signature_id' => $signature_id,
            'table' => $table_name
        ));
    }
}

// Add test button to admin
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-info">
            <p>
                <strong>Signature Test:</strong> 
                <button onclick="testDirectSignature()" class="button">🧪 Test Direct Signature Save</button>
                <span id="test-result" style="margin-left: 10px;"></span>
            </p>
        </div>
        <script>
        function testDirectSignature() {
            document.getElementById("test-result").innerHTML = "Testing...";
            
            fetch("' . admin_url('admin-ajax.php') . '", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=test_signature_direct"
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("test-result").innerHTML = "✅ Success! ID: " + data.data.signature_id;
                } else {
                    document.getElementById("test-result").innerHTML = "❌ Failed: " + data.data;
                }
            })
            .catch(error => {
                document.getElementById("test-result").innerHTML = "❌ Error: " + error;
            });
        }
        </script>';
    }
});
