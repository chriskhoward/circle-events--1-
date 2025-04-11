=== Circle.so Events Integration ===
Contributors: [your username]
Tags: circle, events, community, circle.so
Requires at least: 5.6
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display events from your Circle.so community directly on your WordPress website.

== Description ==

Circle.so Events Integration allows you to seamlessly display events from your Circle.so community on your WordPress website. Keep your website visitors informed about upcoming events without requiring them to log in to your Circle.so community.

= Features =

* Display upcoming Circle.so events on your WordPress site via shortcode or widget
* Customizable display options for event details (date, time, description, location)
* Cache API results for improved performance
* Responsive design that looks great on all devices
* Widget support for sidebar display
* Shortcode support for embedding in posts and pages
* Admin settings page for easy configuration

= Usage =

1. Install and activate the plugin
2. Go to Settings > Circle.so Events to configure your API credentials
3. Add the [circle_events] shortcode to any post or page, or use the "Circle.so Events" widget in your sidebar

= Shortcode Options =

The basic shortcode is: [circle_events]

Optional parameters:
* limit="5" - Number of events to display (overrides settings)
* show_date="0" - Hide date (1 to show)
* show_time="0" - Hide time (1 to show)
* show_description="0" - Hide description (1 to show)
* show_location="0" - Hide location (1 to show)

Example: [circle_events limit="3" show_description="0"]

= Requirements =

* WordPress 5.6 or higher
* PHP 7.4 or higher
* A Circle.so community with an active API token

== Installation ==

1. Upload the `circle-events` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Circle.so Events to configure your API credentials
4. Add the [circle_events] shortcode to any post or page, or use the widget

== Frequently Asked Questions ==

= Where do I find my Circle.so API token? =

You can generate an API token in your Circle.so account settings. Go to your Circle.so admin dashboard, navigate to Settings > API, and create a new API token.

= Where do I find my Circle.so community ID? =

Your community ID can be found in the URL of your Circle.so community. For example, if your community URL is https://community.circle.so/c/12345, your community ID is 12345.

= How often does the plugin update the events data? =

By default, the plugin caches API results for 1 hour to improve performance. You can adjust this interval in the plugin settings.

= Can I customize the appearance of the events list? =

Yes, you can customize the appearance by adding custom CSS to your theme. The plugin includes basic styling that works with most themes, but you can override these styles as needed.

== Screenshots ==

1. Events displayed on the frontend
2. Widget configuration
3. Plugin settings page

== Changelog ==

= 1.0.1 =
* Fixed API base URL to use correct endpoint
* Added CST timezone conversion for event times
* Improved error handling for API requests

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.1 =
* API endpoint URL has been updated to fix connection issues
* Event times are now displayed in CST timezone
* Improved error handling for better debugging

= 1.0.0 =
Initial release of Circle.so Events Integration.