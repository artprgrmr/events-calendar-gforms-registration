<?php
/**
 * Plugin Name: Events Calendar GForms Registration
 * Plugin URI:  https://github.com/artprgrmr/events-calendar-gforms-registration
 * Description: Use Gravity Forms to handle registration for The Events Calendar events. Updated version by artprgrmr.
 * Version:     0.2.1
 * Author:      ForwardJump (updated by artprgrmr)
 * Author URI:  https://github.com/artprgrmr/events-calendar-gforms-registration
 *
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Text Domain: events-calendar-gforms-registration
 * Domain Path: /languages
 *
 * Requires PHP: 8.x
 *
 * GitHub Plugin URI: https://github.com/artprgrmr/events-calendar-gforms-registration
 *
 * @package ForwardJump\ECGF_Registration
 */

namespace ForwardJump\ECGF_Registration;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define( 'ECGF_DIR', __DIR__ );
define( 'ECGF_PATH', __FILE__ );
define( 'ECGF_URL', plugins_url( '', __FILE__ ) );
define( 'ECGF_CONFIG_DIR', __DIR__ . '/config' );
define( 'ECGF_DIR_TEXT_DOMAIN', 'events-calendar-gforms-registration' );

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 5 );
/**
 * Checks for dependencies before loading plugin files.
 *
 * @since 0.1.2
 * @return void
 */
function init() {

	if (
		! class_exists( 'Tribe__Events__Main' ) ||
		! class_exists( 'GFForms' ) ||
		version_compare( PHP_VERSION, '8.0.0', '<' )
	) {

		add_action( 'admin_notices', __NAMESPACE__ . '\\activation_error_notice' );
		add_action( 'admin_init', __NAMESPACE__ . '\\deactivate_plugin' );

		return;
	}

	require_once ECGF_DIR . '/vendor/autoload.php';

	load_admin_files();

	load_frontend_files();
}

/**
 * Loads admin files.
 *
 * @return void
 */
function load_admin_files() {
	if ( ! is_admin() ) {
		return;
	}

	require_once ECGF_DIR . '/vendor/CMB2/init.php';
	require_once ECGF_DIR . '/src/admin-functions.php';
}

/**
 * Loads front end files.
 *
 * @return void
 */
function load_frontend_files() {
	if ( is_admin() ) {
		return;
	}

	require_once ECGF_DIR . '/src/frontend-functions.php';
}

/**
 * Deactivation notice.
 *
 * @since 0.1.0
 */
function activation_error_notice() {

	$plugin_data = get_plugin_data( ECGF_PATH );

	if ( ! class_exists( 'Tribe__Events__Main' ) || ! class_exists( 'GFForms' ) ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p>Error activating
				<b><?php echo esc_html( isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : 'plugin' ); ?></b>. Please
				activate The Events Calendar and Gravity Forms plugins, then try again.</p>
		</div>
		<?php
	}

	if ( version_compare( PHP_VERSION, '8.0.0', '<' ) ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p>Error activating
				<b><?php echo esc_html( isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : 'plugin' ); ?></b>. This plugin requires a minimum PHP version of 8.0.0.</p>
		</div>
		<?php
	}
}

/**
 * Deactivates this plugin.
 *
 * @return void
 */
function deactivate_plugin() {
	deactivate_plugins( ECGF_PATH, true );
}
