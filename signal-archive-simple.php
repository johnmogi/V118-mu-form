<?php
/**
 * Plugin Name: Signal Archive Simple
 * Description: Simple signal archive handler
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook into ACF post type registration
add_action('acf/init', 'enable_signal_archive', 20);

function enable_signal_archive() {
    global $wp_post_types;
    
    if (isset($wp_post_types['signal'])) {
        $wp_post_types['signal']->has_archive = true;
        $wp_post_types['signal']->rewrite = array(
            'slug' => 'signal',
            'with_front' => false
        );
        $wp_post_types['signal']->publicly_queryable = true;
    }
}

// Add rewrite rules
add_action('init', 'add_signal_rewrite_rules', 30);

function add_signal_rewrite_rules() {
    add_rewrite_rule(
        '^signal/?$',
        'index.php?post_type=signal',
        'top'
    );
    
    add_rewrite_rule(
        '^signal/page/([0-9]+)/?$',
        'index.php?post_type=signal&paged=$matches[1]',
        'top'
    );
}

// Load archive template
add_filter('template_include', 'signal_archive_template');

function signal_archive_template($template) {
    if (is_post_type_archive('signal')) {
        $plugin_template = __DIR__ . '/signal-archive/templates/archive-signal.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}

// Enqueue styles
add_action('wp_enqueue_scripts', 'signal_archive_styles');

function signal_archive_styles() {
    if (is_post_type_archive('signal')) {
        wp_enqueue_style(
            'signal-archive-styles',
            plugins_url('signal-archive/assets/css/signal-archive.css', __FILE__),
            [],
            '1.0.0'
        );
    }
}

// Force flush rewrite rules once
add_action('wp_loaded', 'maybe_flush_signal_rewrite_rules');

function maybe_flush_signal_rewrite_rules() {
    if (!get_option('signal_archive_rewrite_flushed')) {
        flush_rewrite_rules();
        update_option('signal_archive_rewrite_flushed', true);
    }
}
