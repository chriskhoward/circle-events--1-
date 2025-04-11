<?php
/**
 * Circle.so Events Capability Checker
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Capability_Checker
 * 
 * Handles permission checks across the plugin
 */
class Circle_Events_Capability_Checker {

    /**
     * The default capability required for managing plugin settings
     * 
     * @var string
     */
    private string $default_admin_capability = 'manage_options';

    /**
     * The minimum capability required for viewing events
     * 
     * @var string
     */
    private string $default_view_capability = 'read';

    /**
     * Constructor
     * 
     * @param string|null $admin_cap Optional. Custom capability for admin functions.
     * @param string|null $view_cap Optional. Custom capability for viewing events.
     */
    public function __construct(?string $admin_cap = null, ?string $view_cap = null) {
        if ($admin_cap !== null) {
            $this->default_admin_capability = $admin_cap;
        }
        
        if ($view_cap !== null) {
            $this->default_view_capability = $view_cap;
        }
    }

    /**
     * Check if the current user can manage plugin settings
     * 
     * @return bool Whether the user has permission
     */
    public function can_manage_settings(): bool {
        return current_user_can($this->default_admin_capability);
    }

    /**
     * Check if the current user can view events
     * 
     * @return bool Whether the user has permission
     */
    public function can_view_events(): bool {
        return current_user_can($this->default_view_capability);
    }

    /**
     * Verify user has admin capabilities and has valid nonce
     * 
     * @param string $nonce The nonce to verify
     * @param string $action The nonce action
     * @return bool Whether the user is verified
     */
    public function verify_admin_with_nonce(string $nonce, string $action): bool {
        return $this->can_manage_settings() && wp_verify_nonce($nonce, $action);
    }

    /**
     * Die if user doesn't have admin capabilities
     * 
     * @return void
     */
    public function require_admin_capabilities(): void {
        if (!$this->can_manage_settings()) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'circle-events'),
                esc_html__('Permission Denied', 'circle-events'),
                [
                    'response' => 403,
                    'back_link' => true,
                ]
            );
        }
    }

    /**
     * Die if user doesn't have view capabilities
     * 
     * @return void
     */
    public function require_view_capabilities(): void {
        if (!$this->can_view_events()) {
            wp_die(
                esc_html__('You do not have sufficient permissions to view events.', 'circle-events'),
                esc_html__('Permission Denied', 'circle-events'),
                [
                    'response' => 403,
                    'back_link' => true,
                ]
            );
        }
    }

    /**
     * Verify a nonce in GET or POST request
     * 
     * @param string $nonce_name The name of the nonce field
     * @param string $action The nonce action
     * @return bool Whether the nonce is valid
     */
    public function verify_nonce(string $nonce_name, string $action): bool {
        $nonce = '';
        
        if (isset($_REQUEST[$nonce_name])) {
            $nonce = sanitize_text_field($_REQUEST[$nonce_name]);
        }
        
        if (empty($nonce)) {
            return false;
        }
        
        return wp_verify_nonce($nonce, $action);
    }

    /**
     * Die if nonce verification fails
     * 
     * @param string $nonce_name The name of the nonce field
     * @param string $action The nonce action
     * @return void
     */
    public function require_valid_nonce(string $nonce_name, string $action): void {
        if (!$this->verify_nonce($nonce_name, $action)) {
            wp_die(
                esc_html__('Security check failed.', 'circle-events'),
                esc_html__('Invalid Request', 'circle-events'),
                [
                    'response' => 403,
                    'back_link' => true,
                ]
            );
        }
    }

    /**
     * Verify AJAX request with capabilities and nonce
     * 
     * @param string $nonce The nonce value
     * @param string $action The nonce action
     * @param bool $admin_required Whether admin capabilities are required
     * @return bool Whether the AJAX request is valid
     */
    public function verify_ajax_request(string $nonce, string $action, bool $admin_required = true): bool {
        // Check nonce
        if (!wp_verify_nonce($nonce, $action)) {
            return false;
        }
        
        // Check capabilities
        if ($admin_required) {
            return $this->can_manage_settings();
        } else {
            return $this->can_view_events();
        }
    }

    /**
     * Send JSON error response for invalid AJAX request
     * 
     * @param string $message Optional. Custom error message.
     * @return void
     */
    public function ajax_error_response(string $message = ''): void {
        if (empty($message)) {
            $message = __('Security check failed.', 'circle-events');
        }
        
        wp_send_json_error([
            'message' => $message,
        ]);
    }

    /**
     * Check AJAX request and send error if invalid
     * 
     * @param string $nonce The nonce value
     * @param string $action The nonce action
     * @param bool $admin_required Whether admin capabilities are required
     * @return bool True if valid, false if invalid and error sent
     */
    public function check_ajax_request(string $nonce, string $action, bool $admin_required = true): bool {
        if (!$this->verify_ajax_request($nonce, $action, $admin_required)) {
            $this->ajax_error_response();
            return false;
        }
        
        return true;
    }
} 