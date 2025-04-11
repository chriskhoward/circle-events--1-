<?php
/**
 * Circle.so Events API Handler
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_API
 * 
 * Handles communication with the Circle.so API
 */
class Circle_Events_API {
    /**
     * API base URL for the Circle.so API
     * 
     * @var string
     */
    private string $api_base_url = 'https://app.circle.so/api/v1';

    /**
     * API token
     * 
     * @var string
     */
    private string $api_token;

    /**
     * Community ID
     * 
     * @var string
     */
    private string $community_id;

    /**
     * Debug mode flag
     * 
     * @var bool
     */
    private bool $debug_mode;

    /**
     * Rate limiter instance
     * 
     * @var Circle_Events_Rate_Limiter
     */
    private Circle_Events_Rate_Limiter $rate_limiter;

    /**
     * Error handler instance
     * 
     * @var Circle_Events_Error_Handler
     */
    private Circle_Events_Error_Handler $error_handler;

    /**
     * Constructor
     * 
     * @param string $api_token    The API token.
     * @param string $community_id The community ID.
     * @param Circle_Events_Rate_Limiter|null $rate_limiter Optional rate limiter instance
     * @param Circle_Events_Error_Handler|null $error_handler Optional error handler instance
     */
    public function __construct(
        string $api_token, 
        string $community_id, 
        Circle_Events_Rate_Limiter $rate_limiter = null,
        Circle_Events_Error_Handler $error_handler = null
    ) {
        $this->api_token = $api_token;
        $this->community_id = $community_id;
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        $this->rate_limiter = $rate_limiter ?? new Circle_Events_Rate_Limiter();
        $this->error_handler = $error_handler ?? new Circle_Events_Error_Handler();
    }

    /**
     * Log a debug message if debug mode is enabled
     * 
     * @param string $message The message to log.
     * @param mixed  $context Optional context data.
     */
    private function log_debug(string $message, $context = null): void {
        if (!$this->debug_mode) {
            return;
        }

        $log_message = '[Circle Events] ' . $message;
        if ($context !== null) {
            $log_message .= "\nContext: " . print_r($context, true);
        }

        error_log($log_message);
    }

    /**
     * Validate API configuration
     * 
     * @return WP_Error|true True if valid, WP_Error otherwise.
     */
    private function validate_config() {
        if (empty($this->api_token)) {
            $this->log_debug('API token is empty');
            return new WP_Error(
                'invalid_config',
                __('API token is not configured. Please check your settings.', 'circle-events')
            );
        }

        if (empty($this->community_id)) {
            $this->log_debug('Community ID is empty');
            return new WP_Error(
                'invalid_config',
                __('Community ID is not configured. Please check your settings.', 'circle-events')
            );
        }

        return true;
    }

    /**
     * Build the full API URL
     * 
     * @param string $endpoint The API endpoint.
     * @return string The full API URL.
     */
    private function build_url(string $endpoint): string {
        // Remove any leading/trailing slashes and ensure proper path construction
        $endpoint = trim($endpoint, '/');
        $base_url = rtrim($this->api_base_url, '/');
        
        // For community-specific endpoints, prepend the community ID
        if (!str_starts_with($endpoint, 'me') && !str_starts_with($endpoint, 'communities/')) {
            $endpoint = "communities/{$this->community_id}/{$endpoint}";
        }
        
        return "{$base_url}/{$endpoint}";
    }

    /**
     * Prepare request arguments
     * 
     * @param array  $params The request parameters.
     * @param string $method The HTTP method.
     * @return array The prepared request arguments.
     */
    private function prepare_request_args(array $params, string $method): array {
        $args = [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Token ' . trim($this->api_token),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        if ($method !== 'GET') {
            $args['method'] = $method;
            if (!empty($params)) {
                $args['body'] = wp_json_encode($params);
            }
        }

        return $args;
    }

    /**
     * Make a request to the Circle.so API
     * 
     * @param string $endpoint The API endpoint.
     * @param array  $params   The request parameters.
     * @param string $method   The HTTP method.
     * 
     * @return array|WP_Error The response or error.
     */
    private function make_request(string $endpoint, array $params = [], string $method = 'GET') {
        // Validate configuration
        $config_validation = $this->validate_config();
        if (is_wp_error($config_validation)) {
            $this->error_handler->log_api_error('config_validation', $config_validation);
            return $config_validation;
        }

        // Check rate limits
        if (!$this->rate_limiter->check_rate_limit($endpoint)) {
            $error = new WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'circle-events'),
                ['status' => 429]
            );
            $this->error_handler->log_api_error($endpoint, $error, [
                'method' => $method,
                'params' => $params
            ]);
            return $error;
        }

        // Build URL and args
        $url = $this->build_url($endpoint);
        $args = $this->prepare_request_args($params, $method);

        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        }

        // Log request details
        $this->log_debug('API Request', [
            'URL' => $url,
            'Method' => $method,
            'Headers' => $args['headers'],
            'Params' => $params,
            'API Token Length' => strlen($this->api_token),
            'API Token First/Last 4' => substr($this->api_token, 0, 4) . '...' . substr($this->api_token, -4),
        ]);

        // Make the request
        $response = wp_remote_request($url, $args);

        // Handle WP_Error response
        if (is_wp_error($response)) {
            $this->log_debug('API Request Error', $response->get_error_message());
            $this->error_handler->log_api_error($endpoint, $response, [
                'method' => $method,
                'url' => $url
            ]);
            return $response;
        }

        // Get response details
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Log response details
        $this->log_debug('API Response', [
            'Status Code' => $response_code,
            'Headers' => $response_headers,
            'Body' => $response_body,
        ]);

        // Parse JSON response
        $response_data = json_decode($response_body, true);

        // Handle non-200 responses
        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($response_data['error']) 
                ? $response_data['error'] 
                : sprintf(__('API request failed with status code: %d', 'circle-events'), $response_code);

            $this->log_debug('API Error Response', [
                'Status' => $response_code,
                'Message' => $error_message,
                'URL' => $url,
            ]);

            $error = new WP_Error('api_error', $error_message, ['status' => $response_code]);
            $this->error_handler->log_api_error($endpoint, $error, [
                'response_code' => $response_code,
                'response_body' => $response_body,
                'url' => $url,
                'method' => $method
            ]);
            
            return $error;
        }

        // Handle invalid JSON
        if (empty($response_data) && !empty($response_body)) {
            $this->log_debug('Invalid JSON Response', $response_body);
            $error = new WP_Error(
                'invalid_response',
                __('Invalid response format from API', 'circle-events')
            );
            $this->error_handler->log_api_error($endpoint, $error, [
                'response_body' => $response_body
            ]);
            
            return $error;
        }

        // Add rate limit status to response data
        $rate_limit_status = $this->rate_limiter->get_rate_limit_status($endpoint);
        if (is_array($response_data)) {
            $response_data['rate_limit'] = $rate_limit_status;
        }

        return $response_data;
    }

    /**
     * Test API connection
     * 
     * @return array The test result.
     */
    public function test_connection(): array {
        try {
            // Try to get user info
            $response = $this->make_request('me');

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => $response->get_error_message(),
                ];
            }

            // Check for valid user data
            if (empty($response) || !isset($response['id'])) {
                return [
                    'success' => false,
                    'message' => __('Invalid response from Circle.so API. Please check your API token.', 'circle-events'),
                ];
            }

            // Verify admin access
            if (!isset($response['admin']) || !$response['admin']) {
                return [
                    'success' => false,
                    'message' => __('API token does not have admin access. Please use an admin API token.', 'circle-events'),
                ];
            }

            return [
                'success' => true,
                'message' => __('Connection successful. API token has admin access.', 'circle-events'),
            ];
        } catch (Exception $e) {
            $this->log_debug('Test Connection Exception', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get events from Circle.so
     * 
     * @param int   $limit   The maximum number of events to retrieve.
     * @param array $filters Optional filters for events.
     * 
     * @return array|WP_Error The events or error.
     */
    public function get_events(int $limit = 10, array $filters = []): array|WP_Error {
        try {
            // Get community events
            $params = [
                'per_page' => $limit,
                'status' => !empty($filters['status']) ? $filters['status'] : 'upcoming',
            ];

            if (!empty($filters['category'])) {
                $params['category'] = $filters['category'];
            }

            $response = $this->make_request('events', $params);

            if (is_wp_error($response)) {
                return $response;
            }

            $events = isset($response['records']) ? $response['records'] : [];
            return $this->format_events($events);

        } catch (Exception $e) {
            $this->log_debug('Get Events Exception', $e->getMessage());
            return new WP_Error('events_exception', $e->getMessage());
        }
    }

    /**
     * Format event data
     * 
     * @param array $event The raw event data.
     * @return array The formatted event data.
     */
    private function format_event(array $event): array {
        $formatted = [
            'id' => '',
            'title' => '',
            'description' => '',
            'start_time' => '',
            'end_time' => '',
            'location' => '',
            'url' => '',
            'is_online' => false,
            'thumbnail_url' => '',
            'status' => '',
            'raw_data' => $event,
        ];

        $this->log_debug('Formatting Event', $event);

        // Map fields
        if (isset($event['id'])) {
            $formatted['id'] = sanitize_text_field($event['id']);
        }

        if (isset($event['name'])) {
            $formatted['title'] = sanitize_text_field($event['name']);
        }

        if (isset($event['description'])) {
            $formatted['description'] = wp_kses_post($event['description']);
        }

        if (isset($event['starts_at'])) {
            $formatted['start_time'] = sanitize_text_field($event['starts_at']);
        }

        if (isset($event['ends_at'])) {
            $formatted['end_time'] = sanitize_text_field($event['ends_at']);
        }

        if (isset($event['location'])) {
            $formatted['location'] = sanitize_text_field($event['location']);
        }

        if (isset($event['url'])) {
            $formatted['url'] = esc_url_raw($event['url']);
        }

        if (isset($event['is_online'])) {
            $formatted['is_online'] = (bool) $event['is_online'];
        }

        if (isset($event['thumbnail_url'])) {
            $formatted['thumbnail_url'] = esc_url_raw($event['thumbnail_url']);
        }

        if (isset($event['status'])) {
            $formatted['status'] = sanitize_text_field($event['status']);
        }

        return $formatted;
    }

    /**
     * Format multiple events
     * 
     * @param array $events The raw events data.
     * @return array The formatted events data.
     */
    private function format_events(array $events): array {
        $formatted_events = [];
        foreach ($events as $event) {
            $formatted_events[] = $this->format_event($event);
        }

        // Sort by start time
        usort($formatted_events, function($a, $b) {
            if (empty($a['start_time']) || empty($b['start_time'])) {
                return 0;
            }
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });

        return $formatted_events;
    }
}