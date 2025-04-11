<?php
/**
 * Circle.so Events Migration Handler
 *
 * @package Circle_Events
 */

declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Circle_Events_Migration
 * 
 * Handles version migrations and upgrades
 */
class Circle_Events_Migration {

    /**
     * The encryption handler
     * 
     * @var Circle_Events_Encryption
     */
    private Circle_Events_Encryption $encryption;

    /**
     * Constructor
     * 
     * @param Circle_Events_Encryption $encryption The encryption handler
     */
    public function __construct(Circle_Events_Encryption $encryption) {
        $this->encryption = $encryption;
    }

    /**
     * Run migration tasks
     * 
     * @param string $current_version The current plugin version
     * @param string $previous_version The previous plugin version
     * @return void
     */
    public function run_migrations(string $current_version, string $previous_version): void {
        // Check if this is a new installation
        if (empty($previous_version)) {
            // No migrations needed for fresh install
            update_option('circle_events_version', $current_version);
            return;
        }

        // Run version-specific migrations
        if (version_compare($previous_version, '1.0.1', '<=')) {
            $this->migrate_to_1_1_0();
        }

        // Update stored version
        update_option('circle_events_version', $current_version);
    }

    /**
     * Migrate to version 1.1.0
     * 
     * @return void
     */
    private function migrate_to_1_1_0(): void {
        // Encrypt API token if it exists in plaintext
        $settings = get_option('circle_events_settings', []);
        
        if (!empty($settings['api_token'])) {
            // Check if the token is already encrypted
            // (Basic check: encrypted tokens should be longer and contain base64 characters)
            $api_token = $settings['api_token'];
            
            // If the token is shorter than 64 chars, it's probably not encrypted yet
            if (strlen($api_token) < 64 || !preg_match('/^[a-zA-Z0-9\/\+\=]+$/', $api_token)) {
                // Encrypt the token
                $settings['api_token'] = $this->encryption->encrypt($api_token);
                
                // Save the updated settings
                update_option('circle_events_settings', $settings);
            }
        }
        
        // Clear all caches
        delete_transient('circle_events_data');
    }
} 