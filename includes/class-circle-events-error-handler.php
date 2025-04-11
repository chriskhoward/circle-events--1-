<?php
/**
 * Circle.so Events Error Handler
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Error_Handler
 * 
 * Handles error management and logging
 */
class Circle_Events_Error_Handler {

    /**
     * Error log file path
     *
     * @var string
     */
    private string $log_file;

    /**
     * Whether to log errors to file
     *
     * @var bool
     */
    private bool $log_to_file;

    /**
     * Constructor
     * 
     * @param bool $log_to_file Whether to log errors to a file
     */
    public function __construct(bool $log_to_file = false) {
        $this->log_to_file = $log_to_file;
        
        if ($log_to_file) {
            $upload_dir = wp_upload_dir();
            $this->log_file = trailingslashit($upload_dir['basedir']) . 'circle-events-log.txt';
        }
    }

    /**
     * Log an API error
     *
     * @param string $endpoint   The API endpoint
     * @param mixed  $error      The error object or message
     * @param array  $context    Additional context information
     * @param bool   $is_critical Whether this is a critical error
     */
    public function log_api_error(string $endpoint, $error, array $context = [], bool $is_critical = false): void {
        // Format error message
        $error_message = $this->format_error_message($endpoint, $error, $context);
        
        // Add to WordPress error log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($error_message);
        }
        
        // Log to custom file if enabled
        if ($this->log_to_file) {
            $this->write_to_log_file($error_message, $is_critical);
        }
        
        // For critical errors, send email to admin
        if ($is_critical && !empty($context['notification']) && $context['notification']) {
            $this->notify_admin($error_message);
        }
    }
    
    /**
     * Format error message for logging
     *
     * @param string $endpoint The API endpoint
     * @param mixed  $error    The error object or message
     * @param array  $context  Additional context
     * @return string Formatted error message
     */
    private function format_error_message(string $endpoint, $error, array $context): string {
        $timestamp = current_time('mysql');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_id = get_current_user_id();
        
        // Format the error, handling different error types
        $error_text = '';
        if (is_wp_error($error)) {
            $error_text = $error->get_error_code() . ': ' . $error->get_error_message();
        } elseif (is_object($error) && method_exists($error, 'getMessage')) {
            $error_text = get_class($error) . ': ' . $error->getMessage();
        } else {
            $error_text = (string) $error;
        }
        
        // Build the message
        $message = sprintf(
            '[%s] [Circle Events] [%s] [User ID: %d] [IP: %s] API Error: %s',
            $timestamp,
            $endpoint,
            $user_id,
            $ip,
            $error_text
        );
        
        // Add context data if available
        if (!empty($context)) {
            $context_str = json_encode($context, JSON_PRETTY_PRINT);
            $message .= "\nContext: " . $context_str;
        }
        
        return $message;
    }
    
    /**
     * Write error message to log file
     *
     * @param string $message    The formatted error message
     * @param bool   $is_critical Whether this is a critical error
     */
    private function write_to_log_file(string $message, bool $is_critical): void {
        if (empty($this->log_file)) {
            return;
        }
        
        // Add newline
        $message .= "\n";
        
        // For critical errors, add visual separator
        if ($is_critical) {
            $separator = str_repeat('=', 80) . "\n";
            $message = $separator . $message . $separator;
        }
        
        // Append to log file
        file_put_contents($this->log_file, $message, FILE_APPEND);
    }
    
    /**
     * Notify admin about critical errors
     *
     * @param string $message The error message
     */
    private function notify_admin(string $message): void {
        $admin_email = get_option('admin_email');
        if (empty($admin_email)) {
            return;
        }
        
        $site_name = get_bloginfo('name');
        $subject = sprintf('[%s] Circle Events Critical Error', $site_name);
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Format user-friendly error message
     *
     * @param mixed   $error     The error object or message
     * @param boolean $include_technical Whether to include technical details
     * @return array Error data with 'message' and 'technical_details' keys
     */
    public function format_user_error($error, bool $include_technical = false): array {
        $result = [
            'message' => __('An error occurred while processing your request.', 'circle-events'),
            'technical_details' => '',
        ];
        
        // Get user-friendly message based on error type
        if (is_wp_error($error)) {
            $code = $error->get_error_code();
            
            switch ($code) {
                case 'http_request_failed':
                    $result['message'] = __('Could not connect to the Circle.so API. Please check your internet connection and try again.', 'circle-events');
                    break;
                    
                case 'missing_credentials':
                    $result['message'] = __('API credentials are missing. Please configure your API token and community ID in the settings.', 'circle-events');
                    break;
                    
                case 'invalid_response':
                    $result['message'] = __('Received an invalid response from the Circle.so API. Please try again later.', 'circle-events');
                    break;
                    
                case 'api_error':
                    $result['message'] = __('The Circle.so API returned an error. Please check your API credentials and try again.', 'circle-events');
                    break;
                    
                case 'rate_limit_exceeded':
                    $result['message'] = __('Rate limit exceeded. Please try again in a few minutes.', 'circle-events');
                    break;
                    
                default:
                    // Use error message if it seems user-friendly
                    $error_message = $error->get_error_message();
                    if (strlen($error_message) < 100 && !strpos($error_message, '<')) {
                        $result['message'] = $error_message;
                    }
                    break;
            }
            
            if ($include_technical) {
                $result['technical_details'] = sprintf(
                    '%s: %s',
                    $error->get_error_code(),
                    $error->get_error_message()
                );
            }
        } elseif (is_object($error) && method_exists($error, 'getMessage')) {
            if ($include_technical) {
                $result['technical_details'] = sprintf(
                    '%s: %s',
                    get_class($error),
                    $error->getMessage()
                );
            }
        } elseif (is_string($error)) {
            if ($include_technical) {
                $result['technical_details'] = $error;
            }
        }
        
        return $result;
    }
    
    /**
     * Render user-friendly error message in HTML
     *
     * @param mixed   $error     The error object or message
     * @param boolean $include_technical Whether to include technical details
     * @return string HTML for the error message
     */
    public function render_error($error, bool $include_technical = false): string {
        $error_data = $this->format_user_error($error, $include_technical && current_user_can('manage_options'));
        
        $html = '<div class="circle-events-error">';
        $html .= '<p>' . esc_html($error_data['message']) . '</p>';
        
        if (!empty($error_data['technical_details'])) {
            $html .= '<details>';
            $html .= '<summary>' . esc_html__('Technical Details', 'circle-events') . '</summary>';
            $html .= '<p class="circle-events-technical-details">' . esc_html($error_data['technical_details']) . '</p>';
            $html .= '</details>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
} 