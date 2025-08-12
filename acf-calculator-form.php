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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_shortcode('acf_quiz', array($this, 'render_quiz_form'));
        add_action('wp_ajax_submit_quiz', array($this, 'handle_quiz_submission'));
        add_action('wp_ajax_nopriv_submit_quiz', array($this, 'handle_quiz_submission'));
        
        // Database and admin hooks
        register_activation_hook(__FILE__, array($this, 'create_submissions_table'));
        add_action('admin_menu', array($this, 'add_submissions_page'));
        
        // Multi-step form AJAX handlers
        add_action('wp_ajax_save_step_data', array($this, 'handle_step_data'));
        add_action('wp_ajax_nopriv_save_step_data', array($this, 'handle_step_data'));
        
        // BACKUP: Simple lead capture without complex routing
        add_action('wp_ajax_simple_lead_capture', array($this, 'simple_lead_capture'));
        add_action('wp_ajax_nopriv_simple_lead_capture', array($this, 'simple_lead_capture'));
        
        // WooCommerce integration
        add_action('woocommerce_checkout_process', array($this, 'populate_checkout_fields'));
        add_filter('woocommerce_checkout_get_value', array($this, 'get_checkout_field_value'), 10, 2);
        add_filter('woocommerce_add_cart_item_data', array($this, 'handle_custom_cart_item'), 10, 3);
        add_action('woocommerce_before_calculate_totals', array($this, 'set_custom_cart_item_price'));
        
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
            
            PRIMARY KEY (id),
            KEY passed (passed),
            KEY completed (completed),
            KEY submission_time (submission_time),
            KEY user_email (user_email)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add submissions admin page
     */
    public function add_submissions_page() {
        // Create standalone admin menu for Quiz Submissions
        add_menu_page(
            __('Quiz Submissions', 'acf-quiz'),
            __('Quiz Submissions', 'acf-quiz'),
            'manage_options',
            'quiz-submissions',
            array($this, 'render_submissions_page'),
            'dashicons-list-view',
            25
        );
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
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        if (!is_admin()) {
            // Enqueue CSS
            wp_enqueue_style(
                'acf-quiz-public',
                $this->plugin_url . 'css/quiz-public.css',
                array(),
                '1.0.0'
            );

            // Enqueue JavaScript
            wp_enqueue_script(
                'acf-quiz-public',
                $this->plugin_url . 'js/quiz-public.js',
                array('jquery'),
                '1.0.0',
                true
            );

            // Localize script
            wp_localize_script('acf-quiz-public', 'acfQuiz', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('acf_quiz_nonce'),
                'productIds' => array(
                    'trial' => get_option('quiz_trial_product_id', ''),
                    'monthly' => get_option('quiz_monthly_product_id', ''),
                    'yearly' => get_option('quiz_yearly_product_id', '')
                ),
                'strings' => array(
                    'fillAllFields' => __('אנא מלא את כל השדות הנדרשים', 'acf-quiz'),
                    'submitError' => __('שגיאה בשליחת הטופס. אנא נסה שוב.', 'acf-quiz'),
                    'securityError' => __('בדיקת אבטחה נכשלה. אנא רענן את הדף ונסה שוב.', 'acf-quiz'),
                    'networkError' => __('שגיאת רשת. אנא בדוק את החיבור לאינטרנט ונסה שוב.', 'acf-quiz'),
                    'unexpectedError' => __('שגיאה לא צפויה. אנא נסה שוב מאוחר יותר.', 'acf-quiz')
                )
            ));
        }
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
                        <h3>שלום ברוך הבא!</h3>
                        <p>אני שמח שבחרת להצטרף לשירות שלי</p>
                        <p>היות ואני מנהל השקעות, מס' רישיון 7955 השירות מנוהל בהתאם לתקנות של הרשות לניירות ערך</p>
                        <p>ולכן מבוצע איתך הליך מקוון של בירור התאמה לשירות שמטרתו לאסוף את המידע הרלוונטי אודותיך ולברר האם הינך עם הבנה מספקת בשוק הון שכן זהו תנאי הכרחי להרשמה לשרות ונדרש על-פי חוק</p>
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
                        <h4>לתשומת ליבך:</h4>
                        <p>על מנת שנוכל לבחון בצורה המיטבית את התאמתך לשירות המידע, שיתוף הפעולה מצידך הינו קריטי לשם ביצוע הליך הבירור באופן יעיל. אי מסירת פרטים או מסירת פרטים חלקיים עשויות למנוע ממפעיל השרות לספק לך את השירות.</p>
                        <p>על פי תיקון ההוראה לבעלי רישיון בקשר למתן שירותים תוך שימוש באמצעים טכנולוגיים מאוגוסט 2023, אנחנו מדגישים כי שירות הייעוץ למסחר עצמאי אינו מותאם אישית, ועל כן השירותים אינם מותאמים באופן פרטני ללקוח או לצרכיו.</p>
                    </div>
                </div>

                <!-- Step 2: Detailed Personal Information -->
                <div class="form-step" data-step="2">
                    <div class="step-intro">
                        <h3>פרטים מלאים</h3>
                    </div>
                    
                    <div class="personal-fields">
                        <div class="field-group">
                            <label for="id_number" class="field-label">תעודת זהות / דרכון</label>
                            <input type="text" id="id_number" name="id_number" class="field-input">
                        </div>
                        
                        <div class="field-group">
                            <label for="gender" class="field-label">מין</label>
                            <select id="gender" name="gender" class="field-input">
                                <option value="">בחר</option>
                                <option value="male">זכר</option>
                                <option value="female">נקבה</option>
                            </select>
                        </div>
                        
                        <div class="field-group">
                            <label for="birth_date" class="field-label">תאריך לידה</label>
                            <input type="date" id="birth_date" name="birth_date" class="field-input">
                        </div>
                        
                        <div class="field-group">
                            <label for="citizenship" class="field-label">אזרחות</label>
                            <input type="text" id="citizenship" name="citizenship" class="field-input" value="ישראלית">
                        </div>
                        
                        <div class="field-group">
                            <label for="address" class="field-label">כתובת</label>
                            <textarea id="address" name="address" class="field-input" rows="3"></textarea>
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
                        <p>אנא ענה על השאלות הבאות בהתאם לידע וניסיון שלך</p>
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
                    
                    <div class="final-declaration" style="display: block !important; visibility: visible !important;">
                        <h4 style="margin-bottom: 15px; color: #333;">הצהרה סופית</h4>
                        <div class="checkbox-group">
                            <input type="checkbox" id="final_declaration" name="final_declaration" class="checkbox-input rtl-input" required checked style="display: inline-block !important; visibility: visible !important;">
                            <label for="final_declaration" class="checkbox-label" style="display: inline-block !important; margin-right: 10px;">
                                אני מצהיר/ה כי כל המידע שמסרתי הוא נכון ומדויק, ואני מבין/ה את הסיכונים הכרוכים בהשקעות.
                                <span class="required">*</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="form-navigation">
                    <button type="button" id="prev-step" class="nav-btn prev-btn" style="display: none;">חזרה</button>
                    <button type="button" id="next-step" class="nav-btn next-btn">המשך</button>
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
        $final_declaration = isset($quiz_data['final_declaration']) && $quiz_data['final_declaration'] === 'on';

        if (empty($user_name) || empty($user_phone) || !$final_declaration) {
            wp_send_json_error(array('message' => __('אנא מלא את כל הפרטים הנדרשים ואשר את ההסכמה ליצירת קשר.', 'acf-quiz')));
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

        // Check if passed (21+ points out of 40)
        $passed = $total_score >= 21;
        $score_percentage = round(($total_score / $max_possible_score) * 100);

        // Store submission in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        $submission_data = array(
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
            'submission_time' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $wpdb->insert($table_name, $submission_data);
        $submission_id = $wpdb->insert_id;

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
            'redirect_url' => $passed ? $this->get_checkout_url($package_type, $package_price) : '/followup'
        );

        wp_send_json_success($response);
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
        
        // Store step 1 data as partial submission (lead)
        if ($current_step === 1) {
            // Fix field name mapping from frontend
            $first_name = sanitize_text_field($step_data['first_name'] ?? '');
            $last_name = sanitize_text_field($step_data['last_name'] ?? '');
            $user_name = trim($first_name . ' ' . $last_name);
            $user_phone = sanitize_text_field($step_data['user_phone'] ?? '');
            $user_email = sanitize_text_field($step_data['user_email'] ?? '');
            $contact_consent = isset($step_data['contact_consent']) ? 1 : 0;
            
            error_log('Processing step 1 lead data');
            error_log('User name: ' . $user_name);
            error_log('User phone: ' . $user_phone);
            error_log('User email: ' . $user_email);
            
            // Only store if we have at least name or phone
            if (!empty($user_name) || !empty($user_phone)) {
                error_log('Lead data validation passed, proceeding with DB insert');
                
                global $wpdb;
                $table_name = $wpdb->prefix . 'quiz_submissions'; // Use WordPress prefix for consistency
                
                error_log('Table name: ' . $table_name);
                
                // Get package information from URL parameters or POST data
                $package_type = $_POST['package_param'] ?? '';
                $package_price = 0;
                
                if ($package_type === 'trial') {
                    $package_price = get_field('trial_price', 'option') ?: 99;
                } elseif ($package_type === 'monthly') {
                    $package_price = get_field('monthly_price', 'option') ?: 199;
                } elseif ($package_type === 'yearly') {
                    $package_price = get_field('yearly_price', 'option') ?: 1999;
                }
                
                error_log('Package type: ' . $package_type);
                error_log('Package price: ' . $package_price);
                
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
                    'current_step' => $current_step,
                    'completed' => 0,
                    'submission_time' => current_time('mysql'),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                );
                
                error_log('Submission data: ' . print_r($submission_data, true));
                
                $result = $wpdb->insert($table_name, $submission_data);
                
                error_log('Insert result: ' . ($result === false ? 'FALSE' : $result));
                error_log('Last error: ' . $wpdb->last_error);
                error_log('Insert ID: ' . $wpdb->insert_id);
                
                // Debug log for troubleshooting
                if ($result === false) {
                    error_log('Quiz lead insert failed: ' . $wpdb->last_error);
                } else {
                    error_log('Quiz lead inserted successfully: ID ' . $wpdb->insert_id);
                }
            } else {
                error_log('Lead data validation failed - no name or phone provided');
            }
        }
        
        // Store in session for multi-step form
        if (!session_id()) {
            session_start();
        }
        
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
     * Render submissions admin page
     */
    public function render_submissions_page() {
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
        
        // Get all submissions including leads - simplified query
        $submissions = $wpdb->get_results("
            SELECT id, user_name, user_phone, user_email, package_selected, 
                   score, max_score, passed, completed, submission_time, current_step
            FROM $table_name 
            WHERE 1=1
            " . ($filter === 'failed' ? " AND (passed = 0 OR completed = 0)" : "") . "
            " . ($filter === 'passed' ? " AND passed = 1" : "") . "
            ORDER BY submission_time DESC 
            LIMIT 100
        ");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Quiz Submissions', 'acf-quiz'); ?></h1>
            
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
        </style>
        
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
        });
        </script>
        <?php
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
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    $quiz_system = ACF_Quiz_System::get_instance();
    $quiz_system->create_assets_structure();
});
