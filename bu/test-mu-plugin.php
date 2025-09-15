<?php
/**
 * Plugin Name: Test MU Plugin
 * Description: Simple test to verify MU plugins are loading
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add a simple admin menu to test if MU plugins work
add_action('admin_menu', function() {
    add_menu_page(
        'Test MU Plugin',
        'Test MU Plugin',
        'read',
        'test-mu-plugin',
        function() {
            echo '<div class="wrap">';
            echo '<h1>Test MU Plugin Works!</h1>';
            echo '<p>This confirms that MU plugins are loading correctly.</p>';
            echo '<p>Current user capabilities:</p>';
            echo '<ul>';
            $user = wp_get_current_user();
            foreach ($user->allcaps as $cap => $has_cap) {
                if ($has_cap) {
                    echo '<li>' . esc_html($cap) . '</li>';
                }
            }
            echo '</ul>';
            echo '</div>';
        },
        'dashicons-admin-tools',
        30
    );
});
