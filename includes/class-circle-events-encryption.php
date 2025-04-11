<?php
/**
 * Circle.so Events Encryption Handler
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Encryption
 * 
 * Handles secure encryption and decryption of sensitive data
 */
class Circle_Events_Encryption {

    /**
     * The encryption key
     * 
     * @var string
     */
    private string $key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->key = $this->get_encryption_key();
    }

    /**
     * Get or generate the encryption key
     * 
     * @return string The encryption key
     */
    private function get_encryption_key(): string {
        $key = get_option('circle_events_encryption_key');
        
        if (empty($key)) {
            // Generate a secure random key
            if (function_exists('random_bytes')) {
                $key = bin2hex(random_bytes(32));
            } else {
                // Fallback for older PHP versions
                $key = bin2hex(openssl_random_pseudo_bytes(32));
            }
            
            // Store the key in options
            add_option('circle_events_encryption_key', $key);
        }
        
        // Apply WordPress salts for additional security
        if (defined('AUTH_KEY') && defined('SECURE_AUTH_KEY')) {
            $key = hash('sha256', $key . AUTH_KEY . SECURE_AUTH_KEY);
        }
        
        return $key;
    }

    /**
     * Encrypt sensitive data
     * 
     * @param string $data The data to encrypt
     * @return string The encrypted data
     */
    public function encrypt(string $data): string {
        if (empty($data)) {
            return '';
        }
        
        // Generate a random IV
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        
        // Encrypt the data
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            $this->key,
            0,
            $iv
        );
        
        // Combine IV and encrypted data and encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     * 
     * @param string $data The encrypted data
     * @return string The decrypted data
     */
    public function decrypt(string $data): string {
        if (empty($data)) {
            return '';
        }
        
        // Decode the combined data
        $data = base64_decode($data);
        
        // Extract IV and encrypted data
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        // Decrypt the data
        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            $this->key,
            0,
            $iv
        );
        
        return $decrypted !== false ? $decrypted : '';
    }
} 