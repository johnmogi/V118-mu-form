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

        $this->init_hooks();
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
     * Create submissions table
     */
    public function create_submissions_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_name varchar(255) NOT NULL,
            user_phone varchar(50) NOT NULL,
            user_email varchar(255) DEFAULT '',
            package_selected varchar(100) DEFAULT '',
            score int(11) NOT NULL,
            max_score int(11) NOT NULL,
            passed tinyint(1) NOT NULL,
            answers longtext NOT NULL,
            submission_time datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            PRIMARY KEY (id),
            KEY passed (passed),
            KEY submission_time (submission_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Add submissions admin page
     */
    public function add_submissions_page() {
        add_submenu_page(
            'quiz-settings',
            __('Quiz Submissions', 'acf-quiz'),
            __('Submissions', 'acf-quiz'),
            'manage_options',
            'quiz-submissions',
            array($this, 'render_submissions_page')
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
                'strings' => array(
                    'pleaseAnswerAll' => __('Please answer all questions before submitting.', 'acf-quiz'),
                    'submitting' => __('Submitting...', 'acf-quiz'),
                    'submit' => __('Submit Quiz', 'acf-quiz'),
                    'error' => __('An error occurred. Please try again.', 'acf-quiz'),
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
     * Render quiz form shortcode
     */
    public function render_quiz_form($atts = array()) {
        // Parse shortcode attributes
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
        $quiz_title = get_field('quiz_title', 'option') ?: __('שאלון התאמה - חלק א׳', 'acf-quiz');
        $quiz_instructions = get_field('quiz_instructions', 'option');
        $questions = get_field('quiz_questions', 'option');
        
        // Set passing score to 21 (minimum passing score out of 40)
        $passing_score = 21;
        
        if (empty($questions) || count($questions) !== 10) {
            return '<div class="quiz-error"><p>' . __('השאלון לא מוגדר כראוי. אנא פנה למנהל האתר.', 'acf-quiz') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="acf-quiz-container" data-passing-score="21" data-max-score="40" dir="rtl">
            <div class="quiz-header">
                <h2 class="quiz-title"><?php echo esc_html($quiz_title); ?></h2>
                <?php if ($quiz_instructions) : ?>
                    <div class="quiz-instructions">
                        <p><?php echo esc_html($quiz_instructions); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <form id="acf-quiz-form" class="quiz-form" dir="rtl">
                <?php wp_nonce_field('acf_quiz_nonce', 'quiz_nonce'); ?>
                
                <!-- Hidden package information -->
                <input type="hidden" name="package_selected" value="<?php echo esc_attr($package); ?>">
                <input type="hidden" name="package_price" value="<?php echo esc_attr($price); ?>">
                <input type="hidden" name="package_source" value="<?php echo esc_attr($source); ?>">
                
                <!-- Personal Details Section -->
                <div class="personal-details-section">
                    <h3 class="section-title">פרטים אישיים</h3>
                    <div class="personal-fields">
                        <div class="field-group">
                            <label for="user_name" class="field-label">שם מלא <span class="required">*</span></label>
                            <input type="text" id="user_name" name="user_name" class="field-input" required>
                        </div>
                        
                        <div class="field-group">
                            <label for="user_phone" class="field-label">טלפון <span class="required">*</span></label>
                            <input type="tel" id="user_phone" name="user_phone" class="field-input" required>
                        </div>
                        
                        <div class="field-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="contact_consent" name="contact_consent" required>
                                <span class="checkmark"></span>
                                אני מסכים/ה שמנהל האתר יוכל ליצור איתי קשר לצורך מתן שירות
                                <span class="required">*</span>
                            </label>
                        </div>
                    </div>
                    <hr class="section-divider">
                </div>
                
                <?php foreach ($questions as $q_index => $question) : ?>
                    <div class="question-block" data-question="<?php echo $q_index; ?>">
                        <div class="question-header">
                            <span class="question-number"><?php echo ($q_index + 1); ?>.</span>
                            <h3 class="question-text"><?php echo esc_html($question['question_text']); ?></h3>
                        </div>
                        
                        <div class="answers-container">
                            <?php if (!empty($question['answers'])) : ?>
                                <?php foreach ($question['answers'] as $a_index => $answer) : ?>
                                    <div class="answer-option">
                                        <input type="radio" 
                                               name="question_<?php echo $q_index; ?>" 
                                               id="q<?php echo $q_index; ?>_a<?php echo $a_index; ?>"
                                               value="<?php echo $answer['points']; ?>"
                                               class="answer-input"
                                               required>
                                        <label for="q<?php echo $q_index; ?>_a<?php echo $a_index; ?>" class="answer-label">
                                            <span class="answer-marker"></span>
                                            <span class="answer-text"><?php echo esc_html($answer['answer_text']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($question['explanation'])) : ?>
                            <div class="question-explanation" style="display: none;">
                                <p><strong><?php _e('Explanation:', 'acf-quiz'); ?></strong> <?php echo esc_html($question['explanation']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="quiz-actions">
                    <button type="submit" class="quiz-submit-btn"><?php _e('שלח שאלון', 'acf-quiz'); ?></button>
                </div>
            </form>
            
            <div id="quiz-results" class="quiz-results" style="display: none;" dir="rtl">
                <div class="results-header">
                    <h3><?php _e('תוצאות השאלון', 'acf-quiz'); ?></h3>
                </div>
                <div class="results-content">
                    <div class="score-display">
                        <span class="score-label"><?php _e('הציון שלך:', 'acf-quiz'); ?></span>
                        <span class="score-value"><span id="quiz-score">0</span>/40</span>
                        <span class="score-message" id="score-message"></span>
                    </div>
                    <div class="result-message">
                        <p id="result-message"></p>
                    </div>
                    <div class="quiz-actions">
                        <button type="button" class="quiz-contact-btn"><?php _e('נציג יצור איתך קשר בקרוב', 'acf-quiz'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * Handle quiz submission via AJAX
     */
    public function handle_quiz_submission() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['quiz_nonce'], 'acf_quiz_nonce')) {
            wp_send_json_error(array('message' => __('בדיקת אבטחה נכשלה.', 'acf-quiz')));
        }

        // Validate personal details
        $user_name = sanitize_text_field($_POST['user_name'] ?? '');
        $user_phone = sanitize_text_field($_POST['user_phone'] ?? '');
        $contact_consent = isset($_POST['contact_consent']) && $_POST['contact_consent'] === 'on';

        if (empty($user_name) || empty($user_phone) || !$contact_consent) {
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
            if (!isset($_POST[$answer_key])) {
                $all_answered = false;
                continue;
            }

            $points_earned = intval($_POST[$answer_key]);
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

        // Get package information
        $package_selected = sanitize_text_field($_POST['package_selected'] ?? '');
        $package_price = sanitize_text_field($_POST['package_price'] ?? '');
        $package_source = sanitize_text_field($_POST['package_source'] ?? '');

        // Store submission in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        $submission_data = array(
            'user_name' => $user_name,
            'user_phone' => $user_phone,
            'user_email' => '', // Can be added later if needed
            'package_selected' => $package_selected,
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

        // Store user details for later use (can be used for WooCommerce integration)
        $user_details = array(
            'name' => $user_name,
            'phone' => $user_phone,
            'consent' => $contact_consent,
            'submission_time' => current_time('mysql'),
            'submission_id' => $submission_id
        );

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
            )
        );

        wp_send_json_success($response);
    }

    /**
     * Render submissions admin page
     */
    public function render_submissions_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'quiz_submissions';
        
        // Handle filtering
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $where_clause = '';
        
        if ($filter === 'failed') {
            $where_clause = 'WHERE passed = 0';
        } elseif ($filter === 'passed') {
            $where_clause = 'WHERE passed = 1';
        }
        
        // Get submissions
        $submissions = $wpdb->get_results("
            SELECT * FROM $table_name 
            $where_clause 
            ORDER BY submission_time DESC 
            LIMIT 100
        ");
        
        $total_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $failed_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE passed = 0");
        $passed_submissions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE passed = 1");
        
        ?>
        <div class="wrap">
            <h1><?php _e('Quiz Submissions', 'acf-quiz'); ?></h1>
            
            <div class="quiz-stats">
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3><?php echo $total_submissions; ?></h3>
                        <p><?php _e('Total Submissions', 'acf-quiz'); ?></p>
                    </div>
                    <div class="stat-box failed">
                        <h3><?php echo $failed_submissions; ?></h3>
                        <p><?php _e('Failed Attempts', 'acf-quiz'); ?></p>
                    </div>
                    <div class="stat-box passed">
                        <h3><?php echo $passed_submissions; ?></h3>
                        <p><?php _e('Passed', 'acf-quiz'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="tablenav top">
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
                        <th><?php _e('Date', 'acf-quiz'); ?></th>
                        <th><?php _e('Name', 'acf-quiz'); ?></th>
                        <th><?php _e('Phone', 'acf-quiz'); ?></th>
                        <th><?php _e('Package', 'acf-quiz'); ?></th>
                        <th><?php _e('Score', 'acf-quiz'); ?></th>
                        <th><?php _e('Status', 'acf-quiz'); ?></th>
                        <th><?php _e('Actions', 'acf-quiz'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)) : ?>
                        <tr>
                            <td colspan="7"><?php _e('No submissions found.', 'acf-quiz'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($submissions as $submission) : ?>
                            <tr class="<?php echo $submission->passed ? 'passed' : 'failed'; ?>">
                                <td><?php echo date('Y-m-d H:i', strtotime($submission->submission_time)); ?></td>
                                <td><strong><?php echo esc_html($submission->user_name); ?></strong></td>
                                <td><a href="tel:<?php echo esc_attr($submission->user_phone); ?>"><?php echo esc_html($submission->user_phone); ?></a></td>
                                <td><?php echo esc_html($submission->package_selected ?: __('Not specified', 'acf-quiz')); ?></td>
                                <td>
                                    <span class="score-display">
                                        <?php echo $submission->score; ?>/<?php echo $submission->max_score; ?>
                                        (<?php echo round(($submission->score / $submission->max_score) * 100); ?>%)
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $submission->passed ? 'passed' : 'failed'; ?>">
                                        <?php echo $submission->passed ? __('Passed', 'acf-quiz') : __('Failed', 'acf-quiz'); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button view-details" data-id="<?php echo $submission->id; ?>">
                                        <?php _e('View Details', 'acf-quiz'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
        <?php
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
