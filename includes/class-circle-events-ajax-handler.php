<?php
/**
 * Circle.so Events AJAX Handler
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Ajax_Handler
 * 
 * Handles AJAX requests for Circle.so events
 */
class Circle_Events_Ajax_Handler {
    /**
     * Settings manager instance
     * 
     * @var Circle_Events_Settings_Manager
     */
    private Circle_Events_Settings_Manager $settings_manager;

    /**
     * Template manager instance
     * 
     * @var Circle_Events_Template_Manager
     */
    private Circle_Events_Template_Manager $template_manager;

    /**
     * API instance
     * 
     * @var Circle_Events_API
     */
    private Circle_Events_API $api;

    /**
     * Cache manager instance
     * 
     * @var Circle_Events_Cache_Manager
     */
    private Circle_Events_Cache_Manager $cache_manager;

    /**
     * Input sanitizer instance
     * 
     * @var Circle_Events_Input_Sanitizer
     */
    private Circle_Events_Input_Sanitizer $input_sanitizer;

    /**
     * Capability checker instance
     * 
     * @var Circle_Events_Capability_Checker
     */
    private Circle_Events_Capability_Checker $capability_checker;

    /**
     * Constructor
     *
     * @param Circle_Events_Settings_Manager    $settings_manager   Settings manager instance
     * @param Circle_Events_Template_Manager    $template_manager   Template manager instance
     * @param Circle_Events_API                 $api                API instance
     * @param Circle_Events_Cache_Manager       $cache_manager      Cache manager instance
     * @param Circle_Events_Input_Sanitizer     $input_sanitizer    Input sanitizer instance
     * @param Circle_Events_Capability_Checker  $capability_checker Capability checker instance
     */
    public function __construct(
        Circle_Events_Settings_Manager $settings_manager,
        Circle_Events_Template_Manager $template_manager,
        Circle_Events_API $api,
        Circle_Events_Cache_Manager $cache_manager,
        Circle_Events_Input_Sanitizer $input_sanitizer,
        Circle_Events_Capability_Checker $capability_checker
    ) {
        $this->settings_manager = $settings_manager;
        $this->template_manager = $template_manager;
        $this->api = $api;
        $this->cache_manager = $cache_manager;
        $this->input_sanitizer = $input_sanitizer;
        $this->capability_checker = $capability_checker;
    }

    /**
     * Initialize AJAX handlers
     */
    public function init(): void {
        // AJAX handler for testing API connection
        add_action('wp_ajax_circle_events_test_connection', [$this, 'ajax_test_connection']);
        
        // AJAX handlers for template rendering
        add_action('wp_ajax_circle_events_get_widget_template', [$this, 'ajax_get_widget_template']);
        add_action('wp_ajax_nopriv_circle_events_get_widget_template', [$this, 'ajax_get_widget_template']);
        
        add_action('wp_ajax_circle_events_get_list_template', [$this, 'ajax_get_list_template']);
        add_action('wp_ajax_nopriv_circle_events_get_list_template', [$this, 'ajax_get_list_template']);
        
        add_action('wp_ajax_circle_events_get_calendar_template', [$this, 'ajax_get_calendar_template']);
        add_action('wp_ajax_nopriv_circle_events_get_calendar_template', [$this, 'ajax_get_calendar_template']);
    }

    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_connection(): void {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'circle_events_admin_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'circle-events')]);
        }
        
        // Check if user has permission
        if (!$this->capability_checker->can_manage_settings()) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'circle-events')]);
        }
        
        // Get API credentials
        $api_token = isset($_POST['api_token']) ? $this->input_sanitizer->sanitize_text($_POST['api_token']) : '';
        $community_id = isset($_POST['community_id']) ? $this->input_sanitizer->sanitize_text($_POST['community_id']) : '';
        
        if (empty($api_token) || empty($community_id)) {
            wp_send_json_error(['message' => __('API token and community ID are required.', 'circle-events')]);
        }
        
        // Test connection
        $test_result = $this->api->test_connection();
        
        if ($test_result['success']) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => $test_result['message']]);
        }
    }

    /**
     * AJAX handler for getting widget template
     */
    public function ajax_get_widget_template(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'circle_events_ajax_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'circle-events')]);
        }
        
        // Verify request data
        if (!isset($_POST['events']) || !is_array($_POST['events'])) {
            wp_send_json_error(['message' => __('Invalid request data.', 'circle-events')]);
        }
        
        $events = $this->input_sanitizer->sanitize_array($_POST['events']);
        
        // Get display options from settings or request
        $display_options = [];
        
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            $display_options = $this->input_sanitizer->sanitize_array($_POST['options']);
        } else {
            $display_options = $this->settings_manager->get_display_options();
        }
        
        // Render template
        $output = $this->template_manager->render_widget_template($events, $display_options);
        
        wp_send_json_success($output);
    }

    /**
     * AJAX handler for getting list template
     */
    public function ajax_get_list_template(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'circle_events_ajax_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'circle-events')]);
        }
        
        // Verify request data
        if (!isset($_POST['events']) || !is_array($_POST['events'])) {
            wp_send_json_error(['message' => __('Invalid request data.', 'circle-events')]);
        }
        
        $events = $this->input_sanitizer->sanitize_array($_POST['events']);
        
        // Get display options from settings or request
        $display_options = [];
        
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            $display_options = $this->input_sanitizer->sanitize_array($_POST['options']);
        } else {
            $display_options = $this->settings_manager->get_display_options();
        }
        
        // Get pagination data
        $pagination = [];
        if (isset($_POST['pagination']) && is_array($_POST['pagination'])) {
            $pagination = $this->input_sanitizer->sanitize_array($_POST['pagination']);
        }
        
        // Render template
        $output = $this->template_manager->render_list_template($events, $display_options, $pagination);
        
        wp_send_json_success($output);
    }

    /**
     * AJAX handler for getting calendar template
     */
    public function ajax_get_calendar_template(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'circle_events_ajax_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'circle-events')]);
        }
        
        // Verify request data
        if (!isset($_POST['events']) || !is_array($_POST['events'])) {
            wp_send_json_error(['message' => __('Invalid request data.', 'circle-events')]);
        }
        
        $events = $this->input_sanitizer->sanitize_array($_POST['events']);
        
        // Get display options from settings or request
        $display_options = [];
        
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            $display_options = $this->input_sanitizer->sanitize_array($_POST['options']);
        } else {
            $display_options = $this->settings_manager->get_display_options();
        }
        
        // Render template
        $output = $this->template_manager->render_calendar_template($events, $display_options);
        
        wp_send_json_success($output);
    }
} 