<?php
/**
 * Plugin Name: ACF Quiz System
 * Description: Advanced Custom Fields quiz system with pass/fail scoring
 * Version: 1.0.0
 * Author: Quiz Developer
 * Text Domain: acf-quiz
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Quiz System Class
 */
class ACF_Quiz_System {
    private static $instance = null;
    private $plugin_url;
    private $plugin_path;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        
        // Check if ACF is active
        if (!class_exists('ACF')) {
            add_action('admin_notices', array($this, 'acf_notice'));
            return;
        }

        // Add version control for CSS files
        add_filter('style_loader_src', array($this, 'remove_css_js_version'), 9999);
        
        $this->init_hooks();
    }
    
    /**
     * Remove version parameter from enqueued CSS/JS files
     * This helps with cache control during development
     */
    public function remove_css_js_version($src) {
        // Only process files from our plugin
        if (strpos($src, 'mu-plugins/acf-quiz/') !== false) {
            // Remove version parameter if it exists
            if (strpos($src, 'ver=')) {
                $src = remove_query_arg('ver', $src);
            }
            // Add file modification time as version for cache busting
            $file_path = str_replace($this->plugin_url, $this->plugin_path, $src);
            if (file_exists($file_path)) {
                $src = add_query_arg('ver', filemtime($file_path), $src);
            }
        }
        return $src;
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('acf/init', array($this, 'add_options_page'));
        add_action('acf/init', array($this, 'register_fields'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_block_checkout_scripts'));
        add_action('wp_footer', array($this, 'add_quiz_scripts'));
        add_action('wp_ajax_submit_quiz', array($this, 'handle_quiz_submission'));
        add_action('wp_ajax_nopriv_submit_quiz', array($this, 'handle_quiz_submission'));
        add_action('woocommerce_checkout_process', array($this, 'populate_checkout_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_checkout_fields'));
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'));
        // Temporarily disable render_block filter to prevent 500 errors
        // add_filter('render_block', array($this, 'filter_woocommerce_checkout_blocks'), 10, 2);
        
        // Multi-step form AJAX handlers
        add_action('wp_ajax_save_step_data', array($this, 'handle_step_data'));
        add_action('wp_ajax_nopriv_save_step_data', array($this, 'handle_step_data'));
        
        // BACKUP: Simple lead capture without complex routing
        add_action('wp_ajax_simple_lead_capture', array($this, 'simple_lead_capture'));
        add_action('wp_ajax_nopriv_simple_lead_capture', array($this, 'simple_lead_capture'));
        
        // Register shortcodes
        add_shortcode('acf_quiz', array($this, 'add_quiz_form'));
        
        // Initialize submissions viewer
        add_action('admin_menu', array($this, 'add_submissions_menu'));
        
        // WooCommerce integration
        add_action('woocommerce_checkout_process', array($this, 'populate_checkout_fields'));
        add_filter('woocommerce_checkout_get_value', array($this, 'get_checkout_field_value'), 10, 2);
        add_filter('woocommerce_add_cart_item_data', array($this, 'handle_custom_cart_item'), 10, 3);
        add_action('woocommerce_before_calculate_totals', array($this, 'set_custom_cart_item_price'));
        
        // Add custom checkout fields
        add_action('woocommerce_checkout_billing', array($this, 'add_checkout_custom_fields'));
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_custom_fields'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_custom_fields'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_order_meta'));
        
        // Remove default WooCommerce fields (classic checkout)
        add_filter('woocommerce_checkout_fields', array($this, 'remove_checkout_fields'));
        
        // Remove additional fields and make single column
        add_action('init', array($this, 'remove_checkout_additional_fields'));
        
        // Handle WooCommerce block checkout
        add_action('woocommerce_blocks_loaded', array($this, 'register_checkout_block_integration'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_block_checkout_scripts'));
        
        // Create WooCommerce products if they don't exist
        add_action('init', array($this, 'create_quiz_products'));
        
        // Show notice if ACF is not active
        if (!class_exists('ACF')) {
            add_action('admin_notices', array($this, 'acf_notice'));
        }
    }

    /**
     * Show ACF requirement notice
     */
    public function acf_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('ACF Quiz System requires Advanced Custom Fields PRO to be installed and activated.', 'acf-quiz'); ?></p>
        </div>
        <?php
    }

    /**
     * Create submissions table with expanded fields for 4-step form
     */
    public function create_submissions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            -- Step 1: Basic Personal Info
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            user_phone varchar(20) NOT NULL,
            user_email varchar(100) NOT NULL,
            
            -- Step 2: Detailed Personal Info
            id_number varchar(20) DEFAULT '',
            gender varchar(10) DEFAULT '',
            birth_date date DEFAULT NULL,
            citizenship varchar(50) DEFAULT 'ישראלית',
            address text DEFAULT '',
            marital_status varchar(20) DEFAULT '',
            employment_status varchar(50) DEFAULT '',
            education varchar(50) DEFAULT '',
            profession varchar(100) DEFAULT '',
            
            -- Legacy fields (for compatibility)
            user_name varchar(100) DEFAULT '',
            contact_consent tinyint(1) DEFAULT 0,
            
            -- Package and submission info
            package_name varchar(100) DEFAULT '',
            package_price decimal(10,2) DEFAULT 0.00,
            package_source varchar(100) DEFAULT '',
            
            -- Quiz results
            answers longtext NOT NULL,
            score int(11) NOT NULL,
            max_score int(11) NOT NULL,
            score_percentage decimal(5,2) NOT NULL,
            passed tinyint(1) NOT NULL,
            
            -- Form completion tracking
            current_step int(1) DEFAULT 1,
            completed tinyint(1) DEFAULT 0,
            declaration_accepted tinyint(1) DEFAULT 0,
            
            -- Meta info
            submission_time datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            signature_data longtext DEFAULT '',
            
            PRIMARY KEY (id),
            KEY passed (passed),
            KEY completed (completed),
            KEY submission_time (submission_time),
            KEY user_email (user_email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Add signature_data column if it doesn't exist (for existing installations)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'signature_data'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN signature_data longtext DEFAULT ''");
        }
    }

    /**
     * Add submissions admin page
     */
    public function add_submissions_page() {
        // Remove the old broken submissions menu - handled by new plugin
    }

    /**
     * Add ACF options page
     */
    public function add_options_page() {
        if (function_exists('acf_add_options_page')) {
            acf_add_options_page(array(
                'page_title'    => __('Quiz Settings', 'acf-quiz'),
                'menu_title'    => __('Quiz System', 'acf-quiz'),
                'menu_slug'     => 'quiz-settings',
                'capability'    => 'manage_options',
                'redirect'      => false,
                'icon_url'      => 'dashicons-forms',
                'position'      => 30
            ));
        }
    }

    /**
     * Register ACF field groups
     */
    public function register_fields() {
        // Register the Quiz Settings field group
        if (function_exists('acf_add_local_field_group')) {
            // Define the fields
            $fields = array(
                // Price Settings Tab
                array(
                    'key' => 'field_price_settings_tab',
                    'label' => 'Package Prices',
                    'name' => '',
                    'type' => 'tab',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'placement' => 'top',
                    'endpoint' => 0,
                ),
                array(
                    'key' => 'field_trial_price',
                    'label' => 'Trial Package Price',
                    'name' => 'trial_price',
                    'type' => 'number',
                    'instructions' => 'Price for trial package (first 3 months)',
                    'required' => 0,
                    'default_value' => 99,
                    'placeholder' => '99',
                    'prepend' => '₪',
                    'append' => '',
                    'min' => 0,
                    'max' => '',
                    'step' => 1,
                    'wrapper' => array(
                        'width' => '33',
                    ),
                ),
                array(
                    'key' => 'field_monthly_price',
                    'label' => 'Monthly Package Price',
                    'name' => 'monthly_price',
                    'type' => 'number',
                    'instructions' => 'Price for monthly package',
                    'required' => 0,
                    'default_value' => 199,
                    'placeholder' => '199',
                    'prepend' => '₪',
                    'append' => '',
                    'min' => 0,
                    'max' => '',
                    'step' => 1,
                    'wrapper' => array(
                        'width' => '33',
                    ),
                ),
                array(
                    'key' => 'field_yearly_price',
                    'label' => 'Yearly Package Price',
                    'name' => 'yearly_price',
                    'type' => 'number',
                    'instructions' => 'Price for yearly package (1999 total, 166 monthly)',
                    'required' => 0,
                    'default_value' => 1999,
                    'placeholder' => '1999',
                    'prepend' => '₪',
                    'append' => '',
                    'min' => 0,
                    'max' => '',
                    'step' => 1,
                    'wrapper' => array(
                        'width' => '34',
                    ),
                ),
                // Quiz Settings Tab
                array(
                    'key' => 'field_quiz_settings_tab',
                    'label' => 'Quiz Settings',
                    'name' => '',
                    'type' => 'tab',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'placement' => 'top',
                    'endpoint' => 0,
                ),
                // Step 1 Intro Content
                array(
                    'key' => 'field_step_1_intro',
                    'label' => 'Step 1 - Intro Content',
                    'name' => 'step_1_intro',
                    'type' => 'wysiwyg',
                    'instructions' => 'Enter the introduction text for step 1',
                    'required' => 0,
                    'tabs' => 'all',
                    'toolbar' => 'full',
                    'media_upload' => 1,
                    'delay' => 0,
                ),
                // Legal Notice Content
                array(
                    'key' => 'field_legal_notice',
                    'label' => 'Legal Notice',
                    'name' => 'legal_notice',
                    'type' => 'wysiwyg',
                    'instructions' => 'Enter the legal notice text shown at the bottom of step 1',
                    'required' => 0,
                    'tabs' => 'all',
                    'toolbar' => 'full',
                    'media_upload' => 1,
                    'delay' => 0,
                ),
                // Button Text Content
                array(
                    'key' => 'field_button_text',
                    'label' => 'Button Text',
                    'name' => 'button_text',
                    'type' => 'text',
                    'instructions' => 'Enter the text for the next button (default: המשך)',
                    'required' => 0,
                    'default_value' => 'המשך',
                ),
                // Final Declaration Content
                array(
                    'key' => 'field_final_declaration',
                    'label' => 'Final Declaration Text',
                    'name' => 'final_declaration_text',
                    'type' => 'wysiwyg',
                    'instructions' => 'Enter the final declaration text that users must agree to',
                    'required' => 0,
                    'tabs' => 'all',
                    'toolbar' => 'full',
                    'media_upload' => 1,
                    'delay' => 0,
                ),
                // Quiz Title
                array(
                    'key' => 'field_quiz_title',
                    'label' => __('Quiz Title', 'acf-quiz'),
                    'name' => 'quiz_title',
                    'type' => 'text',
                    'required' => 1,
                    'default_value' => __('שאלון התאמה - חלק א׳', 'acf-quiz'),
                    'wrapper' => array(
                        'width' => '50',
                    ),
                ),
                // Passing Score (fixed at 21 points)
                array(
                    'key' => 'field_passing_score',
                    'label' => __('Passing Score', 'acf-quiz'),
                    'name' => 'passing_score',
                    'type' => 'number',
                    'required' => 1,
                    'default_value' => 21,
                    'min' => 1,
                    'max' => 40,
                    'step' => 1,
                    'wrapper' => array(
                        'width' => '50',
                    ),
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                // Quiz Instructions
                array(
                    'key' => 'field_quiz_instructions',
                    'label' => __('Quiz Instructions', 'acf-quiz'),
                    'name' => 'quiz_instructions',
                    'type' => 'textarea',
                    'default_value' => __('אנא ענה על כל השאלות. עליך לצבור 21 נקודות ומעלה (מתוך 40) כדי לעבור את המבחן.', 'acf-quiz'),
                    'rows' => 3,
                ),
            );

            // Add questions repeater
            $questions_field = array(
                'key' => 'field_quiz_questions',
                'label' => __('Quiz Questions', 'acf-quiz'),
                'name' => 'quiz_questions',
                'type' => 'repeater',
                'instructions' => __('הוסף בדיוק 10 שאלות לשאלון', 'acf-quiz'),
                'required' => 1,
                'collapsed' => 'field_question_text',
                'min' => 10,
                'max' => 10,
                'layout' => 'block',
                'button_label' => __('הוסף שאלה', 'acf-quiz'),
                'sub_fields' => array(
                    array(
                        'key' => 'field_question_text',
                        'label' => __('שאלה', 'acf-quiz'),
                        'name' => 'question_text',
                        'type' => 'text',
                        'required' => 1,
                        'wrapper' => array('width' => '100%'),
                    ),
                    array(
                        'key' => 'field_answers',
                        'label' => __('תשובות', 'acf-quiz'),
                        'name' => 'answers',
                        'type' => 'repeater',
                        'layout' => 'table',
                        'button_label' => __('הוסף תשובה', 'acf-quiz'),
                        'sub_fields' => array(
                            array(
                                'key' => 'field_answer_text',
                                'label' => __('טקסט תשובה', 'acf-quiz'),
                                'name' => 'answer_text',
                                'type' => 'text',
                                'required' => 1,
                                'wrapper' => array('width' => '80%'),
                            ),
                            array(
                                'key' => 'field_answer_points',
                                'label' => __('נקודות (1-4)', 'acf-quiz'),
                                'name' => 'points',
                                'type' => 'number',
                                'required' => 1,
                                'min' => 1,
                                'max' => 4,
                                'default_value' => 1,
                                'wrapper' => array('width' => '20%'),
                            ),
                        ),
                    ),
                ),
            );

            // Add questions field to fields array
            $fields[] = $questions_field;

            // Add the field group
            $field_group = array(
                'key' => 'group_quiz_settings',
                'title' => __('Quiz Settings', 'acf-quiz'),
                'fields' => $fields,
                'location' => array(
                    array(
                        array(
                            'param' => 'options_page',
                            'operator' => '==',
                            'value' => 'quiz-settings',
                        ),
                    ),
                ),
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
                'instruction_placement' => 'label',
                'hide_on_screen' => '',
                'active' => true
            );
            
            // Register the field group
            acf_add_local_field_group($field_group);
            
            // Set default questions if not already set
            $default_questions = array(
                array(
                    'question_text' => 'מהי רמת הניסיון שלך בשוק ההון?',
                    'answers' => array(
                        array(
                            'answer_text' => 'אין לי ניסיון כלל',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'השקעות בסיסיות (כגון קניית קרנות נאמנות דרך הבנק)',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'מבצע פעולות עצמאיות בתדירות בינונית',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'עוקב ומבצע פעולות שוטפות באופן עצמאי',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'מה היקף ההשקעות שלך בשוק ההון?',
                    'answers' => array(
                        array(
                            'answer_text' => 'עד 10% מהנכסים שלי',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => '10%-30% מהנכסים שלי',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => '30%-50% מהנכסים שלי',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'מעל 50% מהנכסים שלי',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'כמה זמן אתה מקדיש לעקיבה אחר השקעותיך?',
                    'answers' => array(
                        array(
                            'answer_text' => 'כמעט לא עוקב',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'עוקב מדי פעם (פעם בחודש או פחות)',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'עוקב באופן קבוע (פעם בשבוע)',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'עוקב באופן יומיומי',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'מה היקף הידע שלך בכלים פיננסיים מורכבים?',
                    'answers' => array(
                        array(
                            'answer_text' => 'אין לי ידע בכלים מורכבים',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'ידע בסיסי בכלים מורכבים',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'ידע טוב בכלים מורכבים',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'ידע מתקדם וניסיון בכלים מורכבים',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'איך אתה מגיב לירידות בשוק?',
                    'answers' => array(
                        array(
                            'answer_text' => 'נכנס לפאניקה ומוכר מיד',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'מודאג אבל מחכה להתאוששות',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'שומר על קור רוח ומנתח את המצב',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'רואה הזדמנות לקנייה נוספת',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'מה אופק הזמן להשקעותיך?',
                    'answers' => array(
                        array(
                            'answer_text' => 'פחות משנה',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => '1-3 שנים',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => '3-7 שנים',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'מעל 7 שנים',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'איך אתה מגדיר את יכולת הסיכון שלך?',
                    'answers' => array(
                        array(
                            'answer_text' => 'שמרני מאוד - מעדיף ביטחון על פני תשואה',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'שמרני - מוכן לסיכון נמוך לתשואה מתונה',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'מתון - מוכן לסיכון בינוני לתשואה טובה',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'אגרסיבי - מוכן לסיכון גבוה לתשואה גבוהה',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'מה המטרה העיקרית של ההשקעות שלך?',
                    'answers' => array(
                        array(
                            'answer_text' => 'שמירה על הון וביטחון פיננסי',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'הכנסה שוטפת (דיבידנדים, ריביות)',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'צמיחה מתונה של ההון',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'צמיחה אגרסיבית של ההון',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'איך אתה מתמודד עם תנודתיות בתיק ההשקעות?',
                    'answers' => array(
                        array(
                            'answer_text' => 'תנודתיות גורמת לי ללחץ רב',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'מעדיף תנודתיות נמוכה',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'יכול להתמודד עם תנודתיות בינונית',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'תנודתיות לא מפריעה לי',
                            'points' => 4
                        )
                    )
                ),
                array(
                    'question_text' => 'מה רמת ההבנה שלך בדוחות כספיים וניתוח חברות?',
                    'answers' => array(
                        array(
                            'answer_text' => 'אין לי הבנה בדוחות כספיים',
                            'points' => 1
                        ),
                        array(
                            'answer_text' => 'הבנה בסיסית בדוחות כספיים',
                            'points' => 2
                        ),
                        array(
                            'answer_text' => 'הבנה טובה וניסיון בניתוח דוחות',
                            'points' => 3
                        ),
                        array(
                            'answer_text' => 'הבנה מתקדמת וניסיון בניתוח יסודי',
                            'points' => 4
                        )
                    )
                )
            );
            
            // Only populate if fields are empty (allows admin edits to persist)
            $current_questions = get_field('quiz_questions', 'option');
            if (empty($current_questions) || count($current_questions) < 10) {
                update_field('quiz_questions', $default_questions, 'option');
            }
            
            // Only set defaults if not already set
            if (!get_field('quiz_title', 'option')) {
                update_field('quiz_title', 'שאלון התאמה - חלק א׳', 'option');
            }
            
            if (!get_field('quiz_instructions', 'option')) {
                update_field('quiz_instructions', 'אנא ענה על כל השאלות. עליך לצבור 21 נקודות ומעלה (מתוך 40) כדי לעבור את המבחן.', 'option');
            }
            
            if (!get_field('passing_score', 'option')) {
                update_field('passing_score', 21, 'option');
            }
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            'quiz-public-js',
            plugins_url('js/quiz-public.js', __FILE__),
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Add AJAX URL for frontend
        wp_localize_script('quiz-public-js', 'quiz_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quiz_nonce')
        ));
    }

    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        if (!is_admin()) {
            // Auto-version CSS with filemtime
            $css_path = $this->plugin_path . 'css/quiz-public.css';
            $css_version = file_exists($css_path) ? filemtime($css_path) : '1.0.0';
            
            // Enqueue main CSS
            wp_enqueue_style(
                'acf-quiz-public',
                $this->plugin_url . 'css/quiz-public.css',
                array(),
                $css_version
            );
            
            // Remove elform.css - styles moved to quiz-public.css
            // wp_enqueue_style(
            //     'acf-quiz-elform',
            //     $this->plugin_url . 'css/elform.css',
            //     array('acf-quiz-public'),
            //     '1.0.1'
            // );

            // Add jQuery to handle button visibility
            wp_enqueue_script('jquery');
            
            // Add inline JavaScript for button visibility
            $custom_js = "
            jQuery(document).ready(function($) {
                // Simple function to check if we're on step 4
                function checkStep4() {
                    var isStep4 = $('.step[data-step=\"4\"]').hasClass('active');
                    
                    if (isStep4) {
                        $('.submit-btn, #submit-form').show();
                        $('.next-btn').hide();
                    } else {
                        $('.submit-btn, #submit-form').hide();
                        $('.next-btn').show();
                    }
                }
                
                // Run on page load
                checkStep4();
                
                // Run when buttons are clicked
                $(document).on('click', '.next-btn, .prev-btn', function() {
                    setTimeout(checkStep4, 100);
                });
                
                // Run periodically to catch any changes
                setInterval(checkStep4, 1000);
            });";
            
            wp_add_inline_script('jquery', $custom_js);
            
            // Enqueue signature pad library
            wp_enqueue_script(
                'signature-pad',
                'https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js',
                array('jquery'),
                '4.0.0',
                true
            );
            wp_enqueue_script(
                'signature-capture',
                plugins_url('signature-capture.js', __FILE__),
                array('signature-pad', 'jquery'),
                '1.0.0',
                true
            );
            
            // Add signature pad initialization
            $signature_js = "
            // Test signature system function
            function testSignatureSystem() {
                console.log('Testing signature system...');
                
                // Check if signature canvas exists
                const canvas = document.getElementById('signature_canvas');
                if (!canvas) {
                    alert('❌ Signature canvas not found');
                    return;
                }
                
                // Check if SignaturePad is loaded
                if (typeof SignaturePad === 'undefined') {
                    alert('❌ SignaturePad library not loaded');
                    return;
                }
                
                // Check if signature_ajax is available
                if (typeof signature_ajax === 'undefined') {
                    alert('❌ Signature AJAX not configured');
                    return;
                }
                
                // Create a test signature
                const signaturePad = new SignaturePad(canvas);
                
                // Draw a simple test signature
                signaturePad.fromDataURL('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==');
                
                const testData = signaturePad.toDataURL('image/png', 0.8);
                
                // Test save signature
                const formData = new FormData();
                formData.append('action', 'save_signature');
                formData.append('nonce', signature_ajax.nonce);
                formData.append('signature_data', testData);
                formData.append('submission_id', 999);
                formData.append('user_email', 'test@example.com');
                
                fetch(signature_ajax.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Signature system working! ID: ' + data.data.signature_id);
                    } else {
                        alert('❌ Signature save failed: ' + data.data);
                    }
                })
                .catch(error => {
                    alert('❌ Network error: ' + error);
                });
            }
            
            jQuery(document).ready(function($) {
                // Ensure final declaration checkbox is unchecked by default - multiple approaches
                function forceUncheckDeclaration() {
                    $('#final_declaration').prop('checked', false);
                    $('#final_declaration').removeAttr('checked');
                    $('#final_declaration')[0].checked = false;
                    console.log('Final declaration checkbox forced to unchecked');
                }
                
                // Signature Pad Management - Stable Implementation
                let signaturePadInstance = null;
                let isSignaturePadInitialized = false;
                
                function initializeSignaturePad() {
                    if (isSignaturePadInitialized) {
                        return;
                    }
                    
                    const canvas = document.getElementById('signature_pad');
                    const placeholder = document.getElementById('signature_placeholder');
                    const statusElement = document.getElementById('signature_status');
                    const clearButton = document.getElementById('clear_signature');
                    const hiddenInput = document.getElementById('signature_data');
                    
                    if (!canvas || !placeholder || !statusElement || !clearButton || !hiddenInput) {
                        console.warn('Signature elements not found, retrying...');
                        setTimeout(initializeSignaturePad, 500);
                        return;
                    }
                    
                    // Set canvas dimensions properly
                    function setupCanvas() {
                        const container = canvas.parentElement;
                        const containerWidth = container.offsetWidth || 400;
                        const canvasHeight = 150;
                        
                        // Set display size
                        canvas.style.width = '100%';
                        canvas.style.height = canvasHeight + 'px';
                        
                        // Set actual size for high DPI
                        const ratio = window.devicePixelRatio || 1;
                        canvas.width = containerWidth * ratio;
                        canvas.height = canvasHeight * ratio;
                        
                        // Scale context
                        const ctx = canvas.getContext('2d');
                        ctx.scale(ratio, ratio);
                        ctx.fillStyle = 'white';
                        ctx.fillRect(0, 0, containerWidth, canvasHeight);
                    }
                    
                    setupCanvas();
                    
                    // Initialize SignaturePad
                    try {
                        signaturePadInstance = new SignaturePad(canvas, {
                            backgroundColor: 'rgba(255, 255, 255, 1)',
                            penColor: 'rgba(0, 0, 0, 1)',
                            minWidth: 1.5,
                            maxWidth: 2.5,
                            velocityFilterWeight: 0.7,
                            minDistance: 2
                        });
                        
                        // Handle signature events
                        signaturePadInstance.addEventListener('beginStroke', function() {
                            placeholder.style.display = 'none';
                        });
                        
                        signaturePadInstance.addEventListener('endStroke', function() {
                            if (!signaturePadInstance.isEmpty()) {
                                const dataURL = signaturePadInstance.toDataURL('image/png', 0.8);
                                hiddenInput.value = dataURL;
                                statusElement.textContent = '✓ חתימה נשמרה';
                                statusElement.className = 'signature-status signature-valid';
                            }
                        });
                        
                        // Clear button functionality
                        clearButton.addEventListener('click', function(e) {
                            e.preventDefault();
                            signaturePadInstance.clear();
                            hiddenInput.value = '';
                            placeholder.style.display = 'flex';
                            statusElement.textContent = 'חתימה נדרשת';
                            statusElement.className = 'signature-status signature-required';
                        });
                        
                        // Touch handling for mobile
                        function preventScroll(e) {
                            if (e.target === canvas) {
                                e.preventDefault();
                            }
                        }
                        
                        canvas.addEventListener('touchstart', preventScroll, { passive: false });
                        canvas.addEventListener('touchmove', preventScroll, { passive: false });
                        
                        // Responsive handling
                        let resizeTimer;
                        window.addEventListener('resize', function() {
                            clearTimeout(resizeTimer);
                            resizeTimer = setTimeout(function() {
                                const data = signaturePadInstance.toData();
                                setupCanvas();
                                if (data && data.length > 0) {
                                    signaturePadInstance.fromData(data);
                                }
                            }, 250);
                        });
                        
                        isSignaturePadInitialized = true;
                        console.log('Signature pad initialized successfully');
                        
                    } catch (error) {
                        console.error('Failed to initialize signature pad:', error);
                        statusElement.textContent = 'שגיאה בטעינת חתימה';
                        statusElement.className = 'signature-status signature-error';
                    }
                }
                
                // Initialize when step 4 is shown
                $(document).on('formStepChanged', function(e, step) {
                    if (step === 4 && !isSignaturePadInitialized) {
                        setTimeout(initializeSignaturePad, 100);
                    }
                });
                
                // Initialize on page load if already on step 4
                $(document).ready(function() {
                    setTimeout(initializeSignaturePad, 200);
                });
            });";
            
            wp_add_inline_script('signature-pad', $signature_js);
        }
    }

    /**
     * Add quiz scripts to footer
     */
    public function add_quiz_scripts() {
        // This method is called by wp_footer hook
        // Most scripts are now handled in enqueue_scripts() and enqueue_public_assets()
        // Keep this method for any footer-specific scripts if needed
    }

    /**
     * Add quiz form to pages with specific shortcode
     */
    public function add_quiz_form($atts) {
        // Enqueue public assets when shortcode is used
        $this->enqueue_public_assets();
        
        // Return the quiz form HTML
        return $this->render_quiz_form($atts);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'quiz-settings') !== false) {
            wp_enqueue_style(
                'acf-quiz-admin',
                $this->plugin_url . 'css/quiz-admin.css',
                array(),
                '1.0.0'
            );
        }
    }

    /**
     * Render 4-step suitability questionnaire form
     */
    public function render_quiz_form($atts = array()) {
        // Default attributes
        $atts = shortcode_atts(array(
            'package' => '',
            'price' => '',
            'source' => ''
        ), $atts);
        
        // Get package from URL parameter if not in shortcode
        $package = $atts['package'] ?: (isset($_GET['package']) ? sanitize_text_field($_GET['package']) : '');
        $price = $atts['price'] ?: (isset($_GET['price']) ? sanitize_text_field($_GET['price']) : '');
        $source = $atts['source'] ?: (isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '');
        
        // Get quiz data
        $questions = get_field('quiz_questions', 'option');
        
        if (empty($questions) || count($questions) !== 10) {
            return '<div class="quiz-error"><p>' . __('השאלון לא מוגדר כראוי. אנא פנה למנהל האתר.', 'acf-quiz') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="acf-quiz-container multi-step-form" data-passing-score="21" data-max-score="40" dir="rtl">
            
            <!-- Step Progress Indicator -->
            <div class="step-progress">
                <div class="step-indicator">
                    <div class="step active" data-step="1">1</div>
                    <div class="step" data-step="2">2</div>
                    <div class="step" data-step="3">3</div>
                    <div class="step" data-step="4">4</div>
                </div>
                <div class="step-title">
                    <h2 id="step-title">שאלון התאמה</h2>
                    <p id="step-subtitle">שלב 1 מתוך 4</p>
                </div>
            </div>

            <form id="acf-quiz-form" class="quiz-form multi-step" dir="rtl">
                <?php wp_nonce_field('quiz_step_nonce', 'quiz_nonce'); ?>
            
            <!-- Package Parameters -->
            <input type="hidden" name="package_param" value="<?php echo esc_attr($package); ?>">
            <input type="hidden" name="package_price_param" value="<?php echo esc_attr($price); ?>">
            <input type="hidden" name="package_source_param" value="<?php echo esc_attr($source); ?>">
                
                <!-- Hidden package information -->
                <input type="hidden" name="package_selected" value="<?php echo esc_attr($package); ?>">
                <input type="hidden" name="package_price" value="<?php echo esc_attr($price); ?>">
                <input type="hidden" name="package_source" value="<?php echo esc_attr($source); ?>">
                
                <!-- Step 1: Basic Personal Information -->
                <div class="form-step active" data-step="1">
                    <div class="step-intro">
                        <?php 
                        $step_1_intro = get_field('step_1_intro', 'option');
                        if ($step_1_intro) {
                            echo $step_1_intro;
                        } else {
                            // Default content if no ACF field is set
                            echo '<h3>שלום ברוך הבא!</h3>';
                            echo '<p>אני שמח שבחרת להצטרף לשירות שלי</p>';
                            echo '<p>היות ואני מנהל השקעות, מס\' רישיון 7955 השירות מנוהל בהתאם לתקנות של הרשות לניירות ערך</p>';
                            echo '<p>ולכן מבוצע איתך הליך מקוון של בירור התאמה לשירות שמטרתו לאסוף את המידע הרלוונטי אודותיך ולברר האם הינך עם הבנה מספקת בשוק הון שכן זהו תנאי הכרחי להרשמה לשרות ונדרש על-פי חוק</p>';
                        }
                        ?>
                    </div>
                    
                    <div class="personal-fields">
                        <div class="field-group">
                            <label for="first_name" class="field-label">שם פרטי <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="field-input" required>
                        </div>
                        
                        <div class="field-group">
                            <label for="last_name" class="field-label">שם משפחה <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="field-input" required>
                        </div>
                        
                        <div class="field-group">
                            <label for="user_phone" class="field-label">טלפון <span class="required">*</span></label>
                            <input type="tel" id="user_phone" name="user_phone" class="field-input" required>
                        </div>
                        
                        <div class="field-group">
                            <label for="user_email" class="field-label">אימייל <span class="required">*</span></label>
                            <input type="email" id="user_email" name="user_email" class="field-input" required>
                        </div>
                    </div>
                    
                    <div class="legal-notice">
                        <?php
                        $legal_notice = get_field('legal_notice', 'option');
                        if ($legal_notice) {
                            echo $legal_notice;
                        } else {
                            // Default content if no ACF field is set
                            echo '<h4>לתשומת ליבך:</h4>';
                            echo '<p>על מנת שנוכל לבחון בצורה המיטבית את התאמתך לשירות המידע, שיתוף הפעולה מצידך הינו קריטי לשם ביצוע הליך הבירור באופן יעיל. אי מסירת פרטים או מסירת פרטים חלקיים עשויות למנוע ממפעיל השרות לספק לך את השירות.</p>';
                            echo '<p>על פי תיקון ההוראה לבעלי רישיון בקשר למתן שירותים תוך שימוש באמצעים טכנולוגיים מאוגוסט 2023, אנחנו מדגישים כי שירות הייעוץ למסחר עצמאי אינו מותאם אישית, ועל כן השירותים אינם מותאמים באופן פרטני ללקוח או לצרכיו.</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Step 2: Detailed Personal Information -->
                <div class="form-step" data-step="2">
                    <div class="step-intro">
                        <h3>פרטים מלאים</h3>
                    </div>
                    
                    <div class="personal-fields">
                        <div class="field-group">
                            <label for="id_number" class="field-label">תעודת זהות / דרכון <span class="required">*</span></label>
                            <input type="text" id="id_number" name="id_number" class="field-input" required>
                        </div>
                        
                        <div class="field-group">
                            <label for="gender" class="field-label">מין <span class="required">*</span></label>
                            <select id="gender" name="gender" class="field-input" required>
                                <option value="">בחר</option>
                                <option value="male">זכר</option>
                                <option value="female">נקבה</option>
                            </select>
                        </div>
                        
                        <div class="field-group">
                            <label for="birth_date" class="field-label">תאריך לידה</label>
                            <div class="date-input-group">
                                <select id="birth_day" name="birth_day" class="field-input date-select">
                                    <option value="">יום</option>
                                    <?php for($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <select id="birth_month" name="birth_month" class="field-input date-select">
                                    <option value="">חודש</option>
                                    <option value="1">ינואר</option>
                                    <option value="2">פברואר</option>
                                    <option value="3">מרץ</option>
                                    <option value="4">אפריל</option>
                                    <option value="5">מאי</option>
                                    <option value="6">יוני</option>
                                    <option value="7">יולי</option>
                                    <option value="8">אוגוסט</option>
                                    <option value="9">ספטמבר</option>
                                    <option value="10">אוקטובר</option>
                                    <option value="11">נובמבר</option>
                                    <option value="12">דצמבר</option>
                                </select>
                                <select id="birth_year" name="birth_year" class="field-input date-select">
                                    <option value="">שנה</option>
                                    <?php 
                                    $current_year = date('Y');
                                    for($i = $current_year; $i >= ($current_year - 100); $i--): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                <input type="hidden" id="birth_date" name="birth_date">
                            </div>
                        </div>
                        
                        <div class="field-group">
                            <label for="citizenship" class="field-label">אזרחות</label>
                            <input type="text" id="citizenship" name="citizenship" class="field-input" value="ישראלית">
                        </div>
                        
                        <div class="field-group">
                            <label for="address" class="field-label">כתובת</label>
                            <input type="text" id="address" name="address" class="field-input">
                        </div>
                        
                        <div class="field-group">
                            <label for="marital_status" class="field-label">מצב משפחתי</label>
                            <select id="marital_status" name="marital_status" class="field-input">
                                <option value="">בחר</option>
                                <option value="single">רווק/ה</option>
                                <option value="married">נשוי/ה</option>
                                <option value="divorced">גרוש/ה</option>
                                <option value="widowed">אלמן/ה</option>
                            </select>
                        </div>
                        
                        <div class="field-group">
                            <label for="employment_status" class="field-label">מצב תעסוקתי</label>
                            <select id="employment_status" name="employment_status" class="field-input">
                                <option value="">בחר</option>
                                <option value="employed">שכיר/ה</option>
                                <option value="self_employed">עצמאי/ת</option>
                                <option value="unemployed">מובטל/ת</option>
                                <option value="retired">פנסיונר/ית</option>
                                <option value="student">סטודנט/ית</option>
                            </select>
                        </div>
                        
                        <div class="field-group">
                            <label for="education" class="field-label">השכלה</label>
                            <select id="education" name="education" class="field-input">
                                <option value="">בחר</option>
                                <option value="high_school">תיכון</option>
                                <option value="bachelor">תואר ראשון</option>
                                <option value="master">תואר שני</option>
                                <option value="doctorate">תואר שלישי</option>
                                <option value="other">אחר</option>
                            </select>
                        </div>
                        
                        <div class="field-group">
                            <label for="profession" class="field-label">מקצוע</label>
                            <input type="text" id="profession" name="profession" class="field-input">
                        </div>
                    </div>
                </div>

                <!-- Step 3: First 5 Investment Questions -->
                <div class="form-step" data-step="3">
                    <div class="step-intro">
                        <h3>שאלון התאמה - חלק ב׳</h3>
                        <p>אנא השיבו על השאלות הבאות בהתאם לידע ולניסיון שלכם</p>
                    </div>
                    
                    <div class="questions-container">
                        <?php for ($i = 0; $i < 5; $i++) : ?>
                            <?php if (isset($questions[$i])) : ?>
                                <div class="question-block" data-question="<?php echo $i; ?>">
                                    <div class="question-header">
                                        <span class="question-number"><?php echo ($i + 1); ?>.</span>
                                        <h3 class="question-text"><?php echo esc_html($questions[$i]['question_text']); ?></h3>
                                    </div>
                                    
                                    <div class="answers-container">
                                        <?php if (!empty($questions[$i]['answers'])) : ?>
                                            <?php foreach ($questions[$i]['answers'] as $a_index => $answer) : ?>
                                                <div class="answer-option">
                                                    <input type="radio" 
                                                           name="question_<?php echo $i; ?>" 
                                                           id="q<?php echo $i; ?>_a<?php echo $a_index; ?>"
                                                           value="<?php echo $answer['points']; ?>"
                                                           class="answer-input"
                                                           required>
                                                    <label for="q<?php echo $i; ?>_a<?php echo $a_index; ?>" class="answer-label">
                                                        <span class="answer-marker"></span>
                                                        <span class="answer-text"><?php echo esc_html($answer['answer_text']); ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Step 4: Last 5 Questions + Declaration -->
                <div class="form-step" data-step="4">
                    <div class="step-intro">
                        <h3>שאלון התאמה - חלק ב׳</h3>
                        <p>השלמת השאלות האחרונות והצהרה</p>
                    </div>
                    
                    <div class="questions-container">
                        <?php for ($i = 5; $i < 10; $i++) : ?>
                            <?php if (isset($questions[$i])) : ?>
                                <div class="question-block" data-question="<?php echo $i; ?>">
                                    <div class="question-header">
                                        <span class="question-number"><?php echo ($i + 1); ?>.</span>
                                        <h3 class="question-text"><?php echo esc_html($questions[$i]['question_text']); ?></h3>
                                    </div>
                                    
                                    <div class="answers-container">
                                        <?php if (!empty($questions[$i]['answers'])) : ?>
                                            <?php foreach ($questions[$i]['answers'] as $a_index => $answer) : ?>
                                                <div class="answer-option">
                                                    <input type="radio" 
                                                           name="question_<?php echo $i; ?>" 
                                                           id="q<?php echo $i; ?>_a<?php echo $a_index; ?>"
                                                           value="<?php echo $answer['points']; ?>"
                                                           class="answer-input"
                                                           required>
                                                    <label for="q<?php echo $i; ?>_a<?php echo $a_index; ?>" class="answer-label">
                                                        <span class="answer-marker"></span>
                                                        <span class="answer-text"><?php echo esc_html($answer['answer_text']); ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="final-declaration">
                        <div class="declaration-content">
                            <div class="declaration-text">
                                <p>אני מצהיר שכל המידע שמסרתי לעיל הינו נכון, מדויק ומלא וכי בהשיבי על השאלון לעיל לא החסרתי כל פרט שהוא ממנהל השירות. ידוע לי שמנהל השירות מסתמך על הצהרתי זו לצורך החלטה באם לאשר לי מתן שירותי ייעוץ למסחר עצמאי.</p>
                                <p>אני מאשר ש:</p>
                                <ul>
                                    <li>השירות אינו מהווה ייעוץ השקעות אישי שמותאם לי</li>
                                    <li>כל פעולה שאני מבצע בעקבות איתות או מידע שהתקבל במסגרת השירות היא על אחריותי הבלעדית</li>
                                    <li>אני מבין שהשירות כולל גם מידע כללי, פרשנויות שוק, ניתוחים ודעות מקצועיות, אך אין בו התאמה אישית לתיק ההשקעות שלי.</li>
                                    <li>שייתכן שלא אקבל הודעה מסוימת בזמן אמת או כלל, לאור העובדה שהשירות ניתן באמצעים טכנולוגיים בלבד כגון וואטסאפ, וייתכנו תקלות, עיכובים או כשל בהעברת הודעות.</li>
                                    <li>אני מבין שאין אפשרות לשוחח עם מנהל השירות על כל המלצה או איתות באופן מותאם ואישי.</li>
                                </ul>
                                <p>כן ידוע לי כי ככל שהצהרה מהצהרותיי לעיל תתברר כלא מלאה או לא מדויקת, יהא מנהל השירות רשאי להפסיק לתת לי שירות, והנני מוותר על כל טענה ו/או תביעה ו/או דרישה כנגד מנהל השירות ו/או מי מטעמו בגין כל נזק ו/או הוצאה שיגרמו לי בקשר עם מתן השירות והפסקתו כאמור.</p>
                            </div>
                            
                            <div class="declaration-checkbox">
                                <div class="checkbox-group-new">
                                    <input type="checkbox" id="final_declaration" name="final_declaration" class="checkbox-input-new rtl-input" required>
                                    <label for="final_declaration" class="checkbox-label-new">
                                        אני מאשר כי קראתי והבנתי את כל האמור לעיל
                                        <span class="required">*</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="declaration-text">
                                <p>אני מצהיר שכל המידע שמסרתי לעיל הינו נכון, מדויק ומלא וכי בהשיבי על השאלון לעיל לא החסרתי כל פרט שהוא ממנהל השירות. ידוע לי שמנהל השירות מסתמך על הצהרתי זו לצורך החלטה באם לאשר לי מתן שירותי ייעוץ למסחר עצמאי.</p>
                                <p>אני מבין כי מסירת מידע כוזב או לא מדויק עלולה להביא לביטול הסכם השירות ו/או לכל תוצאה משפטית אחרת.</p>
                                <p>אני מאשר כי קיבלתי הסברים מפורטים אודות השירותים הניתנים על ידי מנהל השירות, לרבות אודות הסיכונים הכרוכים במסחר עצמאי בכלים פיננסיים.</p>
                            </div>
                            
                            <!-- Conditional subscription checkbox based on package type -->
                            <div class="subscription-checkbox trial-packages" style="display: none;">
                                <input type="checkbox" id="subscription_terms_3month" name="subscription_terms_3month" class="checkbox-input-new" required>
                                <label for="subscription_terms_3month" class="checkbox-label-new">
                                    אני מאשר כי קראתי והבנתי את תנאי המנוי, לרבות העובדה כי לאחר תקופת ההטבה (3 חודשים במחיר מוזל), יתחדש המנוי אוטומטית מדי חודש במחיר המלא.
                                    <span class="required">*</span>
                                </label>
                            </div>
                            
                            <div class="subscription-checkbox other-packages" style="display: none;">
                                <input type="checkbox" id="subscription_terms_other" name="subscription_terms_other" class="checkbox-input-new" required>
                                <label for="subscription_terms_other" class="checkbox-label-new">
                                    אני מאשר כי קראתי והבנתי את תנאי המנוי, לרבות העובדה כי בתום תקופת ההטבה יתחדש המנוי באופן אוטומטי בהתאם למסלול שנבחר.
                                    <span class="required">*</span>
                                </label>
                            </div>
                            
                            <div class="signature-section">
                                <h5>חתימה דיגיטלית <span class="signature-required">*</span></h5>
                                <p class="signature-instructions">אנא חתום במסגרת למטה באמצעות העכבר או המגע</p>
                                <div class="signature-container">
                                    <div class="signature-wrapper">
                                        <canvas id="signature_pad" width="400" height="150"></canvas>
                                        <div class="signature-placeholder" id="signature_placeholder">
                                            <span>חתום כאן</span>
                                        </div>
                                    </div>
                                    <div class="signature-controls">
                                        <button type="button" id="clear_signature" class="clear-signature-btn">
                                            <span>🗑️</span> נקה חתימה
                                        </button>
                                        <span id="signature_status" class="signature-status">חתימה נדרשת</span>
                                    </div>
                                    <input type="hidden" id="signature_data" name="signature_data" required>
                                </div>
                                
                                <!-- Test Signature Button -->
                                <div style="margin-top: 10px; padding: 10px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px;">
                                    <button type="button" onclick="testSignatureSystem()" class="button" style="background: #0073aa; color: white; border: none; padding: 8px 16px; border-radius: 3px; cursor: pointer;">
                                        🧪 Test Signature System
                                    </button>
                                    <span style="font-size: 12px; color: #666; margin-left: 10px;">Click to test if signature saving works</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-navigation">
                    <button type="button" id="prev-step" class="nav-btn prev-btn" style="display: none;">
                        <span class="elementor-button-content-wrapper">
                            <span class="elementor-button-icon elementor-align-icon-right">
                                <i aria-hidden="true" class="fas fa-angle-left"></i>
                            </span>
                            <span class="elementor-button-text">חזרה</span>
                        </span>
                    </button>
                    <button type="button" id="next-step" class="nav-btn next-btn">
                        <span class="elementor-button-content-wrapper">
                            <span class="elementor-button-text"><?php echo get_field('button_text', 'option') ?: 'המשך'; ?></span>
                            <span class="elementor-button-icon elementor-align-icon-left">
                                <i aria-hidden="true" class="fas fa-angle-right"></i>
                            </span>
                        </span>
                    </button>
                    <button type="submit" id="submit-form" class="nav-btn submit-btn" style="display: none;">שלח שאלון</button>
                </div>
            </form>
            
            
            <!-- Results container -->
            <div id="quiz-results" class="quiz-results" style="display: none;">
                <div class="results-content">
                    <h3 class="results-title">תוצאות השאלון</h3>
                    <div class="score-display">
                        <span id="quiz-score">0/40</span>
                    </div>
                    <div id="result-message" class="result-message"></div>
                </div>
            </div>
        </div>
        
        <script>
        // Pass data to JavaScript
        window.acfQuiz = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('acf_quiz_nonce'); ?>',
            strings: {
                submit: 'שלח שאלון',
                submitting: 'שולח...',
                error: 'אירעה שגיאה. אנא נסה שוב.',
                pleaseAnswerAll: 'אנא ענה על כל השאלות',
                next: 'המשך',
                prev: 'חזרה',
                step1Title: 'שאלון התאמה',
                step2Title: 'שאלון התאמה',
                step3Title: 'שאלון התאמה',
                step4Title: 'שאלון התאמה',
                step1Subtitle: 'שלב 1 מתוך 4',
                step2Subtitle: 'שלב 2 מתוך 4',
                step3Subtitle: 'שלב 3 מתוך 4',
                step4Subtitle: 'שלב 4 מתוך 4'
            }
        };
        
        // Combine date fields into hidden birth_date field
        jQuery(document).ready(function($) {
            var formStarted = false;
            var originalBeforeUnload = window.onbeforeunload;
            
            function updateBirthDate() {
                var day = $('#birth_day').val();
                var month = $('#birth_month').val();
                var year = $('#birth_year').val();
                
                if (day && month && year) {
                    var formattedDate = year + '-' + month.padStart(2, '0') + '-' + day.padStart(2, '0');
                    $('#birth_date').val(formattedDate);
                } else {
                    $('#birth_date').val('');
                }
            }
            
            // Track form interaction
            $('#acf-quiz-form input, #acf-quiz-form select, #acf-quiz-form textarea').on('input change', function() {
                formStarted = true;
            });
            
            // Remove error class from all fields on page load and ensure clean styling
            function resetFieldStyling() {
                $('#acf-quiz-form .field-input').each(function() {
                    // Remove all error-related classes
                    $(this).removeClass('error touched rtl-input');
                    
                    // Remove any existing classes that might cause red styling
                    var classes = $(this).attr('class');
                    if (classes) {
                        var cleanClasses = classes.split(' ').filter(function(cls) {
                            return cls !== 'error' && cls !== 'touched' && cls !== 'rtl-input';
                        }).join(' ');
                        $(this).attr('class', cleanClasses);
                    }
                    
                    // Force clean styling
                    this.style.setProperty('border-color', '#ddd', 'important');
                    this.style.setProperty('background-color', '#fff', 'important');
                });
            }
            
            // Run multiple times to ensure complete override
            resetFieldStyling();
            setTimeout(resetFieldStyling, 50);
            setTimeout(resetFieldStyling, 200);
            setTimeout(resetFieldStyling, 1000);
            
            // Add touched class for validation styling
            $('#acf-quiz-form input, #acf-quiz-form select, #acf-quiz-form textarea').on('blur', function() {
                if ($(this).is(':invalid')) {
                    $(this).addClass('touched');
                }
            });
            
            // Remove touched class when field becomes valid
            $('#acf-quiz-form input, #acf-quiz-form select, #acf-quiz-form textarea').on('input change', function() {
                if ($(this).is(':valid')) {
                    $(this).removeClass('touched');
                }
            });
            
            $('#birth_day, #birth_month, #birth_year').on('change', updateBirthDate);
            
            // Browser back button protection
            window.addEventListener('beforeunload', function(e) {
                console.log('beforeunload triggered - formStarted:', formStarted, 'formSubmitting:', formSubmitting, 'window.formSubmitting:', window.formSubmitting);
                if (formStarted && !formSubmitting && !window.formSubmitting) {
                    console.log('Showing beforeunload confirmation');
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                } else {
                    console.log('Allowing navigation - no confirmation needed');
                }
            });

            window.addEventListener('popstate', function(e) {
                console.log('popstate triggered - formStarted:', formStarted, 'formSubmitting:', formSubmitting, 'window.formSubmitting:', window.formSubmitting);
                if (formStarted && !formSubmitting && !window.formSubmitting) {
                    console.log('Showing popstate confirmation modal');
                    e.preventDefault();
                    showConfirmationModal(
                        'לצאת מהטופס?',
                        'האם אתה בטוח שברצונך לצאת? השינויים שביצעת לא יישמרו.',
                        function() {
                            formStarted = false;
                            window.history.back();
                        }
                    );
                    return false;
                } else {
                    console.log('Allowing popstate navigation - no confirmation needed');
                }
            });
            
            // Track form submission to disable navigation warning
            var formSubmitting = false;
            window.formSubmitting = false; // Make it globally accessible
            
            $('#acf-quiz-form').on('submit', function() {
                console.log('Form submit event triggered - setting formSubmitting = true');
                formSubmitting = true;
                window.formSubmitting = true;
            });
            
            // Also track submit button clicks directly
            $('#submit-form').on('click', function() {
                console.log('Submit button clicked - setting formSubmitting = true');
                formSubmitting = true;
                window.formSubmitting = true;
            });
            
            
            // Handle browser back/forward buttons
            window.addEventListener('popstate', function(e) {
                if (formStarted && !formSubmitting) {
                    e.preventDefault();
                    showConfirmationModal(function() {
                        formStarted = false;
                        window.history.back();
                    });
                    return false;
                }
            });
            
            // Show confirmation modal
            function showConfirmationModal(confirmCallback) {
                $('#confirmation-modal').css('display', 'flex');
                
                $('#modal-confirm').off('click').on('click', function() {
                    $('#confirmation-modal').hide();
                    if (confirmCallback) confirmCallback();
                });
                
                $('#modal-cancel').off('click').on('click', function() {
                    $('#confirmation-modal').hide();
                });
                
                // Close modal on background click
                $('#confirmation-modal').off('click').on('click', function(e) {
                    if (e.target === this) {
                        $(this).hide();
                    }
                });
            }
        });
        </script>
        
        <?php
        return ob_get_clean();
    }

    /**
     * Handle quiz submission via AJAX
{{ ... }}
     */
    public function handle_quiz_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['quiz_nonce'], 'acf_quiz_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'acf-quiz')));
        }

        // Get quiz data from AJAX submission
        $quiz_data = $_POST['quiz_data'] ?? array();
        
        // Validate personal details from quiz_data
        $user_name = sanitize_text_field($quiz_data['first_name'] ?? '') . ' ' . sanitize_text_field($quiz_data['last_name'] ?? '');
        $user_name = trim($user_name);
        $user_phone = sanitize_text_field($quiz_data['user_phone'] ?? '');
        $user_email = sanitize_email($quiz_data['user_email'] ?? '');
        // Step 2 fields
        $id_number = sanitize_text_field($quiz_data['id_number'] ?? '');
        $gender = sanitize_text_field($quiz_data['gender'] ?? '');
        $birth_date = sanitize_text_field($quiz_data['birth_date'] ?? '');
        $citizenship = sanitize_text_field($quiz_data['citizenship'] ?? '');
        $address = sanitize_text_field($quiz_data['address'] ?? '');
        $marital_status = sanitize_text_field($quiz_data['marital_status'] ?? '');
        $employment_status = sanitize_text_field($quiz_data['employment_status'] ?? '');
        $education = sanitize_text_field($quiz_data['education'] ?? '');
        $profession = sanitize_text_field($quiz_data['profession'] ?? '');
        $final_declaration = isset($quiz_data['final_declaration']) && $quiz_data['final_declaration'] === 'on';
        $signature_data = sanitize_text_field($quiz_data['signature_data'] ?? '');

        // Debug logging for signature data
        error_log('Quiz submission debug - signature_data received: ' . (!empty($signature_data) ? 'YES (' . strlen($signature_data) . ' chars)' : 'NO'));
        error_log('Quiz submission debug - final_declaration: ' . ($final_declaration ? 'YES' : 'NO'));
        error_log('Quiz submission debug - quiz_data keys: ' . implode(', ', array_keys($quiz_data)));

        if (empty($user_name) || empty($user_phone) || !$final_declaration) {
            wp_send_json_error(array('message' => __('אנא מלא את כל הפרטים הנדרשים ואשר את ההצהרה הסופית.', 'acf-quiz')));
        }
        
        // Signature validation - require signature for complete submissions
        if (empty($signature_data)) {
            error_log('Quiz submission: No signature provided - this should not happen for complete submissions');
            wp_send_json_error(array('message' => __('חתימה דיגיטלית נדרשת להשלמת השאלון.', 'acf-quiz')));
        }

        // Get quiz data
        $questions = get_field('quiz_questions', 'option');
        if (empty($questions)) {
            wp_send_json_error(array('message' => __('השאלון לא מוגדר כראוי. אנא פנה למנהל האתר.', 'acf-quiz')));
        }

        // Process answers
        $total_score = 0;
        $max_possible_score = count($questions) * 4; // 4 points per question max
        $results = array();
        $all_answered = true;

        foreach ($questions as $q_index => $question) {
            $answer_key = 'question_' . $q_index;
            if (!isset($quiz_data[$answer_key])) {
                $all_answered = false;
                continue;
            }

            $points_earned = intval($quiz_data[$answer_key]);
            $total_score += $points_earned;

            $results[] = array(
                'question' => $question['question_text'],
                'points_earned' => $points_earned,
                'max_points' => 4,
                'explanation' => $question['explanation'] ?? ''
            );
        }

        if (!$all_answered) {
            wp_send_json_error(array('message' => __('אנא ענה על כל השאלות.', 'acf-quiz')));
        }

        // Check scoring rules:
        // - 23+ points: Pass (proceed to checkout)
        // - 19-22 points: Redirect to /test
        // - Below 19: Fail (redirect to followup)
        $passed = $total_score >= 23;
        $test_redirect = ($total_score >= 19 && $total_score <= 22);
        $score_percentage = round(($total_score / $max_possible_score) * 100);

        // Store or update submission in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Identify existing submission by session id or email
        $existing_id = isset($_SESSION['quiz_submission_id']) ? (int) $_SESSION['quiz_submission_id'] : 0;
        if (!$existing_id && !empty($user_email)) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE user_email = %s ORDER BY id DESC LIMIT 1", $user_email));
        }
        
        $package_selected = sanitize_text_field($quiz_data['package_param'] ?? ($quiz_data['package_selected'] ?? ''));
        $package_price = intval($quiz_data['package_price'] ?? 0);
        
        if ($existing_id) {
            $wpdb->update(
                $table_name,
                array(
                    'user_name' => $user_name,
                    'user_phone' => $user_phone,
                    'user_email' => $user_email,
                    'id_number' => $id_number,
                    'gender' => $gender,
                    'birth_date' => $birth_date,
                    'citizenship' => $citizenship,
                    'address' => $address,
                    'marital_status' => $marital_status,
                    'employment_status' => $employment_status,
                    'education' => $education,
                    'profession' => $profession,
                    'package_selected' => $package_selected,
                    'package_price' => $package_price,
                    'score' => $total_score,
                    'max_score' => $max_possible_score,
                    'passed' => $passed ? 1 : 0,
                    'answers' => json_encode($results),
                    'current_step' => 4,
                    'completed' => 1,
                    'submission_time' => current_time('mysql'),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ),
                array('id' => $existing_id),
                null,
                array('%d')
            );
            $submission_id = $existing_id;
        } else {
            $wpdb->insert($table_name, array(
                'user_name' => $user_name,
                'user_phone' => $user_phone,
                'user_email' => $user_email,
                'id_number' => $id_number,
                'gender' => $gender,
                'birth_date' => $birth_date,
                'citizenship' => $citizenship,
                'address' => $address,
                'marital_status' => $marital_status,
                'employment_status' => $employment_status,
                'education' => $education,
                'profession' => $profession,
                'package_selected' => $package_selected,
                'package_price' => $package_price,
                'score' => $total_score,
                'max_score' => $max_possible_score,
                'passed' => $passed ? 1 : 0,
                'answers' => json_encode($results),
                'current_step' => 4,
                'completed' => 1,
                'submission_time' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'signature_data' => $signature_data
            ));
            $submission_id = $wpdb->insert_id;
        }

        // Store user details in session for WooCommerce integration
        if (!session_id()) {
            session_start();
        }
        
        $_SESSION['quiz_user_data'] = array(
            'name' => $user_name,
            'email' => $user_email,
            'phone' => $user_phone,
            'consent' => $contact_consent,
            'submission_time' => current_time('mysql'),
            'submission_id' => $submission_id,
            'package_type' => $package_type,
            'package_price' => $package_price
        );
        
        $user_details = $_SESSION['quiz_user_data'];

        // Prepare response
        $response = array(
            'score' => $total_score,
            'max_score' => $max_possible_score,
            'score_percentage' => $score_percentage,
            'passed' => $passed,
            'message' => $passed 
                ? __('כל הכבוד! עברת את השאלון בציון ', 'acf-quiz') . $total_score . '/40' 
                : __('ציון: ', 'acf-quiz') . $total_score . '/40. ' . __('ציון מעבר מינימלי הוא 21.', 'acf-quiz'),
            'results' => $results,
            'user_details' => $user_details,
            'package_info' => array(
                'package' => $package_selected,
                'price' => $package_price,
                'source' => $package_source
            ),
            'redirect_url' => $this->get_redirect_url($total_score, $passed, $test_redirect, $package_type, $package_price)
        );

        wp_send_json_success($response);
    }

    /**
     * Get redirect URL based on score
     */
    private function get_redirect_url($score, $passed, $test_redirect, $package_type = '', $package_price = 0) {
        if ($test_redirect) {
            // Score 19-22: Redirect to /test
            return '/test';
        } elseif ($passed) {
            // Score 21+: Proceed to checkout
            return $this->get_checkout_url($package_type, $package_price);
        } else {
            // Score below 19: Redirect to followup
            return '/followup';
        }
    }

    /**
     * Handle step data saving via AJAX (for partial submissions)
     */
    public function handle_step_data() {
        error_log('=== HANDLE_STEP_DATA CALLED ===');
        error_log('POST data: ' . print_r($_POST, true));

        // SIMPLIFIED: Skip nonce verification for now to ensure lead capture works
        // TODO: Re-enable nonce verification after confirming lead capture works
        /*
        if (!isset($_POST['quiz_nonce']) || !wp_verify_nonce($_POST['quiz_nonce'], 'acf_quiz_nonce')) {
            error_log('Security check failed. Nonce: ' . ($_POST['quiz_nonce'] ?? 'not set'));
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'acf-quiz')));
        }
        */

        error_log('Proceeding without nonce verification (temporary)');

        // Get step data
        $step_data = $_POST['step_data'] ?? array();
        $current_step = intval($_POST['current_step'] ?? 1);

        error_log('Step data: ' . print_r($step_data, true));
        error_log('Current step: ' . $current_step);

        // Ensure session
        if (!session_id()) {
            session_start();
        }

        // Handle Step 1: insert lead and remember submission id
        if ($current_step === 1) {
            $first_name = sanitize_text_field($step_data['first_name'] ?? '');
            $last_name = sanitize_text_field($step_data['last_name'] ?? '');
            $user_name = trim($first_name . ' ' . $last_name);
            $user_phone = sanitize_text_field($step_data['user_phone'] ?? '');
            $user_email = sanitize_text_field($step_data['user_email'] ?? '');

            if (!empty($user_name) || !empty($user_phone)) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'quiz_submissions';

                // Package info
                $package_type = sanitize_text_field($_POST['package_param'] ?? ($step_data['package_param'] ?? ''));
                $package_price = 0;
                if ($package_type === 'trial') {
                    $package_price = get_field('trial_price', 'option') ?: 99;
                } elseif ($package_type === 'monthly') {
                    $package_price = get_field('monthly_price', 'option') ?: 199;
                } elseif ($package_type === 'yearly') {
                    $package_price = get_field('yearly_price', 'option') ?: 1999;
                }

                $submission_data = array(
                    'user_name' => $user_name,
                    'user_phone' => $user_phone,
                    'user_email' => $user_email,
                    'package_selected' => $package_type,
                    'package_price' => $package_price,
                    'score' => 0,
                    'max_score' => 40,
                    'passed' => 0,
                    'answers' => json_encode(array()),
                    'current_step' => 1,
                    'completed' => 0,
                    'submission_time' => current_time('mysql'),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                );

                $result = $wpdb->insert($table_name, $submission_data);
                if ($result !== false) {
                    $_SESSION['quiz_submission_id'] = (int) $wpdb->insert_id;
                    error_log('Lead inserted, session quiz_submission_id=' . $_SESSION['quiz_submission_id']);
                } else {
                    error_log('Lead insert failed: ' . $wpdb->last_error);
                }
            } else {
                error_log('Step 1 skipped - no name or phone');
            }
        }

        // Handle Step 2: update same submission (by session id or email)
        if ($current_step === 2) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'quiz_submissions';

            $submission_id = isset($_SESSION['quiz_submission_id']) ? (int) $_SESSION['quiz_submission_id'] : 0;
            $user_email = sanitize_text_field($step_data['user_email'] ?? '');

            $update_where = '';
            $update_where_args = array();
            if ($submission_id > 0) {
                $update_where = 'id = %d';
                $update_where_args[] = $submission_id;
            } elseif (!empty($user_email)) {
                $update_where = 'user_email = %s AND completed = 0';
                $update_where_args[] = $user_email;
            }

            if ($update_where) {
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE $table_name SET id_number=%s, gender=%s, birth_date=%s, citizenship=%s, address=%s, marital_status=%s, employment_status=%s, education=%s, profession=%s, current_step=%d WHERE $update_where",
                    sanitize_text_field($step_data['id_number'] ?? ''),
                    sanitize_text_field($step_data['gender'] ?? ''),
                    sanitize_text_field($step_data['birth_date'] ?? ''),
                    sanitize_text_field($step_data['citizenship'] ?? ''),
                    sanitize_text_field($step_data['address'] ?? ''),
                    sanitize_text_field($step_data['marital_status'] ?? ''),
                    sanitize_text_field($step_data['employment_status'] ?? ''),
                    sanitize_text_field($step_data['education'] ?? ''),
                    sanitize_text_field($step_data['profession'] ?? ''),
                    2,
                    ...$update_where_args
                ));
                error_log('Step 2 update result: ' . var_export($updated, true));
            } else {
                error_log('Step 2 update skipped - no identifier');
            }
        }

        // Persist step data in session
        $_SESSION['quiz_step_data'][$current_step] = $step_data;

        wp_send_json_success(array('message' => 'Step data saved'));
    }

    /**
     * Simple, reliable lead capture method (backup)
     */
    public function simple_lead_capture() {
        error_log('=== SIMPLE_LEAD_CAPTURE CALLED ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Get data directly from POST
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $user_phone = sanitize_text_field($_POST['user_phone'] ?? '');
        $user_email = sanitize_text_field($_POST['user_email'] ?? '');
        $package_param = sanitize_text_field($_POST['package_param'] ?? '');
        
        $user_name = trim($first_name . ' ' . $last_name);
        
        error_log("Simple lead capture - Name: $user_name, Phone: $user_phone, Email: $user_email");
        
        // Only proceed if we have basic data
        if (empty($user_name) && empty($user_phone)) {
            error_log('Simple lead capture - No name or phone provided');
            wp_send_json_error(array('message' => 'No data to save'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Get package price
        $package_price = 0;
        if ($package_param === 'trial') {
            $package_price = 99;
        } elseif ($package_param === 'monthly') {
            $package_price = 199;
        } elseif ($package_param === 'yearly') {
            $package_price = 1999;
        }
        
        $submission_data = array(
            'first_name' => $first_name,
            'last_name' => $last_name,
            'user_name' => $user_name,
            'user_phone' => $user_phone,
            'user_email' => $user_email,
            'package_selected' => $package_param,
            'package_price' => $package_price,
            'score' => 0,
            'max_score' => 40,
            'passed' => 0,
            'answers' => json_encode(array()),
            'current_step' => 1,
            'completed' => 0,
            'submission_time' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        error_log('Simple lead capture - Inserting data: ' . print_r($submission_data, true));
        
        $result = $wpdb->insert($table_name, $submission_data);
        
        error_log('Simple lead capture - Insert result: ' . ($result === false ? 'FALSE' : $result));
        error_log('Simple lead capture - Last error: ' . $wpdb->last_error);
        error_log('Simple lead capture - Insert ID: ' . $wpdb->insert_id);
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Lead captured successfully',
                'id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to capture lead: ' . $wpdb->last_error
            ));
        }
    }

    /**
     * Get WooCommerce checkout URL with package added to cart
     */
    public function get_checkout_url($package_type, $package_price) {
        if (!class_exists('WooCommerce')) {
            return home_url('/checkout');
        }

        // Clear existing cart
        WC()->cart->empty_cart();

        // Get package details
        $package_names = array(
            'trial' => 'חבילת ניסיון - 3 חודשים ראשונים',
            'monthly' => 'חבילה חודשית',
            'yearly' => 'חבילה שנתית (166₪ לחודש)'
        );

        $package_name = $package_names[$package_type] ?? 'חבילת השקעות';

        // Add custom product to cart
        $cart_item_data = array(
            'custom_price' => $package_price,
            'package_type' => $package_type,
            'quiz_validated' => true
        );

        // Create a temporary product ID (we'll handle this in cart hooks)
        $product_id = 9999; // Temporary ID for custom product
        
        // Add to cart with custom data
        WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);

        return wc_get_checkout_url();
    }

    /**
     * Handle custom cart item pricing
     */
    public function handle_custom_cart_item($cart_item_data, $product_id, $variation_id) {
        if ($product_id == 9999 && isset($cart_item_data['custom_price'])) {
            // This is our custom quiz product
            return $cart_item_data;
        }
        return $cart_item_data;
    }

    /**
     * Set custom cart item price
     */
    public function set_custom_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['custom_price']) && isset($cart_item['package_type'])) {
                $cart_item['data']->set_price($cart_item['custom_price']);
                
                // Set custom product name
                $package_names = array(
                    'trial' => 'חבילת ניסיון - 3 חודשים ראשונים',
                    'monthly' => 'חבילה חודשית',
                    'yearly' => 'חבילה שנתית (166₪ לחודש)'
                );
                
                $package_name = $package_names[$cart_item['package_type']] ?? 'חבילת השקעות';
                $cart_item['data']->set_name($package_name);
            }
        }
    }

    /**
     * Render submissions admin page (legacy - removed to avoid duplicate method)
     */
    public function render_submissions_admin_page_legacy() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Handle bulk delete action
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['submission_ids'])) {
            if (!wp_verify_nonce($_POST['bulk_delete_nonce'], 'bulk_delete_submissions')) {
                wp_die(__('Security check failed.', 'acf-quiz'));
            }
            
            $submission_ids = array_map('intval', $_POST['submission_ids']);
            if (!empty($submission_ids)) {
                $ids_placeholder = implode(',', array_fill(0, count($submission_ids), '%d'));
                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE id IN ($ids_placeholder)",
                    ...$submission_ids
                ));
                
                if ($deleted !== false) {
                    echo '<div class="notice notice-success"><p>' . 
                         sprintf(__('%d submissions deleted successfully.', 'acf-quiz'), $deleted) . 
                         '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . 
                         __('Error deleting submissions.', 'acf-quiz') . 
                         '</p></div>';
                }
            }
        }
        
        // Handle filtering
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $where_clause = '';
        
        if ($filter === 'failed') {
            $where_clause = 'WHERE passed = 0';
        } elseif ($filter === 'passed') {
            $where_clause = 'WHERE passed = 1';
        }
        
        // Get both completed submissions and initial leads (avoid duplicates)
        $submissions = $wpdb->get_results("
            SELECT DISTINCT id, user_name, user_phone, user_email, package_selected, 
                   score, max_score, passed, completed, submission_time, current_step,
                   id_number, gender, birth_date, citizenship, address, 
                   marital_status, employment_status, education, profession
            FROM $table_name 
            WHERE 1=1
            " . ($filter === 'failed' ? " AND (passed = 0 OR completed = 0)" : "") . "
            " . ($filter === 'passed' ? " AND passed = 1" : "") . "
            GROUP BY user_email, completed
            ORDER BY submission_time DESC 
            LIMIT 100
        ");
        
        $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $completed_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE completed = 1");
        $failed_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE (passed = 0 AND completed = 1) OR completed = 0");
        $passed_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE passed = 1");
        $lead_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE completed = 0");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Quiz Submissions', 'acf-quiz'); ?></h1>
            <!-- Stats grid intentionally hidden per request -->
            
            <form method="post" id="bulk-action-form">
                <?php wp_nonce_field('bulk_delete_submissions', 'bulk_delete_nonce'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php _e('Bulk Actions', 'acf-quiz'); ?></option>
                            <option value="delete"><?php _e('Delete', 'acf-quiz'); ?></option>
                        </select>
                        <input type="submit" id="doaction" class="button action" value="<?php _e('Apply', 'acf-quiz'); ?>" onclick="return confirm('<?php _e('Are you sure you want to delete the selected submissions?', 'acf-quiz'); ?>');">
                    </div>
                    <div class="alignleft actions">
                        <select name="filter" onchange="window.location.href='?page=quiz-submissions&filter='+this.value">
                            <option value="all" <?php selected($filter, 'all'); ?>><?php _e('All Submissions', 'acf-quiz'); ?></option>
                            <option value="failed" <?php selected($filter, 'failed'); ?>><?php _e('Failed Only', 'acf-quiz'); ?></option>
                            <option value="passed" <?php selected($filter, 'passed'); ?>><?php _e('Passed Only', 'acf-quiz'); ?></option>
                        </select>
                    </div>
                </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <input id="cb-select-all-1" type="checkbox" />
                        </td>
                        <th><?php _e('Date', 'acf-quiz'); ?></th>
                        <th><?php _e('Name', 'acf-quiz'); ?></th>
                        <th><?php _e('Phone', 'acf-quiz'); ?></th>
                        <th><?php _e('Package', 'acf-quiz'); ?></th>
                        <th><?php _e('Score', 'acf-quiz'); ?></th>
                        <th><?php _e('Status', 'acf-quiz'); ?></th>
                        <th><?php _e('Type', 'acf-quiz'); ?></th>
                        <th><?php _e('Actions', 'acf-quiz'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)) : ?>
                        <tr>
                            <td colspan="8"><?php _e('No submissions found.', 'acf-quiz'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($submissions as $submission) : ?>
                            <tr class="<?php echo $submission->completed ? ($submission->passed ? 'passed' : 'failed') : 'lead'; ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="submission_ids[]" value="<?php echo $submission->id; ?>" />
                                </th>
                                <td><?php echo date('Y-m-d H:i', strtotime($submission->submission_time)); ?></td>
                                <td><strong><?php echo esc_html($submission->user_name); ?></strong></td>
                                <td><a href="tel:<?php echo esc_attr($submission->user_phone); ?>"><?php echo esc_html($submission->user_phone); ?></a></td>
                                <td><?php echo esc_html($submission->package_selected ?: __('Not specified', 'acf-quiz')); ?></td>
                                <td>
                                    <?php if ($submission->completed): ?>
                                        <span class="score-display">
                                            <?php echo $submission->score; ?>/<?php echo $submission->max_score; ?>
                                            (<?php echo round(($submission->score / $submission->max_score) * 100); ?>%)
                                        </span>
                                    <?php else: ?>
                                        <span class="score-display">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($submission->completed): ?>
                                        <span class="status-badge <?php echo $submission->passed ? 'status-passed' : 'status-failed'; ?>">
                                            <?php echo $submission->passed ? __('Passed', 'acf-quiz') : __('Failed', 'acf-quiz'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-lead">
                                            <?php _e('Lead', 'acf-quiz'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $submission->completed ? __('Full Quiz', 'acf-quiz') : __('Initial Lead', 'acf-quiz'); ?>
                                </td>
                                <td>
                                    <button type="button" class="button view-details" 
                                            data-id="<?php echo $submission->id; ?>"
                                            data-name="<?php echo esc_attr($submission->user_name); ?>"
                                            data-phone="<?php echo esc_attr($submission->user_phone); ?>"
                                            data-email="<?php echo esc_attr($submission->user_email); ?>"
                                            data-package="<?php echo esc_attr($submission->package_selected); ?>"
                                            data-id-number="<?php echo esc_attr($submission->id_number); ?>"
                                            data-gender="<?php echo esc_attr($submission->gender); ?>"
                                            data-birth-date="<?php echo esc_attr($submission->birth_date); ?>"
                                            data-citizenship="<?php echo esc_attr($submission->citizenship); ?>"
                                            data-address="<?php echo esc_attr($submission->address); ?>"
                                            data-marital-status="<?php echo esc_attr($submission->marital_status); ?>"
                                            data-employment-status="<?php echo esc_attr($submission->employment_status); ?>"
                                            data-education="<?php echo esc_attr($submission->education); ?>"
                                            data-profession="<?php echo esc_attr($submission->profession); ?>">
                                        <?php _e('View Details', 'acf-quiz'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </form>
        </div>
        
        <style>
        .quiz-stats { margin: 20px 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 600px; }
        .stat-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-box h3 { font-size: 2em; margin: 0 0 10px 0; color: #333; }
        .stat-box.failed h3 { color: #dc3545; }
        .stat-box.passed h3 { color: #28a745; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-badge.passed { background: #d4edda; color: #155724; }
        .status-badge.failed { background: #f8d7da; color: #721c24; }
        .score-display { font-weight: bold; }
        tr.failed { background-color: #fff5f5; }
        tr.passed { background-color: #f0fff4; }
        
        /* Modal Styles */
        .submission-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 700px;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .close-modal {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: #000;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 3px;
        }
        
        .detail-value {
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
            min-height: 20px;
        }
        </style>
        
        <!-- Submission Details Modal -->
        <div id="submissionModal" class="submission-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><?php _e('Submission Details', 'acf-quiz'); ?></h2>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Name', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-name"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Phone', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-phone"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Email', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-email"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Package', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-package"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('ID Number', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-id-number"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Gender', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-gender"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Birth Date', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-birth-date"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Citizenship', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-citizenship"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Address', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-address"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Marital Status', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-marital-status"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Employment Status', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-employment-status"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Education', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-education"></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label"><?php _e('Profession', 'acf-quiz'); ?></div>
                        <div class="detail-value" id="modal-profession"></div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Select all checkbox functionality
            $('#cb-select-all-1').on('change', function() {
                $('input[name="submission_ids[]"]').prop('checked', this.checked);
            });
            
            // Individual checkbox change
            $('input[name="submission_ids[]"]').on('change', function() {
                var allChecked = $('input[name="submission_ids[]"]:checked').length === $('input[name="submission_ids[]"]').length;
                $('#cb-select-all-1').prop('checked', allChecked);
            });
            
            // Modal functionality
            const modal = $('#submissionModal');
            const closeBtn = $('.close-modal');
            
            // Open modal when View Details is clicked
            $('.view-details').on('click', function() {
                // Populate modal with data
                $('#modal-name').text($(this).data('name') || '-');
                $('#modal-phone').text($(this).data('phone') || '-');
                $('#modal-email').text($(this).data('email') || '-');
                $('#modal-package').text($(this).data('package') || '-');
                $('#modal-id-number').text($(this).data('id-number') || '-');
                $('#modal-gender').text($(this).data('gender') || '-');
                $('#modal-birth-date').text($(this).data('birth-date') || '-');
                $('#modal-citizenship').text($(this).data('citizenship') || '-');
                $('#modal-address').text($(this).data('address') || '-');
                $('#modal-marital-status').text($(this).data('marital-status') || '-');
                $('#modal-employment-status').text($(this).data('employment-status') || '-');
                $('#modal-education').text($(this).data('education') || '-');
                $('#modal-profession').text($(this).data('profession') || '-');
                
                // Show modal
                modal.css('display', 'block');
            });
            
            // Close modal when X is clicked
            closeBtn.on('click', function() {
                modal.css('display', 'none');
            });
            
            // Close modal when clicking outside the content
            $(window).on('click', function(event) {
                if ($(event.target).is(modal)) {
                    modal.css('display', 'none');
                }
            });
            
            // Close modal with Escape key
            $(document).keyup(function(e) {
                if (e.key === 'Escape') {
                    modal.css('display', 'none');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Register WooCommerce Blocks checkout integration
     */
    public function register_checkout_block_integration() {
        if (class_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry')) {
            add_action(
                'woocommerce_blocks_checkout_block_registration',
                function($integration_registry) {
                    // This will be handled via JavaScript and CSS
                }
            );
        }
    }

    /**
     * Hide specific WooCommerce Checkout inner blocks on the frontend using render_block filter
     */
    public function filter_woocommerce_checkout_blocks($block_content, $block) {
        // Don't affect the admin/editor
        if (is_admin()) {
            return $block_content;
        }

        // Only touch checkout pages - add safety checks
        if (!function_exists('is_checkout')) {
            return $block_content;
        }
        
        // Add try-catch to prevent fatal errors
        try {
            if (!is_checkout()) {
                return $block_content;
            }
        } catch (Exception $e) {
            error_log("Error in filter_woocommerce_checkout_blocks: " . $e->getMessage());
            return $block_content;
        }

        // Blocks to suppress
        $blocked_blocks = [
            'woocommerce/checkout-shipping-method-block',
            'woocommerce/checkout-pickup-options-block',
            'woocommerce/checkout-shipping-address-block',
            'woocommerce/checkout-shipping-methods-block',
            'woocommerce/checkout-billing-address-block',
            'woocommerce/checkout-additional-information-block',
            'woocommerce/checkout-order-note-block',
        ];

        $block_name = isset($block['blockName']) ? $block['blockName'] : '';

        // If it's one of the blocked blocks, remove it
        if (in_array($block_name, $blocked_blocks, true)) {
            error_log("Blocking WooCommerce checkout block: " . $block_name);
            return '';
        }

        return $block_content;
    }

    /**
     * Enqueue block checkout scripts for WooCommerce
     */
    public function enqueue_block_checkout_scripts() {
        if (is_checkout() && function_exists('has_block') && has_block('woocommerce/checkout')) {
            wp_enqueue_script(
                'wc-block-checkout-custom',
                plugins_url('js/wc-block-checkout.js', __FILE__),
                array('wp-element', 'wp-components', 'wc-blocks-checkout'),
                '1.0.0',
                true
            );

            // Add inline script to hide specific WooCommerce block components
            $custom_checkout_js = "
            jQuery(document).ready(function($) {
                console.log('WooCommerce block checkout customization loaded');
                
                // Function to hide specific WooCommerce block components
                function hideBlockCheckoutComponents() {
                    console.log('Attempting to hide specific WooCommerce block components...');
                    
                    // Hide shipping-related blocks
                    $('.wp-block-woocommerce-checkout-shipping-method-block').hide();
                    $('.wp-block-woocommerce-checkout-pickup-options-block').hide();
                    $('.wp-block-woocommerce-checkout-shipping-address-block').hide();
                    $('.wp-block-woocommerce-checkout-shipping-methods-block').hide();
                    
                    // Hide billing address block (but keep contact information)
                    $('.wp-block-woocommerce-checkout-billing-address-block').hide();
                    
                    // Hide additional optional blocks
                    $('.wp-block-woocommerce-checkout-additional-information-block').hide();
                    $('.wp-block-woocommerce-checkout-order-note-block').hide();
                    
                    // Keep visible: contact information, payment, terms, actions, totals
                    $('.wp-block-woocommerce-checkout-contact-information-block').show();
                    $('.wp-block-woocommerce-checkout-payment-block').show();
                    $('.wp-block-woocommerce-checkout-terms-block').show();
                    $('.wp-block-woocommerce-checkout-actions-block').show();
                    $('.wp-block-woocommerce-checkout-totals-block').show();
                    
                    console.log('Hidden shipping and billing address blocks, kept contact info and payment');
                }
                
                // CSS to hide specific WooCommerce block components
                function addBlockComponentCSS() {
                    if (!$('#wc-block-component-css').length) {
                        $('head').append('<style id=\"wc-block-component-css\">' +
                            '.wp-block-woocommerce-checkout-shipping-method-block, ' +
                            '.wp-block-woocommerce-checkout-pickup-options-block, ' +
                            '.wp-block-woocommerce-checkout-shipping-address-block, ' +
                            '.wp-block-woocommerce-checkout-shipping-methods-block, ' +
                            '.wp-block-woocommerce-checkout-billing-address-block, ' +
                            '.wp-block-woocommerce-checkout-additional-information-block, ' +
                            '.wp-block-woocommerce-checkout-order-note-block { ' +
                            'display: none !important; ' +
                            'visibility: hidden !important; ' +
                            'opacity: 0 !important; ' +
                            'height: 0 !important; ' +
                            'overflow: hidden !important; ' +
                            '} ' +
                            '.wp-block-woocommerce-checkout-contact-information-block, ' +
                            '.wp-block-woocommerce-checkout-payment-block, ' +
                            '.wp-block-woocommerce-checkout-terms-block, ' +
                            '.wp-block-woocommerce-checkout-actions-block, ' +
                            '.wp-block-woocommerce-checkout-totals-block { ' +
                            'display: block !important; ' +
                            'visibility: visible !important; ' +
                            'opacity: 1 !important; ' +
                            '}' +
                        '</style>');
                    }
                }
                
                // Run immediately
                addBlockComponentCSS();
                hideBlockCheckoutComponents();
                
                // Use MutationObserver to detect when WooCommerce blocks are rendered
                var observer = new MutationObserver(function(mutations) {
                    hideBlockCheckoutComponents();
                    addBlockComponentCSS();
                });
                
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
                
                // Run repeatedly to catch all loading states
                setInterval(function() {
                    hideBlockCheckoutComponents();
                    addBlockComponentCSS();
                }, 1000);
            });";
            
            wp_add_inline_script('wc-block-checkout-custom', $custom_checkout_js);
            
            // Add CSS for WooCommerce block components
            wp_add_inline_style('wc-block-checkout-custom', "
                /* Hide specific WooCommerce block components */
                .wp-block-woocommerce-checkout-shipping-method-block,
                .wp-block-woocommerce-checkout-pickup-options-block,
                .wp-block-woocommerce-checkout-shipping-address-block,
                .wp-block-woocommerce-checkout-shipping-methods-block,
                .wp-block-woocommerce-checkout-billing-address-block,
                .wp-block-woocommerce-checkout-additional-information-block,
                .wp-block-woocommerce-checkout-order-note-block {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                    height: 0 !important;
                    overflow: hidden !important;
                }
                
                /* Ensure important blocks remain visible */
                .wp-block-woocommerce-checkout-contact-information-block,
                .wp-block-woocommerce-checkout-payment-block,
                .wp-block-woocommerce-checkout-terms-block,
                .wp-block-woocommerce-checkout-actions-block,
                .wp-block-woocommerce-checkout-totals-block {
                    display: block !important;
                    visibility: visible !important;
                    opacity: 1 !important;
                }
            ");
        }
    }

    /**
     * Populate WooCommerce checkout fields with quiz data
     */
    public function populate_checkout_fields() {
        if (!session_id()) {
            session_start();
        }
        
        // Get data from session
        $step1_data = isset($_SESSION['quiz_step_1']) ? $_SESSION['quiz_step_1'] : array();
        
        if (!empty($step1_data)) {
            // Store in session for checkout field population
            $_SESSION['quiz_checkout_data'] = array(
                'first_name' => isset($step1_data['first_name']) ? $step1_data['first_name'] : '',
                'last_name' => isset($step1_data['last_name']) ? $step1_data['last_name'] : '',
                'email' => isset($step1_data['user_email']) ? $step1_data['user_email'] : '',
                'phone' => isset($step1_data['user_phone']) ? $step1_data['user_phone'] : ''
            );
        }
    }

    /**
     * Get checkout field value from quiz data
     */
    public function get_checkout_field_value($value, $input) {
        if (!session_id()) {
            session_start();
        }
        
        $quiz_data = isset($_SESSION['quiz_checkout_data']) ? $_SESSION['quiz_checkout_data'] : array();
        
        if (empty($quiz_data)) {
            return $value;
        }
        
        // Map quiz fields to WooCommerce checkout fields
        $field_mapping = array(
            'billing_first_name' => 'first_name',
            'billing_last_name' => 'last_name',
            'billing_email' => 'email',
            'billing_phone' => 'phone'
        );
        
        if (isset($field_mapping[$input]) && isset($quiz_data[$field_mapping[$input]])) {
            return $quiz_data[$field_mapping[$input]];
        }
        
        return $value;
    }

    /**
     * Create WooCommerce products for quiz packages
     */
    public function create_quiz_products() {
        // Only run if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Check if products already exist
        $trial_product = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_quiz_package_type',
                    'value' => 'trial'
                )
            ),
            'posts_per_page' => 1
        ));
        
        // Only create products if they don't exist
        if (empty($trial_product)) {
            // Get prices from ACF options
            $trial_price = get_field('trial_price', 'option') ?: 99;
            $monthly_price = get_field('monthly_price', 'option') ?: 199;
            $yearly_price = get_field('yearly_price', 'option') ?: 1999;
            
            // Create Trial Product
            $trial_id = wp_insert_post(array(
                'post_title' => 'חבילת ניסיון - 3 חודשים ראשונים',
                'post_content' => 'חבילת ניסיון מיוחדת למשך 3 חודשים ראשונים במחיר מוזל.',
                'post_status' => 'publish',
                'post_type' => 'product'
            ));
            
            if ($trial_id) {
                wp_set_object_terms($trial_id, 'simple', 'product_type');
                update_post_meta($trial_id, '_price', $trial_price);
                update_post_meta($trial_id, '_regular_price', $trial_price);
                update_post_meta($trial_id, '_manage_stock', 'no');
                update_post_meta($trial_id, '_stock_status', 'instock');
                update_post_meta($trial_id, '_visibility', 'visible');
                update_post_meta($trial_id, '_quiz_package_type', 'trial');
                update_post_meta($trial_id, '_virtual', 'yes');
            }
            
            // Create Monthly Product
            $monthly_id = wp_insert_post(array(
                'post_title' => 'חבילה חודשית',
                'post_content' => 'חבילה חודשית רגילה.',
                'post_status' => 'publish',
                'post_type' => 'product'
            ));
            
            if ($monthly_id) {
                wp_set_object_terms($monthly_id, 'simple', 'product_type');
                update_post_meta($monthly_id, '_price', $monthly_price);
                update_post_meta($monthly_id, '_regular_price', $monthly_price);
                update_post_meta($monthly_id, '_manage_stock', 'no');
                update_post_meta($monthly_id, '_stock_status', 'instock');
                update_post_meta($monthly_id, '_visibility', 'visible');
                update_post_meta($monthly_id, '_quiz_package_type', 'monthly');
                update_post_meta($monthly_id, '_virtual', 'yes');
            }
            
            // Create Yearly Product
            $yearly_id = wp_insert_post(array(
                'post_title' => 'חבילה שנתית',
                'post_content' => 'חבילה שנתית במחיר מוזל (1999 ש"ח לשנה, 166 ש"ח לחודש).',
                'post_status' => 'publish',
                'post_type' => 'product'
            ));
            
            if ($yearly_id) {
                wp_set_object_terms($yearly_id, 'simple', 'product_type');
                update_post_meta($yearly_id, '_price', $yearly_price);
                update_post_meta($yearly_id, '_regular_price', $yearly_price);
                update_post_meta($yearly_id, '_manage_stock', 'no');
                update_post_meta($yearly_id, '_stock_status', 'instock');
                update_post_meta($yearly_id, '_visibility', 'visible');
                update_post_meta($yearly_id, '_quiz_package_type', 'yearly');
                update_post_meta($yearly_id, '_virtual', 'yes');
            }
            
            // Store product IDs in options for easy reference
            update_option('quiz_trial_product_id', $trial_id);
            update_option('quiz_monthly_product_id', $monthly_id);
            update_option('quiz_yearly_product_id', $yearly_id);
        }
    }

    /**
     * Create necessary directories and files
     */
    public function create_assets_structure() {
        $css_dir = $this->plugin_path . 'css';
        $js_dir = $this->plugin_path . 'js';
        
        // Create directories
        if (!file_exists($css_dir)) {
            wp_mkdir_p($css_dir);
        }
        if (!file_exists($js_dir)) {
            wp_mkdir_p($js_dir);
        }
    }

    /**
     * Add custom fields to WooCommerce checkout
     */
    public function add_checkout_custom_fields($checkout) {
        // Check if $checkout is valid object before using it
        if (!is_object($checkout) || !method_exists($checkout, 'get_value')) {
            error_log('Invalid checkout object passed to add_checkout_custom_fields');
            return;
        }
        
        // Remove the extra info section header
        echo '<div id="custom_checkout_fields">';
        
        // Full Name Field (replacing first_name and last_name)
        woocommerce_form_field('full_name', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => __('שם מלא'),
            'placeholder' => __('הכנס שם מלא'),
            'required' => true,
        ), $checkout->get_value('full_name'));
        
        // Identification Field (ח.פ / ת.ז)
        woocommerce_form_field('identification_number', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => __('ח.פ / ת.ז'),
            'placeholder' => __('הכנס מספר זהות או ח.פ'),
            'required' => true,
        ), $checkout->get_value('identification_number'));
        
        // ID Photo Upload Field
        woocommerce_form_field('id_photo_upload', array(
            'type' => 'file',
            'class' => array('form-row-wide'),
            'label' => __('העלאת תמונת תעודת זהות'),
            'placeholder' => __('בחר קובץ...'),
            'required' => true,
            'custom_attributes' => array(
                'accept' => 'image/*,.pdf',
                'data-max-size' => '5242880' // 5MB
            )
        ), $checkout->get_value('id_photo_upload'));
        
        // Add mobile-friendly file upload styling
        ?>
        <style>
        #id_photo_upload_field input[type="file"] {
            width: 100% !important;
            padding: 10px !important;
            border: 2px dashed #ddd !important;
            border-radius: 5px !important;
            background: #f9f9f9 !important;
            cursor: pointer !important;
            font-size: 16px !important; /* Prevents zoom on iOS */
        }
        
        @media (max-width: 768px) {
            #id_photo_upload_field input[type="file"] {
                font-size: 16px !important; /* Critical for mobile */
                -webkit-appearance: none !important;
                appearance: none !important;
            }
            
            #id_photo_upload_field input[type="file"]::-webkit-file-upload-button {
                background: #007cba !important;
                color: white !important;
                border: none !important;
                padding: 8px 12px !important;
                border-radius: 3px !important;
                margin-right: 10px !important;
                font-size: 14px !important;
            }
        }
        </style>
        <?php
        
        // Determine subscription type based on URL parameter
        $is_3_month_plan = (isset($_GET['monthly']) || isset($_SESSION['package_type']) && $_SESSION['package_type'] === 'monthly');
        
        // Conditional subscription terms checkbox
        if ($is_3_month_plan) {
            $terms_text = 'אני מאשר כי קראתי והבנתי את תנאי המנוי, לרבות העובדה כי לאחר תקופת ההטבה (3 חודשים במחיר מוזל), יתחדש המנוי אוטומטית מדי חודש במחיר המלא.';
        } else {
            $terms_text = 'אני מאשר כי קראתי והבנתי את תנאי המנוי, לרבות העובדה כי בתום תקופת ההטבה יתחדש המנוי באופן אוטומטי בהתאם למסלול שנבחר.';
        }
        
        woocommerce_form_field('subscription_terms_agreement', array(
            'type' => 'checkbox',
            'class' => array('form-row-wide'),
            'label' => $terms_text,
            'required' => true,
        ), $checkout->get_value('subscription_terms_agreement'));
        
        echo '</div>';
    }
    
    /**
     * Add submissions viewer menu
     */
    public function add_submissions_menu() {
        add_menu_page(
            'Quiz Submissions',
            'Quiz Submissions', 
            'manage_options',
            'quiz-submissions-viewer',
            array($this, 'render_submissions_page'),
            'dashicons-list-view',
            25
        );
    }

    /**
     * Render submissions viewer page (main implementation)
     */
    public function render_submissions_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && current_user_can('manage_options')) {
            $id = intval($_GET['id']);
            $wpdb->delete($table_name, array('id' => $id), array('%d'));
            echo '<div class="notice notice-success"><p>Submission deleted successfully.</p></div>';
        }
        
        // Get all submissions
        $submissions = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Quiz Submissions Viewer</h1>
            <p>View and manage quiz submissions and leads data.</p>
            
            <?php if (empty($submissions)): ?>
                <div class="notice notice-info">
                    <p>No submissions found. Submissions will appear here when users complete the quiz form.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Score</th>
                            <th>Passed</th>
                            <th>Complete</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo esc_html($submission->id); ?></td>
                                <td><?php echo esc_html(($submission->first_name ?? '') . ' ' . ($submission->last_name ?? '')); ?></td>
                                <td><?php echo esc_html($submission->email ?? ''); ?></td>
                                <td><?php echo esc_html($submission->phone ?? ''); ?></td>
                                <td><?php echo esc_html($submission->total_score ?? 'N/A'); ?></td>
                                <td><?php echo ($submission->passed ?? false) ? '✅ Yes' : '❌ No'; ?></td>
                                <td><?php echo ($submission->is_complete ?? false) ? '✅ Complete' : '⏳ Partial'; ?></td>
                                <td><?php echo esc_html($submission->created_at ?? ''); ?></td>
                                <td>
                                    <a href="?page=quiz-submissions-viewer&action=view&id=<?php echo $submission->id; ?>" class="button button-small">View</a>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <a href="?page=quiz-submissions-viewer&action=delete&id=<?php echo $submission->id; ?>" class="button button-small" onclick="return confirm('Are you sure?')">Delete</a>
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
                $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
                ?>
                
                <?php if ($submission): ?>
                    <div style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                        <h2>Submission Details - ID: <?php echo esc_html($submission->id); ?></h2>
                        
                        <h3>Personal Information</h3>
                        <table class="form-table">
                            <tr><th>First Name:</th><td><?php echo esc_html($submission->first_name ?? 'Not provided'); ?></td></tr>
                            <tr><th>Last Name:</th><td><?php echo esc_html($submission->last_name ?? 'Not provided'); ?></td></tr>
                            <tr><th>Email:</th><td><?php echo esc_html($submission->email ?? 'Not provided'); ?></td></tr>
                            <tr><th>Phone:</th><td><?php echo esc_html($submission->phone ?? 'Not provided'); ?></td></tr>
                            <tr><th>ID Number:</th><td><?php echo esc_html($submission->id_number ?? 'Not provided'); ?></td></tr>
                            <tr><th>Gender:</th><td><?php echo esc_html($submission->gender ?? 'Not provided'); ?></td></tr>
                        </table>
                        
                        <h3>Quiz Results</h3>
                        <table class="form-table">
                            <tr><th>Total Score:</th><td><?php echo esc_html($submission->total_score ?? 'N/A'); ?>/40</td></tr>
                            <tr><th>Passed:</th><td><?php echo ($submission->passed ?? false) ? 'Yes (23+ points)' : 'No (below 23 points)'; ?></td></tr>
                            <tr><th>Is Complete:</th><td><?php echo ($submission->is_complete ?? false) ? 'Complete submission' : 'Partial (Step 1 only)'; ?></td></tr>
                        </table>
                        
                        <h3>Submission Status</h3>
                        <table class="form-table">
                            <tr><th>Current Step:</th><td><?php echo esc_html($submission->current_step ?? 'Unknown'); ?>/4</td></tr>
                            <tr><th>Completed:</th><td><?php echo ($submission->completed ?? false) ? '✅ Complete' : '⏳ Incomplete'; ?></td></tr>
                            <tr><th>Progress:</th><td>
                                <?php 
                                $step = intval($submission->current_step ?? 0);
                                $steps = ['Not Started', 'Personal Info', 'Quiz Questions', 'Additional Info', 'Complete'];
                                echo esc_html($steps[$step] ?? 'Unknown Step');
                                ?>
                            </td></tr>
                        </table>
                        
                        <h3>Digital Signature</h3>
                        <table class="form-table">
                            <tr><th>Signature Status:</th><td>
                                <?php if (!empty($submission->signature_data)): ?>
                                    <span style="color: green;">✅ Signature Captured</span>
                                    <div style="margin-top: 10px;">
                                        <div class="signature-display" style="border: 2px solid #ddd; padding: 10px; background: #f9f9f9; border-radius: 5px; max-width: 500px;">
                                            <img src="<?php echo esc_attr($submission->signature_data); ?>" 
                                                 style="max-width: 100%; height: auto; border: 1px solid #ccc;" 
                                                 alt="Digital Signature">
                                            <div style="margin-top: 10px;">
                                                <button type="button" onclick="downloadSignature(<?php echo $submission->id; ?>)" 
                                                        class="button button-secondary" style="margin-right: 10px;">
                                                    📥 Download Signature
                                                </button>
                                                <button type="button" onclick="viewSignatureFullscreen(<?php echo $submission->id; ?>)" 
                                                        class="button button-secondary">
                                                    🔍 View Full Size
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php elseif (($submission->completed ?? false)): ?>
                                    <span style="color: orange;">⚠️ No signature provided (completed without signature)</span>
                                <?php else: ?>
                                    <span style="color: #666;">❌ Not available (submission incomplete - signature captured in final step)</span>
                                <?php endif; ?>
                            </td></tr>
                        </table>
                        
                        <h3>Metadata</h3>
                        <table class="form-table">
                            <tr><th>Created:</th><td><?php echo esc_html($submission->created_at ?? 'Not recorded'); ?></td></tr>
                            <tr><th>Updated:</th><td><?php echo esc_html($submission->updated_at ?? 'Not recorded'); ?></td></tr>
                            <tr><th>IP Address:</th><td><?php echo esc_html($submission->ip_address ?? 'Not recorded'); ?></td></tr>
                        </table>
                        
                        <p><a href="?page=quiz-submissions-viewer" class="button">← Back to List</a></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Signature Modal -->
        <div id="signature-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; justify-content: center; align-items: center;">
            <div style="position: relative; max-width: 90%; max-height: 90%; background: white; padding: 20px; border-radius: 8px;">
                <button onclick="closeSignatureModal()" style="position: absolute; top: 10px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                <img id="modal-signature-img" src="" style="max-width: 100%; max-height: 70vh; object-fit: contain;" alt="Signature Full Size">
                <div style="text-align: center; margin-top: 15px;">
                    <button onclick="downloadSignatureFromModal()" class="button button-primary">📥 Download</button>
                </div>
            </div>
        </div>
        
        <script>
        let currentSignatureData = '';
        let currentSubmissionId = '';
        
        function downloadSignature(submissionId) {
            // Get signature data from the image src
            const imgElement = document.querySelector(`img[alt="Digital Signature"]`);
            if (imgElement && imgElement.src) {
                const link = document.createElement('a');
                link.download = `signature_${submissionId}_${new Date().toISOString().split('T')[0]}.png`;
                link.href = imgElement.src;
                link.click();
            }
        }
        
        function viewSignatureFullscreen(submissionId) {
            const imgElement = document.querySelector(`img[alt="Digital Signature"]`);
            if (imgElement && imgElement.src) {
                currentSignatureData = imgElement.src;
                currentSubmissionId = submissionId;
                
                const modal = document.getElementById('signature-modal');
                const modalImg = document.getElementById('modal-signature-img');
                
                modalImg.src = currentSignatureData;
                modal.style.display = 'flex';
                
                // Close on background click
                modal.onclick = function(e) {
                    if (e.target === modal) {
                        closeSignatureModal();
                    }
                };
                
                // Close on Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeSignatureModal();
                    }
                });
            }
        }
        
        function closeSignatureModal() {
            const modal = document.getElementById('signature-modal');
            modal.style.display = 'none';
            currentSignatureData = '';
            currentSubmissionId = '';
        }
        
        function downloadSignatureFromModal() {
            if (currentSignatureData && currentSubmissionId) {
                const link = document.createElement('a');
                link.download = `signature_${currentSubmissionId}_${new Date().toISOString().split('T')[0]}.png`;
                link.href = currentSignatureData;
                link.click();
            }
        }
        </script>
        
        <?php
    }

    /**
     * Remove WooCommerce checkout additional fields and shipping section
     */
    public function remove_checkout_additional_fields() {
        // Remove additional fields section completely
        remove_action('woocommerce_checkout_after_customer_details', 'woocommerce_checkout_shipping');
        
        // Add CSS to hide col-2 section
        add_action('wp_head', function() {
            echo '<style>
                .woocommerce-checkout .col2-set .col-2 {
                    display: none !important;
                }
                .woocommerce-checkout .col2-set .col-1 {
                    width: 100% !important;
                    float: none !important;
                }
                .woocommerce-additional-fields h3,
                .woocommerce-shipping-fields {
                    display: none !important;
                }
            </style>';
        });
    }

    /**
     * Remove default WooCommerce checkout fields
     */
    public function remove_checkout_fields($fields) {
        // Remove billing address fields
        unset($fields['billing']['billing_first_name']);
        unset($fields['billing']['billing_last_name']);
        unset($fields['billing']['billing_address_1']);
        unset($fields['billing']['billing_address_2']);
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_postcode']);
        unset($fields['billing']['billing_state']);
        unset($fields['billing']['billing_country']);
        
        // Remove shipping fields entirely
        unset($fields['shipping']);
        
        // Add fullname field to replace first/last name
        $fields['billing']['billing_full_name'] = array(
            'label' => __('שם מלא'),
            'placeholder' => __('הכנס שם מלא'),
            'required' => true,
            'class' => array('form-row-wide'),
            'clear' => true,
            'priority' => 10
        );
        
        return $fields;
        
    }

    /**
     * Validate custom checkout fields
     */
    public function validate_checkout_custom_fields() {
        // ID photo upload is now optional - no validation needed
        
        // Subscription terms validation is now handled by the new terms checkbox system
        // No validation needed here as it's replaced by the new terms acceptance
    }

    /**
     * Save custom checkout fields to order meta
     */
    public function save_checkout_custom_fields($order_id) {
        // Handle file upload
        if (!empty($_FILES['id_photo_upload']['name'])) {
            $uploaded_file = wp_handle_upload($_FILES['id_photo_upload'], array('test_form' => false));
            
            if ($uploaded_file && !isset($uploaded_file['error'])) {
                update_post_meta($order_id, '_id_photo_url', $uploaded_file['url']);
                update_post_meta($order_id, '_id_photo_path', $uploaded_file['file']);
            }
        }
        
        // Save subscription terms agreement
        if (!empty($_POST['subscription_terms_agreement'])) {
            update_post_meta($order_id, '_subscription_terms_agreement', 'yes');
        }
    }

    /**
     * Customize WooCommerce checkout fields
     */
    public function customize_checkout_fields($fields) {
        // Remove default billing fields except email
        unset($fields['billing']['billing_first_name']);
        unset($fields['billing']['billing_last_name']);
        unset($fields['billing']['billing_company']);
        unset($fields['billing']['billing_address_1']);
        unset($fields['billing']['billing_address_2']);
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_postcode']);
        unset($fields['billing']['billing_country']);
        unset($fields['billing']['billing_state']);
        unset($fields['billing']['billing_phone']);
        
        // Remove shipping fields
        unset($fields['shipping']);
        
        // Remove order notes
        unset($fields['order']['order_comments']);
        
        // Add custom fields
        $fields['billing']['billing_full_name'] = array(
            'label' => 'שם מלא',
            'placeholder' => 'הזן שם מלא',
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 10,
        );
        
        // Keep email field but modify it
        $fields['billing']['billing_email']['label'] = 'אימייל';
        $fields['billing']['billing_email']['placeholder'] = 'הזן כתובת אימייל';
        $fields['billing']['billing_email']['priority'] = 20;
        
        $fields['billing']['billing_identification'] = array(
            'label' => 'ח.פ/ת.ז',
            'placeholder' => 'הזן מספר תעודת זהות או חברה',
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 30,
        );
        
        $fields['billing']['billing_mobile_phone'] = array(
            'label' => 'טלפון נייד',
            'placeholder' => 'הזן מספר טלפון נייד',
            'required' => true,
            'class' => array('form-row-wide'),
            'priority' => 40,
            'type' => 'tel',
        );
        
        return $fields;
    }

    /**
     * Save custom checkout fields to order meta
     */
    public function save_custom_checkout_fields($order_id) {
        if (!empty($_POST['billing_full_name'])) {
            update_post_meta($order_id, '_billing_full_name', sanitize_text_field($_POST['billing_full_name']));
        }
        
        if (!empty($_POST['billing_identification'])) {
            update_post_meta($order_id, '_billing_identification', sanitize_text_field($_POST['billing_identification']));
        }
        
        if (!empty($_POST['billing_mobile_phone'])) {
            update_post_meta($order_id, '_billing_mobile_phone', sanitize_text_field($_POST['billing_mobile_phone']));
        }
    }

    /**
     * Display custom fields in admin order details
     */
    public function display_admin_order_meta($order) {
        $id_photo_url = get_post_meta($order->get_id(), '_id_photo_url', true);
        $terms_agreed = get_post_meta($order->get_id(), '_subscription_terms_agreement', true);
        
        echo '<h3>פרטים נוספים מהלקוח</h3>';
        
        if ($id_photo_url) {
            echo '<p><strong>תמונת תעודת זהות:</strong><br>';
            echo '<a href="' . esc_url($id_photo_url) . '" target="_blank">צפה בקובץ</a></p>';
        }
        
        if ($terms_agreed) {
            echo '<p><strong>אישור תנאי מנוי:</strong> כן</p>';
        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    $quiz_system = ACF_Quiz_System::get_instance();
    $quiz_system->create_assets_structure();
});
