<?php
/**
 * Circle.so Events Rate Limiter
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Rate_Limiter
 * 
 * Handles API rate limiting to prevent abuse
 */
class Circle_Events_Rate_Limiter {

    /**
     * Transient key prefix
     *
     * @var string
     */
    private string $transient_prefix = 'circle_events_rate_limit_';

    /**
     * Default rate limit per minute
     *
     * @var int
     */
    private int $default_rate_limit = 60;

    /**
     * Check if the request exceeds the rate limit
     *
     * @param string $endpoint The API endpoint being called
     * @param int    $limit    Optional custom limit for this endpoint
     * @param int    $window   Time window in seconds (default: 60)
     * @return bool  True if the request can proceed, false if rate limited
     */
    public function check_rate_limit(string $endpoint, int $limit = 0, int $window = 60): bool {
        // Get IP address for additional rate limiting by client
        $ip = $this->get_client_ip();
        
        // Generate a unique key for this endpoint and IP
        $key = $this->transient_prefix . md5($endpoint . '_' . $ip);
        
        // If no custom limit provided, use default
        if ($limit <= 0) {
            $limit = $this->default_rate_limit;
        }
        
        // Get current usage data from transient
        $usage = get_transient($key);
        
        if (false === $usage) {
            // First request in this window
            $usage = [
                'count' => 1,
                'timestamp' => time(),
            ];
            set_transient($key, $usage, $window);
            return true;
        }
        
        // Check if we're in the same time window
        $elapsed = time() - $usage['timestamp'];
        
        if ($elapsed >= $window) {
            // Time window has passed, reset counter
            $usage = [
                'count' => 1,
                'timestamp' => time(),
            ];
            set_transient($key, $usage, $window);
            return true;
        }
        
        // We're within the time window, check count
        if ($usage['count'] >= $limit) {
            // Rate limit exceeded
            $this->log_rate_limit_exceeded($endpoint, $ip, $limit, $window);
            return false;
        }
        
        // Update counter
        $usage['count']++;
        set_transient($key, $usage, $window - $elapsed);
        return true;
    }
    
    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private function get_client_ip(): string {
        $ip = '';
        
        // Check for proxy headers
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip_array = explode(',', $_SERVER[$header]);
                $ip = trim($ip_array[0]);
                break;
            }
        }
        
        // Sanitize and validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        // Default to REMOTE_ADDR if no valid IP was found
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log rate limit exceeded event
     *
     * @param string $endpoint The API endpoint
     * @param string $ip       The client IP
     * @param int    $limit    The rate limit
     * @param int    $window   The time window
     */
    private function log_rate_limit_exceeded(string $endpoint, string $ip, int $limit, int $window): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Circle Events] Rate limit exceeded: endpoint=%s, ip=%s, limit=%d, window=%d seconds',
                $endpoint,
                $ip,
                $limit,
                $window
            ));
        }
    }
    
    /**
     * Get remaining requests allowed in current window
     *
     * @param string $endpoint The API endpoint
     * @return array Array with remaining requests and reset time in seconds
     */
    public function get_rate_limit_status(string $endpoint): array {
        $ip = $this->get_client_ip();
        $key = $this->transient_prefix . md5($endpoint . '_' . $ip);
        $usage = get_transient($key);
        $limit = $this->default_rate_limit;
        
        if (false === $usage) {
            // No usage data, full limit available
            return [
                'remaining' => $limit,
                'reset' => 60,
                'limit' => $limit,
            ];
        }
        
        // Calculate remaining requests and reset time
        $reset = 60 - (time() - $usage['timestamp']);
        if ($reset < 0) {
            $reset = 0;
        }
        
        $remaining = $limit - $usage['count'];
        if ($remaining < 0) {
            $remaining = 0;
        }
        
        return [
            'remaining' => $remaining,
            'reset' => $reset,
            'limit' => $limit,
        ];
    }
} 