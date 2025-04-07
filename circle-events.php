<?php
/**
 * Plugin Name: Circle.so Events Integration
 * Description: Displays events from Circle.so community on your WordPress site.
 * Version: 1.0.1
 * Author: Circle Events Team
 * Text Domain: circle-events
 * Domain Path: /languages
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Define plugin constants
 */
define('CIRCLE_EVENTS_VERSION', '1.0.1');
define('CIRCLE_EVENTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CIRCLE_EVENTS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The core plugin class
 */
class Circle_Events {
    /**
     * The single instance of the class.
     *
     * @var Circle_Events|null
     */
    private static ?Circle_Events $instance = null;

    /**
     * API settings
     *
     * @var array
     */
    private array $api_settings;

    /**
     * Main Circle_Events Instance.
     * Ensures only one instance of Circle_Events is loaded or can be loaded.
     *
     * @return Circle_Events
     */
    public static function instance(): Circle_Events {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Circle_Events Constructor.
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Add settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // Register shortcode
        add_shortcode('circle_events', [$this, 'events_shortcode']);

        // Register widget
        add_action('widgets_init', [$this, 'register_widgets']);
        
        // Register REST API endpoint for AJAX refreshing
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Load dependencies
     */
    private function load_dependencies(): void {
        // Create necessary directories if they don't exist
        $this->create_plugin_directories();
        
        // Include the widget class
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-widget.php';
        
        // Include the API handler class
        require_once CIRCLE_EVENTS_PLUGIN_DIR . 'includes/class-circle-events-api.php';
    }
    
    /**
     * Create plugin directories
     */
    private function create_plugin_directories(): void {
        // Create directories if they don't exist
        $directories = [
            'includes',
            'templates',
            'assets/css',
            'assets/js',
        ];
        
        foreach ($directories as $directory) {
            $path = CIRCLE_EVENTS_PLUGIN_DIR . $directory;
            if (!file_exists($path)) {
                wp_mkdir_p($path);
            }
        }
    }
    
    /**
     * Set up AJAX handlers
     */
    private function setup_ajax_handlers(): void {
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
     * Plugin activation
     */
    public function activate(): void {
        // Add default options
        $default_settings = [
            'api_token' => '',
            'community_id' => '',
            'cache_duration' => 3600, // 1 hour
            'events_count' => 10,
            'display_options' => [
                'show_date' => true,
                'show_time' => true,
                'show_description' => true,
                'show_location' => true,
            ],
        ];
        
        add_option('circle_events_settings', $default_settings);
        
        // Create a transient to flag redirect after activation
        set_transient('circle_events_activation_redirect', true, 30);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        // Clear any scheduled events
        wp_clear_scheduled_hook('circle_events_sync');
        
        // Clear transients
        delete_transient('circle_events_data');
    }

    /**
     * Add settings page
     */
    public function add_settings_page(): void {
        add_options_page(
            __('Circle.so Events Settings', 'circle-events'),
            __('Circle.so Events', 'circle-events'),
            'manage_options',
            'circle-events-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings(): void {
        register_setting(
            'circle_events_settings_group',
            'circle_events_settings',
            [$this, 'sanitize_settings']
        );

        add_settings_section(
            'circle_events_api_settings',
            __('API Settings', 'circle-events'),
            [$this, 'render_api_settings_section'],
            'circle-events-settings'
        );

        add_settings_field(
            'circle_api_token',
            __('API Token', 'circle-events'),
            [$this, 'render_api_token_field'],
            'circle-events-settings',
            'circle_events_api_settings'
        );

        add_settings_field(
            'circle_community_id',
            __('Community ID', 'circle-events'),
            [$this, 'render_community_id_field'],
            'circle-events-settings',
            'circle_events_api_settings'
        );

        add_settings_field(
            'circle_cache_duration',
            __('Cache Duration (seconds)', 'circle-events'),
            [$this, 'render_cache_duration_field'],
            'circle-events-settings',
            'circle_events_api_settings'
        );

        add_settings_field(
            'circle_events_count',
            __('Number of Events to Display', 'circle-events'),
            [$this, 'render_events_count_field'],
            'circle-events-settings',
            'circle_events_api_settings'
        );

        add_settings_section(
            'circle_events_display_settings',
            __('Display Settings', 'circle-events'),
            [$this, 'render_display_settings_section'],
            'circle-events-settings'
        );

        add_settings_field(
            'circle_display_options',
            __('Display Options', 'circle-events'),
            [$this, 'render_display_options_field'],
            'circle-events-settings',
            'circle_events_display_settings'
        );
    }

    /**
     * Sanitize settings
     *
     * @param array $input The unsanitized settings.
     * @return array The sanitized settings.
     */
    public function sanitize_settings(array $input): array {
        $sanitized_input = [];
        
        // Sanitize API token
        $sanitized_input['api_token'] = sanitize_text_field($input['api_token']);
        
        // Sanitize community ID
        $sanitized_input['community_id'] = sanitize_text_field($input['community_id']);
        
        // Sanitize cache duration
        $sanitized_input['cache_duration'] = absint($input['cache_duration']);
        
        // Sanitize events count
        $sanitized_input['events_count'] = absint($input['events_count']);
        
        // Sanitize display options
        $sanitized_input['display_options'] = [
            'show_date' => isset($input['display_options']['show_date']) ? true : false,
            'show_time' => isset($input['display_options']['show_time']) ? true : false,
            'show_description' => isset($input['display_options']['show_description']) ? true : false,
            'show_location' => isset($input['display_options']['show_location']) ? true : false,
        ];
        
        // If settings changed, clear cache
        delete_transient('circle_events_data');
        
        return $sanitized_input;
    }

    /**
     * Render API settings section
     */
    public function render_api_settings_section(): void {
        echo '<p>' . esc_html__('Enter your Circle.so API credentials below.', 'circle-events') . '</p>';
    }

    /**
     * Render display settings section
     */
    public function render_display_settings_section(): void {
        echo '<p>' . esc_html__('Customize how events are displayed on your site.', 'circle-events') . '</p>';
    }

    /**
     * Render API token field
     */
    public function render_api_token_field(): void {
        $options = get_option('circle_events_settings');
        $api_token = $options['api_token'] ?? '';
        
        echo '<input type="password" id="circle_api_token" name="circle_events_settings[api_token]" value="' . esc_attr($api_token) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Your Circle.so API token', 'circle-events') . '</p>';
    }

    /**
     * Render community ID field
     */
    public function render_community_id_field(): void {
        $options = get_option('circle_events_settings');
        $community_id = $options['community_id'] ?? '';
        
        echo '<input type="text" id="circle_community_id" name="circle_events_settings[community_id]" value="' . esc_attr($community_id) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Your Circle.so community ID', 'circle-events') . '</p>';
    }

    /**
     * Render cache duration field
     */
    public function render_cache_duration_field(): void {
        $options = get_option('circle_events_settings');
        $cache_duration = $options['cache_duration'] ?? 3600;
        
        echo '<input type="number" id="circle_cache_duration" name="circle_events_settings[cache_duration]" value="' . esc_attr((string) $cache_duration) . '" class="small-text" min="60" />';
        echo '<p class="description">' . esc_html__('How long to cache API results in seconds (minimum 60)', 'circle-events') . '</p>';
    }

    /**
     * Render events count field
     */
    public function render_events_count_field(): void {
        $options = get_option('circle_events_settings');
        $events_count = $options['events_count'] ?? 10;
        
        echo '<input type="number" id="circle_events_count" name="circle_events_settings[events_count]" value="' . esc_attr((string) $events_count) . '" class="small-text" min="1" max="50" />';
        echo '<p class="description">' . esc_html__('Number of events to fetch and display', 'circle-events') . '</p>';
    }

    /**
     * Render display options field
     */
    public function render_display_options_field(): void {
        $options = get_option('circle_events_settings');
        $display_options = $options['display_options'] ?? [
            'show_date' => true,
            'show_time' => true,
            'show_description' => true,
            'show_location' => true,
        ];
        
        $checkboxes = [
            'show_date' => __('Show event date', 'circle-events'),
            'show_time' => __('Show event time', 'circle-events'),
            'show_description' => __('Show event description', 'circle-events'),
            'show_location' => __('Show event location', 'circle-events'),
        ];
        
        foreach ($checkboxes as $key => $label) {
            $checked = isset($display_options[$key]) && $display_options[$key] ? 'checked' : '';
            echo '<label for="circle_display_' . esc_attr($key) . '">';
            echo '<input type="checkbox" id="circle_display_' . esc_attr($key) . '" name="circle_events_settings[display_options][' . esc_attr($key) . ']" ' . $checked . ' />';
            echo esc_html($label) . '</label><br />';
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Test connection button
        $test_result = '';
        if (isset($_POST['circle_test_connection']) && check_admin_referer('circle_test_connection_nonce')) {
            $test_result = $this->test_api_connection();
        }
        
        // Manual cache refresh button
        if (isset($_POST['circle_refresh_cache']) && check_admin_referer('circle_refresh_cache_nonce')) {
            delete_transient('circle_events_data');
            $this->get_events_data(true);
            $cache_refreshed = true;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (!empty($test_result)) : ?>
                <div class="notice <?php echo $test_result['success'] ? 'notice-success' : 'notice-error'; ?>">
                    <p><?php echo esc_html($test_result['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($cache_refreshed)) : ?>
                <div class="notice notice-success">
                    <p><?php esc_html_e('Cache refreshed successfully!', 'circle-events'); ?></p>
                </div>
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('circle_events_settings_group');
                do_settings_sections('circle-events-settings');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Tools', 'circle-events'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('circle_test_connection_nonce'); ?>
                <p>
                    <input type="submit" name="circle_test_connection" class="button button-secondary" value="<?php esc_attr_e('Test API Connection', 'circle-events'); ?>" />
                    <span class="description"><?php esc_html_e('Test your API token and community ID', 'circle-events'); ?></span>
                </p>
            </form>
            
            <form method="post" action="">
                <?php wp_nonce_field('circle_refresh_cache_nonce'); ?>
                <p>
                    <input type="submit" name="circle_refresh_cache" class="button button-secondary" value="<?php esc_attr_e('Refresh Cache', 'circle-events'); ?>" />
                    <span class="description"><?php esc_html_e('Manually refresh events cache', 'circle-events'); ?></span>
                </p>
            </form>
            
            <hr>
            
            <h2><?php esc_html_e('Shortcode Usage', 'circle-events'); ?></h2>
            <p><?php esc_html_e('Use the following shortcode to display events in your posts or pages:', 'circle-events'); ?></p>
            <code>[circle_events]</code>
            
            <p><?php esc_html_e('Optional parameters:', 'circle-events'); ?></p>
            <ul>
                <li><code>limit="5"</code> - <?php esc_html_e('Number of events to display (overrides settings)', 'circle-events'); ?></li>
                <li><code>view="calendar"</code> - <?php esc_html_e('Display events in a calendar/grid view with images (default is "list")', 'circle-events'); ?></li>
                <li><code>category="name"</code> - <?php esc_html_e('Filter events by category/space name', 'circle-events'); ?></li>
                <li><code>days="30"</code> - <?php esc_html_e('Show events for the next X days only', 'circle-events'); ?></li>
                <li><code>past_events="1"</code> - <?php esc_html_e('Include past events (0 to hide)', 'circle-events'); ?></li>
                <li><code>status="all"</code> - <?php esc_html_e('Event status: "upcoming", "past", or "all"', 'circle-events'); ?></li>
                <li><code>order="desc"</code> - <?php esc_html_e('Sort order: "asc" (oldest first) or "desc" (newest first)', 'circle-events'); ?></li>
                <li><code>show_date="0"</code> - <?php esc_html_e('Hide date (1 to show)', 'circle-events'); ?></li>
                <li><code>show_time="0"</code> - <?php esc_html_e('Hide time (1 to show)', 'circle-events'); ?></li>
                <li><code>show_description="0"</code> - <?php esc_html_e('Hide description (1 to show)', 'circle-events'); ?></li>
                <li><code>show_location="0"</code> - <?php esc_html_e('Hide location (1 to show)', 'circle-events'); ?></li>
            </ul>
            
            <p><?php esc_html_e('Examples:', 'circle-events'); ?></p>
            <p><code>[circle_events limit="3" show_description="0"]</code> - <?php esc_html_e('List view with 3 events, description hidden', 'circle-events'); ?></p>
            <p><code>[circle_events view="calendar" limit="6"]</code> - <?php esc_html_e('Calendar/grid view with 6 events', 'circle-events'); ?></p>
            <p><code>[circle_events category="webinars" days="30" order="desc"]</code> - <?php esc_html_e('Show webinar events for the next 30 days, newest first', 'circle-events'); ?></p>
            <p><code>[circle_events view="calendar" past_events="1" status="all"]</code> - <?php esc_html_e('Calendar view showing all events including past events', 'circle-events'); ?></p>
        </div>
        <?php
    }

    /**
     * Test API connection
     * 
     * @return array Connection test result
     */
    private function test_api_connection(): array {
        $options = get_option('circle_events_settings');
        
        if (empty($options['api_token']) || empty($options['community_id'])) {
            return [
                'success' => false,
                'message' => __('API token and community ID are required.', 'circle-events'),
            ];
        }
        
        $api = new Circle_Events_API($options['api_token'], $options['community_id']);
        $test_result = $api->test_connection();
        
        if ($test_result['success']) {
            return [
                'success' => true,
                'message' => __('API connection successful!', 'circle-events'),
            ];
        } else {
            return [
                'success' => false,
                'message' => sprintf(
                    __('API connection failed: %s', 'circle-events'),
                    $test_result['message']
                ),
            ];
        }
    }

    /**
     * Register widgets
     */
    public function register_widgets(): void {
        register_widget('Circle_Events_Widget');
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        register_rest_route('circle-events/v1', '/events', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_events'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * REST API handler for events
     * 
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response The REST response.
     */
    public function rest_get_events($request) {
        $force_refresh = (bool) $request->get_param('refresh');
        
        // Build filters from request parameters
        $filters = [];
        
        // Get category filter
        $category = $request->get_param('category');
        if (!empty($category)) {
            $filters['category'] = sanitize_text_field($category);
        }
        
        // Get status filter
        $status = $request->get_param('status');
        if (!empty($status)) {
            $filters['status'] = sanitize_key($status);
        }
        
        // Get days filter
        $days = $request->get_param('days');
        if (!empty($days) && absint($days) > 0) {
            $filters['days'] = absint($days);
        }
        
        // Get past_events filter
        $past_events = $request->get_param('past_events');
        if (!empty($past_events) && absint($past_events) > 0) {
            $filters['past_events'] = true;
        }
        
        // Get events with filters
        $events = $this->get_events_data($force_refresh, $filters);
        
        if (is_wp_error($events)) {
            return rest_ensure_response([
                'success' => false,
                'message' => $events->get_error_message(),
            ]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'events' => $events,
        ]);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'circle-events-style',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/css/circle-events.css',
            [],
            CIRCLE_EVENTS_VERSION
        );
        
        wp_enqueue_script(
            'circle-events-script',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/js/circle-events.js',
            ['jquery'],
            CIRCLE_EVENTS_VERSION,
            true
        );
        
        wp_localize_script(
            'circle-events-script',
            'circle_events_params',
            [
                'ajax_url' => rest_url('circle-events/v1/events'),
                'nonce' => wp_create_nonce('wp_rest'),
            ]
        );
    }

    /**
     * Enqueue admin assets
     * 
     * @param string $hook The current admin page.
     */
    public function enqueue_admin_assets(string $hook): void {
        if ('settings_page_circle-events-settings' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'circle-events-admin-style',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/css/circle-events-admin.css',
            [],
            CIRCLE_EVENTS_VERSION
        );
        
        wp_enqueue_script(
            'circle-events-admin-script',
            CIRCLE_EVENTS_PLUGIN_URL . 'assets/js/circle-events-admin.js',
            ['jquery'],
            CIRCLE_EVENTS_VERSION,
            true
        );
        
        // Localize script with admin data
        wp_localize_script(
            'circle-events-admin-script',
            'circle_events_admin',
            [
                'nonce' => wp_create_nonce('circle_events_admin_nonce'),
                'ajax_url' => admin_url('admin-ajax.php'),
            ]
        );
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
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'circle-events')]);
        }
        
        // Get API credentials
        $api_token = isset($_POST['api_token']) ? sanitize_text_field($_POST['api_token']) : '';
        $community_id = isset($_POST['community_id']) ? sanitize_text_field($_POST['community_id']) : '';
        
        if (empty($api_token) || empty($community_id)) {
            wp_send_json_error(['message' => __('API token and community ID are required.', 'circle-events')]);
        }
        
        // Test connection
        $api = new Circle_Events_API($api_token, $community_id);
        $test_result = $api->test_connection();
        
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
        // Verify request data
        if (!isset($_POST['events']) || !is_array($_POST['events'])) {
            wp_send_json_error();
        }
        
        $events = $_POST['events'];
        
        // Get display options from settings
        $options = get_option('circle_events_settings');
        $display_options = $options['display_options'] ?? [];
        
        // Start output buffer
        ob_start();
        
        // Include template
        include CIRCLE_EVENTS_PLUGIN_DIR . 'templates/events-widget.php';
        
        // Get the output
        $output = ob_get_clean();
        
        wp_send_json_success($output);
    }
    
    /**
     * AJAX handler for getting list template
     */
    public function ajax_get_list_template(): void {
        // Verify request data
        if (!isset($_POST['events']) || !is_array($_POST['events'])) {
            wp_send_json_error();
        }
        
        $events = $_POST['events'];
        
        // Get display options from settings
        $options = get_option('circle_events_settings');
        $display_options = $options['display_options'] ?? [];
        
        // Start output buffer
        ob_start();
        
        // Include template
        include CIRCLE_EVENTS_PLUGIN_DIR . 'templates/events-list.php';
        
        // Get the output
        $output = ob_get_clean();
        
        wp_send_json_success($output);
    }

    /**
     * AJAX handler for getting calendar template
     */
    public function ajax_get_calendar_template(): void {
        // Verify request data
        if (!isset($_POST['events']) || !is_array($_POST['events'])) {
            wp_send_json_error();
        }
        
        $events = $_POST['events'];
        
        // Get display options from settings or request
        $display_options = [];
        
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            $options = $_POST['options'];
            
            // Map options to display options
            $display_options = [
                'show_date' => isset($options['show_date']) ? (bool) $options['show_date'] : true,
                'show_time' => isset($options['show_time']) ? (bool) $options['show_time'] : true,
                'show_description' => isset($options['show_description']) ? (bool) $options['show_description'] : true,
                'show_location' => isset($options['show_location']) ? (bool) $options['show_location'] : true,
            ];
        } else {
            // Get from settings
            $options = get_option('circle_events_settings');
            $display_options = $options['display_options'] ?? [];
        }
        
        // Start output buffer
        ob_start();
        
        // Include template
        include CIRCLE_EVENTS_PLUGIN_DIR . 'templates/events-calendar.php';
        
        // Get the output
        $output = ob_get_clean();
        
        wp_send_json_success($output);
    }

    /**
     * Get events data
     * 
     * @param bool $force_refresh Whether to force refresh the cache.
     * @param array $filters Optional filters for events (category, date range, etc.)
     * @return array|WP_Error The events data or error.
     */
    public function get_events_data(bool $force_refresh = false, array $filters = []) {
        // Generate a cache key based on filters
        $cache_key = 'circle_events_data';
        if (!empty($filters)) {
            $cache_key .= '_' . md5(serialize($filters));
        }
        
        // Check for cached data
        $events = get_transient($cache_key);
        
        if (false === $events || $force_refresh) {
            $options = get_option('circle_events_settings');
            
            if (empty($options['api_token']) || empty($options['community_id'])) {
                return new WP_Error(
                    'missing_credentials',
                    __('API token and community ID are required.', 'circle-events')
                );
            }
            
            $api = new Circle_Events_API($options['api_token'], $options['community_id']);
            $events_response = $api->get_events($options['events_count'], $filters);
            
            if (is_wp_error($events_response)) {
                return $events_response;
            }
            
            $events = $events_response;
            
            // Cache the data
            set_transient($cache_key, $events, $options['cache_duration']);
        }
        
        return $events;
    }

    /**
     * Filter events by date
     * 
     * @param array $events Array of events to filter
     * @param string $status 'upcoming', 'past', or 'all'
     * @return array Filtered events
     */
    private function filter_events_by_date($events, $status = 'upcoming') {
        if (empty($events) || $status === 'all') {
            return $events;
        }

        $now = current_time('timestamp');
        return array_filter($events, function($event) use ($now, $status) {
            if (empty($event['start_time'])) {
                return false;
            }
            
            $event_time = strtotime($event['start_time']);
            if ($status === 'upcoming') {
                return $event_time >= $now;
            } else if ($status === 'past') {
                return $event_time < $now;
            }
            return true;
        });
    }

    /**
     * Shortcode handler
     * 
     * @param array $atts Shortcode attributes.
     * @return string The shortcode output.
     */
    public function events_shortcode(array $atts = []): string {
        $defaults = [
            'limit' => 0, // 0 means use the default from settings
            'show_date' => null,
            'show_time' => null,
            'show_description' => null,
            'show_location' => null,
            'view' => 'list', // 'list' or 'calendar'
            'category' => '', // Filter by category/space
            'days' => 0, // Number of days to show events for (0 = all)
            'past_events' => 0, // Whether to show past events (1) or not (0)
            'status' => 'upcoming', // 'upcoming', 'past', or 'all'
            'order' => 'asc', // 'asc' or 'desc'
            'per_page' => 12, // Number of events per page
            'show_toggle' => 0, // Whether to show the past/upcoming toggle
        ];
        
        $atts = shortcode_atts($defaults, $atts, 'circle_events');
        
        // Get options
        $options = get_option('circle_events_settings');
        
        // Get all events first
        $all_events = $this->get_events_data();
        
        if (is_wp_error($all_events)) {
            return '<div class="circle-events-error">' . esc_html($all_events->get_error_message()) . '</div>';
        }
        
        // Apply category filter if specified
        if (!empty($atts['category'])) {
            $all_events = array_filter($all_events, function($event) use ($atts) {
                return isset($event['space']) && strcasecmp($event['space'], $atts['category']) === 0;
            });
        }

        // Get the current status from URL parameter or default
        $status = isset($_GET['event_status']) ? sanitize_key($_GET['event_status']) : $atts['status'];
        if (!in_array($status, ['upcoming', 'past', 'all'])) {
            $status = 'upcoming';
        }

        // Apply date filtering
        $filtered_events = $this->filter_events_by_date($all_events, $status);

        // Sort events based on status
        if ($status === 'past') {
            // Past events should show most recent first
            usort($filtered_events, function($a, $b) {
                return strtotime($b['start_time']) - strtotime($a['start_time']);
            });
        } else {
            // Upcoming and all events should show earliest first
            usort($filtered_events, function($a, $b) {
                return strtotime($a['start_time']) - strtotime($b['start_time']);
            });
        }

        // Handle pagination
        $per_page = intval($atts['per_page']);
        $current_page = isset($_GET['event_page']) ? max(1, intval($_GET['event_page'])) : 1;
        $total_events = count($filtered_events);
        $total_pages = ceil($total_events / $per_page);
        $offset = ($current_page - 1) * $per_page;
        
        // Slice the events array for the current page
        $events = array_slice($filtered_events, $offset, $per_page);
        
        // Determine display options
        $display_options = $options['display_options'] ?? [];
        
        // Override with shortcode attributes if provided
        foreach (['show_date', 'show_time', 'show_description', 'show_location'] as $opt) {
            if (null !== $atts[$opt]) {
                $display_options[$opt] = (bool) $atts[$opt];
            }
        }
        
        // Start output buffering
        ob_start();

        // Add toggle if enabled
        if (!empty($atts['show_toggle'])) {
            $current_url = remove_query_arg(['event_status', 'event_page']);
            echo '<div class="circle-events-toggle">';
            
            // Upcoming Events button
            $upcoming_url = add_query_arg('event_status', 'upcoming', $current_url);
            echo '<a href="' . esc_url($upcoming_url) . '" class="circle-events-toggle-button' . ($status === 'upcoming' ? ' active' : '') . '">' . 
                esc_html__('Upcoming Events', 'circle-events') . '</a>';
            
            // Past Events button
            $past_url = add_query_arg('event_status', 'past', $current_url);
            echo '<a href="' . esc_url($past_url) . '" class="circle-events-toggle-button' . ($status === 'past' ? ' active' : '') . '">' . 
                esc_html__('Past Events', 'circle-events') . '</a>';
            
            // All Events button
            $all_url = add_query_arg('event_status', 'all', $current_url);
            echo '<a href="' . esc_url($all_url) . '" class="circle-events-toggle-button' . ($status === 'all' ? ' active' : '') . '">' . 
                esc_html__('All Events', 'circle-events') . '</a>';
            
            // Reset button
            echo '<a href="' . esc_url($current_url) . '" class="circle-events-toggle-button circle-events-reset">' . 
                esc_html__('Reset', 'circle-events') . '</a>';
            
            echo '</div>';
        }
        
        // Include the appropriate template
        if ($atts['view'] === 'calendar') {
            include CIRCLE_EVENTS_PLUGIN_DIR . 'templates/events-calendar.php';
        } else {
            include CIRCLE_EVENTS_PLUGIN_DIR . 'templates/events-list.php';
        }
        
        return ob_get_clean();
    }
}

// Initialize the plugin
function circle_events_init() {
    Circle_Events::instance();
}
add_action('plugins_loaded', 'circle_events_init');