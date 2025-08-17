<?php

namespace Vider\SignalArchive;

/**
 * Signal Archive Manager
 * 
 * Handles signal archive functionality with proper routing and display
 */
class SignalArchiveManager
{
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct()
    {
        $this->initHooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks()
    {
        // Enable archive for signal post type
        add_action('init', [$this, 'enableSignalArchive'], 20);
        
        // Add rewrite rules for signal archive
        add_action('init', [$this, 'addRewriteRules']);
        
        // Handle signal archive template
        add_filter('template_include', [$this, 'loadArchiveTemplate']);
        
        // Enqueue styles for signal archive
        add_action('wp_enqueue_scripts', [$this, 'enqueueArchiveStyles']);
        
        // Flush rewrite rules on activation
        register_activation_hook(__FILE__, [$this, 'flushRewriteRules']);
    }
    
    /**
     * Enable archive for signal post type
     */
    public function enableSignalArchive()
    {
        // Get the signal post type object
        global $wp_post_types;
        
        if (isset($wp_post_types['signal'])) {
            // Enable archive
            $wp_post_types['signal']->has_archive = true;
            $wp_post_types['signal']->rewrite = array(
                'slug' => 'signal',
                'with_front' => false
            );
            $wp_post_types['signal']->publicly_queryable = true;
            
            // Force rewrite rules refresh
            add_action('wp_loaded', function() {
                flush_rewrite_rules();
            });
        }
    }
    
    /**
     * Add custom rewrite rules
     */
    public function addRewriteRules()
    {
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
    
    /**
     * Load archive template
     */
    public function loadArchiveTemplate($template)
    {
        if (is_post_type_archive('signal')) {
            $archive_template = $this->getArchiveTemplate();
            if ($archive_template) {
                return $archive_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Get archive template path
     */
    private function getArchiveTemplate()
    {
        // First check theme directory
        $theme_template = get_template_directory() . '/archive-signal.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        // Fallback to plugin template
        $plugin_template = __DIR__ . '/../templates/archive-signal.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
        
        return false;
    }
    
    /**
     * Enqueue archive styles
     */
    public function enqueueArchiveStyles()
    {
        if (is_post_type_archive('signal')) {
            wp_enqueue_style(
                'signal-archive-styles',
                plugin_dir_url(__DIR__) . 'assets/css/signal-archive.css',
                [],
                '1.0.0'
            );
        }
    }
    
    /**
     * Flush rewrite rules
     */
    public function flushRewriteRules()
    {
        flush_rewrite_rules();
    }
    
    /**
     * Get signals data for display
     */
    public function getSignalsData($args = [])
    {
        $default_args = [
            'post_type' => 'signal',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => 'signal_date',
            'order' => 'DESC',
            'meta_query' => [
                [
                    'key' => 'signal_date',
                    'compare' => 'EXISTS'
                ]
            ]
        ];
        
        $query_args = wp_parse_args($args, $default_args);
        
        return new \WP_Query($query_args);
    }
}
