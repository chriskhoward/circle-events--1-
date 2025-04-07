<?php
/**
 * Uninstall routines for Circle.so Events Integration
 *
 * This file runs when the plugin is uninstalled.
 *
 * @package Circle_Events
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('circle_events_settings');

// Delete transients
delete_transient('circle_events_data');
delete_transient('circle_events_activation_redirect');

// Clear scheduled events
wp_clear_scheduled_hook('circle_events_sync');

// If using custom tables, would remove them here
// global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}circle_events"); 