<?php
/**
 * Circle.so Events Settings Manager
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Settings_Manager
 * 
 * Manages all plugin settings
 */
class Circle_Events_Settings_Manager {
    /**
     * Encryption handler instance
     * 
     * @var Circle_Events_Encryption
     */
    private Circle_Events_Encryption $encryption;

    /**
     * Input sanitizer instance
     * 
     * @var Circle_Events_Input_Sanitizer
     */
    private Circle_Events_Input_Sanitizer $input_sanitizer;

    /**
     * Output escaper instance
     * 
     * @var Circle_Events_Output_Escaper
     */
    private Circle_Events_Output_Escaper $output_escaper;

    /**
     * Constructor
     *
     * @param Circle_Events_Encryption     $encryption     Encryption handler
     * @param Circle_Events_Input_Sanitizer $input_sanitizer Input sanitizer
     * @param Circle_Events_Output_Escaper  $output_escaper  Output escaper
     */
    public function __construct(
        Circle_Events_Encryption $encryption,
        Circle_Events_Input_Sanitizer $input_sanitizer,
        Circle_Events_Output_Escaper $output_escaper
    ) {
        $this->encryption = $encryption;
        $this->input_sanitizer = $input_sanitizer;
        $this->output_escaper = $output_escaper;
    }

    /**
     * Initialize settings
     */
    public function init(): void {
        // Add settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
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
        // Get current settings to check for changes
        $current_settings = get_option('circle_events_settings', []);
        
        // Use the input sanitizer to sanitize all settings
        $sanitized_input = $this->input_sanitizer->sanitize_api_settings($input, $current_settings);
        
        // Handle special case for API token encryption
        if (!empty($sanitized_input['api_token']) && 
            $sanitized_input['api_token'] !== '••••••••' && 
            $sanitized_input['api_token'] !== ($current_settings['api_token'] ?? '')) {
            // If we have a new token, encrypt it
            $sanitized_input['api_token'] = $this->encryption->encrypt($sanitized_input['api_token']);
        } else if ($sanitized_input['api_token'] === '••••••••') {
            // If the masked value was returned, keep the existing token
            $sanitized_input['api_token'] = $current_settings['api_token'] ?? '';
        }
        
        // If settings changed, clear cache
        delete_transient('circle_events_data');
        
        return $sanitized_input;
    }

    /**
     * Get plugin settings
     *
     * @return array The plugin settings
     */
    public function get_settings(): array {
        return get_option('circle_events_settings', []);
    }

    /**
     * Get API token (decrypted)
     *
     * @return string The decrypted API token
     */
    public function get_api_token(): string {
        $settings = $this->get_settings();
        $api_token = $settings['api_token'] ?? '';
        
        if (!empty($api_token)) {
            return $this->encryption->decrypt($api_token);
        }
        
        return '';
    }

    /**
     * Get community ID
     *
     * @return string The community ID
     */
    public function get_community_id(): string {
        $settings = $this->get_settings();
        return $settings['community_id'] ?? '';
    }

    /**
     * Get cache duration
     *
     * @return int The cache duration in seconds
     */
    public function get_cache_duration(): int {
        $settings = $this->get_settings();
        return (int) ($settings['cache_duration'] ?? 3600);
    }

    /**
     * Get events count
     *
     * @return int The number of events to display
     */
    public function get_events_count(): int {
        $settings = $this->get_settings();
        return (int) ($settings['events_count'] ?? 10);
    }

    /**
     * Get display options
     *
     * @return array The display options
     */
    public function get_display_options(): array {
        $settings = $this->get_settings();
        return $settings['display_options'] ?? [
            'show_date' => true,
            'show_time' => true,
            'show_description' => true,
            'show_location' => true,
        ];
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
        $options = $this->get_settings();
        $api_token = $options['api_token'] ?? '';
        
        // Show masked input if an API token exists
        $display_value = !empty($api_token) ? '••••••••' : '';
        
        echo '<input type="password" id="circle_api_token" name="circle_events_settings[api_token]" value="' . $this->output_escaper->attr($display_value) . '" class="regular-text" />';
        
        // Add show/hide button
        echo ' <button type="button" id="circle_api_token_toggle" class="button button-secondary">' . esc_html__('Show', 'circle-events') . '</button>';
        
        echo '<p class="description">' . esc_html__('Your Circle.so API token', 'circle-events') . '</p>';
    }

    /**
     * Render community ID field
     */
    public function render_community_id_field(): void {
        $options = $this->get_settings();
        $community_id = $options['community_id'] ?? '';
        
        echo '<input type="text" id="circle_community_id" name="circle_events_settings[community_id]" value="' . $this->output_escaper->attr($community_id) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Your Circle.so community ID', 'circle-events') . '</p>';
    }

    /**
     * Render cache duration field
     */
    public function render_cache_duration_field(): void {
        $options = $this->get_settings();
        $cache_duration = $options['cache_duration'] ?? 3600;
        
        echo '<input type="number" id="circle_cache_duration" name="circle_events_settings[cache_duration]" value="' . $this->output_escaper->attr((string) $cache_duration) . '" class="small-text" min="60" />';
        echo '<p class="description">' . esc_html__('How long to cache API results in seconds (minimum 60)', 'circle-events') . '</p>';
    }

    /**
     * Render events count field
     */
    public function render_events_count_field(): void {
        $options = $this->get_settings();
        $events_count = $options['events_count'] ?? 10;
        
        echo '<input type="number" id="circle_events_count" name="circle_events_settings[events_count]" value="' . $this->output_escaper->attr((string) $events_count) . '" class="small-text" min="1" max="50" />';
        echo '<p class="description">' . esc_html__('Number of events to fetch and display', 'circle-events') . '</p>';
    }

    /**
     * Render display options field
     */
    public function render_display_options_field(): void {
        $options = $this->get_settings();
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
            $checked = isset($display_options[$key]) ? (bool) $display_options[$key] : false;
            echo '<p>';
            echo '<input type="checkbox" id="circle_display_' . $this->output_escaper->attr($key) . '" 
                name="circle_events_settings[display_options][' . $this->output_escaper->attr($key) . ']" 
                value="1" ' . checked($checked, true, false) . ' />';
            echo ' <label for="circle_display_' . $this->output_escaper->attr($key) . '">' . esc_html($label) . '</label>';
            echo '</p>';
        }
    }

    /**
     * Test API connection
     *
     * @return array Result of connection test with success status and message
     */
    public function test_api_connection(): array {
        $api_token = $this->get_api_token();
        $community_id = $this->get_community_id();
        
        if (empty($api_token) || empty($community_id)) {
            return [
                'success' => false,
                'message' => __('API token and community ID are required.', 'circle-events')
            ];
        }
        
        // Create API instance
        $api = new Circle_Events_API($api_token, $community_id);
        
        // Test connection
        return $api->test_connection();
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
        </div>
        <?php
    }
} 