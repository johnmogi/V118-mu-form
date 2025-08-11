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
        add_shortcode('quiz_form', array($this, 'render_quiz_form'));
        
        // AJAX handlers
        add_action('wp_ajax_submit_quiz', array($this, 'handle_quiz_submission'));
        add_action('wp_ajax_nopriv_submit_quiz', array($this, 'handle_quiz_submission'));
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
        if (function_exists('acf_add_local_field_group')) {
            acf_add_local_field_group(array(
                'key' => 'group_quiz_settings',
                'title' => __('Quiz Configuration', 'acf-quiz'),
                'fields' => array(
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
                    // Passing Percentage
                    array(
                        'key' => 'field_passing_percentage',
                        'label' => __('Passing Percentage', 'acf-quiz'),
                        'name' => 'passing_percentage',
                        'type' => 'number',
                        'required' => 1,
                        'default_value' => 50,
                        'min' => 1,
                        'max' => 100,
                        'step' => 1,
                        'append' => '%',
                        'wrapper' => array(
                            'width' => '50',
                        ),
                    ),
                    // Quiz Instructions
                    array(
                        'key' => 'field_quiz_instructions',
                        'label' => __('Quiz Instructions', 'acf-quiz'),
                        'name' => 'quiz_instructions',
                        'type' => 'textarea',
                        'default_value' => __('אנא ענה על כל השאלות. עליך לענות נכון על לפחות 50% מהשאלות כדי לעבור את המבחן.', 'acf-quiz'),
                        'rows' => 3,
                    ),
                    // Questions Repeater
                    array(
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
                            // Question 1
                            array(
                                'key' => 'field_question_1',
                                'label' => __('שאלה 1', 'acf-quiz'),
                                'name' => 'question_1',
                                'type' => 'group',
                                'layout' => 'block',
                                'sub_fields' => array(
                                    array(
                                        'key' => 'field_question_1_text',
                                        'label' => __('שאלה', 'acf-quiz'),
                                        'name' => 'question_text',
                                        'type' => 'text',
                                        'required' => 1,
                                        'default_value' => 'מהי רמת הניסיון שלך בשוק ההון?',
                                        'wrapper' => array('width' => '100%'),
                                    ),
                                    array(
                                        'key' => 'field_question_1_answers',
                                        'label' => __('תשובות', 'acf-quiz'),
                                        'name' => 'answers',
                                        'type' => 'repeater',
                                        'layout' => 'table',
                                        'button_label' => __('הוסף תשובה', 'acf-quiz'),
                                        'sub_fields' => array(
                                            array(
                                                'key' => 'field_question_1_answer_text',
                                                'label' => __('טקסט תשובה', 'acf-quiz'),
                                                'name' => 'answer_text',
                                                'type' => 'text',
                                                'required' => 1,
                                                'wrapper' => array('width' => '80%'),
                                            ),
                                            array(
                                                'key' => 'field_question_1_is_correct',
                                                'label' => __('תשובה נכונה?', 'acf-quiz'),
                                                'name' => 'is_correct',
                                                'type' => 'true_false',
                                                'ui' => 1,
                                                'default_value' => 0,
                                                'wrapper' => array('width' => '20%'),
                                            ),
                                        ),
                                        'default_value' => array(
                                            array('answer_text' => 'אין לי ניסיון כלל', 'is_correct' => 0),
                                            array('answer_text' => 'השקעות בסיסיות (כגון קניית קרנות נאמנות דרך הבנק)', 'is_correct' => 0),
                                            array('answer_text' => 'מבצע פעולות עצמאיות בתדירות בינונית', 'is_correct' => 0),
                                            array('answer_text' => 'עוקב ומבצע פעולות שוטפות באופן עצמאי', 'is_correct' => 1),
                                        ),
                                    ),
                                ),
                            ),
                            // Question 2
                            array(
                                'key' => 'field_question_2',
                                'label' => __('שאלה 2', 'acf-quiz'),
                                'name' => 'question_2',
                                'type' => 'group',
                                'layout' => 'block',
                                'sub_fields' => array(
                                    array(
                                        'key' => 'field_question_2_text',
                                        'label' => __('שאלה', 'acf-quiz'),
                                        'name' => 'question_text',
                                        'type' => 'text',
                                        'required' => 1,
                                        'default_value' => 'האם אתה עוקב אחרי השוק ולומד אותו?',
                                        'wrapper' => array('width' => '100%'),
                                    ),
                                    array(
                                        'key' => 'field_question_2_answers',
                                        'label' => __('תשובות', 'acf-quiz'),
                                        'name' => 'answers',
                                        'type' => 'repeater',
                                        'layout' => 'table',
                                        'button_label' => __('הוסף תשובה', 'acf-quiz'),
                                        'sub_fields' => array(
                                            array(
                                                'key' => 'field_question_2_answer_text',
                                                'label' => __('טקסט תשובה', 'acf-quiz'),
                                                'name' => 'answer_text',
                                                'type' => 'text',
                                                'required' => 1,
                                                'wrapper' => array('width' => '80%'),
                                            ),
                                            array(
                                                'key' => 'field_question_2_is_correct',
                                                'label' => __('תשובה נכונה?', 'acf-quiz'),
                                                'name' => 'is_correct',
                                                'type' => 'true_false',
                                                'ui' => 1,
                                                'default_value' => 0,
                                                'wrapper' => array('width' => '20%'),
                                            ),
                                        ),
                                        'default_value' => array(
                                            array('answer_text' => 'לא עוקב כלל ולא למדתי', 'is_correct' => 0),
                                            array('answer_text' => 'עוקב לעיתים / קראתי פה ושם', 'is_correct' => 0),
                                            array('answer_text' => 'קורא עיתונות כלכלית / למדתי קורס בסיסי', 'is_correct' => 0),
                                            array('answer_text' => 'עוקב שוטף ולמדתי באופן מסודר (קורסים/לימוד עצמאי קבוע)', 'is_correct' => 1),
                                        ),
                                    ),
                                ),
                            ),
                            // Additional questions will be added in the same pattern
                        ),
                        'default_value' => array(
                            // Question 1
                            array(
                                'question_text' => 'מהי רמת הניסיון שלך בשוק ההון?',
                                'answers' => array(
                                    array('answer_text' => 'אין לי ניסיון כלל', 'is_correct' => 0),
                                    array('answer_text' => 'השקעות בסיסיות (כגון קניית קרנות נאמנות דרך הבנק)', 'is_correct' => 0),
                                    array('answer_text' => 'מבצע פעולות עצמאיות בתדירות בינונית', 'is_correct' => 0),
                                    array('answer_text' => 'עוקב ומבצע פעולות שוטפות באופן עצמאי', 'is_correct' => 1),
                                )
                            ),
                            // Question 2
                            array(
                                'question_text' => 'האם אתה עוקב אחרי השוק ולומד אותו?',
                                'answers' => array(
                                    array('answer_text' => 'לא עוקב כלל ולא למדתי', 'is_correct' => 0),
                                    array('answer_text' => 'עוקב לעיתים / קראתי פה ושם', 'is_correct' => 0),
                                    array('answer_text' => 'קורא עיתונות כלכלית / למדתי קורס בסיסי', 'is_correct' => 0),
                                    array('answer_text' => 'עוקב שוטף ולמדתי באופן מסודר (קורסים/לימוד עצמאי קבוע)', 'is_correct' => 1),
                                )
                            ),
                            // Question 3
                            array(
                                'question_text' => 'מהי תדירות המעקב שלך אחר תיק ההשקעות או הפעילות שלך?',
                                'answers' => array(
                                    array('answer_text' => 'לעיתים רחוקות', 'is_correct' => 0),
                                    array('answer_text' => 'פעם בחודש', 'is_correct' => 0),
                                    array('answer_text' => 'כל כמה ימים', 'is_correct' => 0),
                                    array('answer_text' => 'יומי', 'is_correct' => 1),
                                )
                            ),
                            // Question 4
                            array(
                                'question_text' => 'כיצד היית מגדיר את הבנתך בנוגע לסיכון בשוק ההון?',
                                'answers' => array(
                                    array('answer_text' => 'לא מבין כלל', 'is_correct' => 0),
                                    array('answer_text' => 'מבין בצורה בסיסית את עקרונות הסיכון', 'is_correct' => 0),
                                    array('answer_text' => 'מבין טוב את הקשר בין סיכון לרווח', 'is_correct' => 0),
                                    array('answer_text' => 'מבין היטב ויודע להשתמש גם בכלים לניהול סיכונים כמו סטופ לוס', 'is_correct' => 1),
                                )
                            ),
                            // Question 5
                            array(
                                'question_text' => 'מהי הגישה שלך כלפי סיכון בהשקעות?',
                                'answers' => array(
                                    array('answer_text' => 'שמרן מאוד - מעדיף יציבות', 'is_correct' => 0),
                                    array('answer_text' => 'שמרן יחסית - מוכן לסיכון מינימלי', 'is_correct' => 0),
                                    array('answer_text' => 'מאוזן - פתוח לסיכון מסוים תמורת תשואה', 'is_correct' => 0),
                                    array('answer_text' => 'אגרסיבי - מחפש הזדמנויות רווח גם במחיר תנודתיות גבוהה', 'is_correct' => 1),
                                )
                            ),
                            // Question 6
                            array(
                                'question_text' => 'מהו טווח הזמן שאתה מעדיף להשקעה?',
                                'answers' => array(
                                    array('answer_text' => 'יומי', 'is_correct' => 0),
                                    array('answer_text' => 'שבועי', 'is_correct' => 0),
                                    array('answer_text' => 'בין שבוע לחודש', 'is_correct' => 0),
                                    array('answer_text' => 'לכמה חודשים ומעלה', 'is_correct' => 1),
                                )
                            ),
                            // Question 7
                            array(
                                'question_text' => 'איזו נזילות נדרשת לך לכספי ההשקעה?',
                                'answers' => array(
                                    array('answer_text' => 'מיידית - צריך גישה מיידית', 'is_correct' => 0),
                                    array('answer_text' => 'שבועית', 'is_correct' => 0),
                                    array('answer_text' => 'חודשית', 'is_correct' => 0),
                                    array('answer_text' => 'שנתית או יותר', 'is_correct' => 1),
                                )
                            ),
                            // Question 8
                            array(
                                'question_text' => 'מהם הנכסים הפיננסיים שברשותך כיום (כולל פנסיה, קופות, תיק השקעות)?',
                                'answers' => array(
                                    array('answer_text' => 'אין לי בכלל', 'is_correct' => 0),
                                    array('answer_text' => 'רק קרן פנסיה / קופת גמל / קרן השתלמות', 'is_correct' => 0),
                                    array('answer_text' => 'יש גם ניירות ערך ישירים כמו מניות', 'is_correct' => 0),
                                    array('answer_text' => 'יש לי תיק מגוון עם השקעות שוטפות', 'is_correct' => 1),
                                )
                            ),
                            // Question 9
                            array(
                                'question_text' => 'מהי רמת החשיפה שלך לנכסים מסוכנים כיום (למשל מניות)?',
                                'answers' => array(
                                    array('answer_text' => 'פחות מ-10%', 'is_correct' => 0),
                                    array('answer_text' => '10%-30%', 'is_correct' => 0),
                                    array('answer_text' => '30%-50%', 'is_correct' => 0),
                                    array('answer_text' => 'מעל 50%', 'is_correct' => 1),
                                )
                            ),
                            // Question 10
                            array(
                                'question_text' => 'באיזו פלטפורמה אתה פועל או סוחר כיום?',
                                'answers' => array(
                                    array('answer_text' => 'אינני פועל כלל', 'is_correct' => 0),
                                    array('answer_text' => 'דרך הבנק בלבד', 'is_correct' => 0),
                                    array('answer_text' => 'דרך בתי השקעות', 'is_correct' => 0),
                                    array('answer_text' => 'גם וגם / מערכות מתקדמות עצמאיות', 'is_correct' => 1),
                                )
                            ),
                        ),
                    ),
                    // Success Message
                    array(
                        'key' => 'field_success_message',
                        'label' => __('Success Message', 'acf-quiz'),
                        'name' => 'success_message',
                        'type' => 'textarea',
                        'default_value' => __('Congratulations! You passed the quiz.', 'acf-quiz'),
                        'rows' => 2,
                        'wrapper' => array(
                            'width' => '50',
                        ),
                    ),
                    // Failure Message
                    array(
                        'key' => 'field_failure_message',
                        'label' => __('Failure Message', 'acf-quiz'),
                        'name' => 'failure_message',
                        'type' => 'textarea',
                        'default_value' => __('Sorry, you did not pass. Please study and try again.', 'acf-quiz'),
                        'rows' => 2,
                        'wrapper' => array(
                            'width' => '50',
                        ),
                    ),
                ),
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
                'active' => true,
            ));
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
        // Get quiz data
        $quiz_title = get_field('quiz_title', 'option') ?: __('Knowledge Quiz', 'acf-quiz');
        $quiz_instructions = get_field('quiz_instructions', 'option');
        $questions = get_field('quiz_questions', 'option');
        $passing_percentage = get_field('passing_percentage', 'option') ?: 50;
        
        if (empty($questions) || count($questions) !== 10) {
            return '<div class="quiz-error"><p>' . __('Quiz is not properly configured. Please contact the administrator.', 'acf-quiz') . '</p></div>';
        }
        
        ob_start();
        ?>
        <div class="acf-quiz-container" data-passing-score="<?php echo esc_attr($passing_percentage); ?>">
            <div class="quiz-header">
                <h2 class="quiz-title"><?php echo esc_html($quiz_title); ?></h2>
                <?php if ($quiz_instructions) : ?>
                    <div class="quiz-instructions">
                        <p><?php echo esc_html($quiz_instructions); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <form id="acf-quiz-form" class="quiz-form">
                <?php wp_nonce_field('acf_quiz_nonce', 'quiz_nonce'); ?>
                
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
                                               value="<?php echo $a_index; ?>"
                                               data-correct="<?php echo $answer['is_correct'] ? '1' : '0'; ?>"
                                               class="answer-input">
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
                    <button type="submit" class="quiz-submit-btn"><?php _e('Submit Quiz', 'acf-quiz'); ?></button>
                </div>
            </form>
            
            <div id="quiz-results" class="quiz-results" style="display: none;">
                <div class="results-header">
                    <h3><?php _e('Quiz Results', 'acf-quiz'); ?></h3>
                </div>
                <div class="results-content">
                    <div class="score-display">
                        <span class="score-label"><?php _e('Your Score:', 'acf-quiz'); ?></span>
                        <span class="score-value"><span id="quiz-score">0</span>%</span>
                        <span class="score-fraction">(<span id="correct-answers">0</span>/<?php echo count($questions); ?>)</span>
                    </div>
                    <div class="result-message">
                        <p id="result-message"></p>
                    </div>
                    <div class="quiz-actions">
                        <button type="button" class="quiz-retry-btn" onclick="location.reload();"><?php _e('Try Again', 'acf-quiz'); ?></button>
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
            wp_send_json_error(array('message' => __('Security check failed.', 'acf-quiz')));
        }
        
        // Get quiz configuration
        $questions = get_field('quiz_questions', 'option');
        $passing_percentage = get_field('passing_percentage', 'option') ?: 50;
        $success_message = get_field('success_message', 'option') ?: __('Congratulations! You passed the quiz.', 'acf-quiz');
        $failure_message = get_field('failure_message', 'option') ?: __('Sorry, you did not pass. Please study and try again.', 'acf-quiz');
        
        if (empty($questions)) {
            wp_send_json_error(array('message' => __('Quiz configuration error.', 'acf-quiz')));
        }
        
        // Get submitted answers
        $submitted_answers = isset($_POST['answers']) ? $_POST['answers'] : array();
        
        // Calculate score
        $total_questions = count($questions);
        $correct_answers = 0;
        $detailed_results = array();
        
        foreach ($questions as $q_index => $question) {
            $question_key = 'question_' . $q_index;
            $submitted_answer_index = isset($submitted_answers[$question_key]) ? intval($submitted_answers[$question_key]) : -1;
            
            $is_correct = false;
            if ($submitted_answer_index >= 0 && isset($question['answers'][$submitted_answer_index])) {
                $is_correct = !empty($question['answers'][$submitted_answer_index]['is_correct']);
            }
            
            if ($is_correct) {
                $correct_answers++;
            }
            
            $detailed_results[] = array(
                'question_index' => $q_index,
                'submitted_answer' => $submitted_answer_index,
                'is_correct' => $is_correct,
                'explanation' => isset($question['explanation']) ? $question['explanation'] : ''
            );
        }
        
        // Calculate percentage
        $score_percentage = round(($correct_answers / $total_questions) * 100);
        $passed = $score_percentage >= $passing_percentage;
        
        // Prepare response
        $response = array(
            'score_percentage' => $score_percentage,
            'correct_answers' => $correct_answers,
            'total_questions' => $total_questions,
            'passed' => $passed,
            'message' => $passed ? $success_message : $failure_message,
            'passing_percentage' => $passing_percentage,
            'detailed_results' => $detailed_results
        );
        
        wp_send_json_success($response);
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
