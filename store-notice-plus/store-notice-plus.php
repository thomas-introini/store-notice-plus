<?php
/**
 * Plugin Name: Store Notice Plus
 * Description: A customizable, rotating, dismissible store notice banner with color controls. Safe layout (no header overlap) and mobile-friendly.
 * Version:     1.2.0
 * Author:      Thomas Introini
 * License:     GPL-2.0-or-later
 * Text Domain: store-notice-plus
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SNP_VERSION', '1.2.0' );
define( 'SNP_FILE', __FILE__ );
define( 'SNP_DIR', plugin_dir_path( __FILE__ ) );
define( 'SNP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load textdomain.
 */
add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'store-notice-plus', false, dirname( plugin_basename( SNP_FILE ) ) . '/languages' );
} );

/**
 * Defaults (used on first run and sanitize fallbacks).
 */
function snp_default_options() {
	return array(
		'enabled'          => 1,
		'messages'         => "Spedizione gratuita oltre 39€\nIscriviti alla newsletter per il 10% di sconto\nScopri i nostri abbonamenti <a href=\"/abbonamenti\">qui</a>",
		'interval'         => 6,          // seconds
		'dismiss_days'     => 7,          // cookie lifetime
		'closable'         => 1,          // allow visitors to close banner
		'render_hook'      => 'header', // 'header' | 'wp_body_open' | 'wp_footer'
		'header_selector'  => 'header, .site-header, #site-header, #masthead, .main-header',
		'sticky'           => 1,          // sticky on scroll
		'bg_color'         => '#111111',
		'text_color'       => '#ffffff',
		'link_color'       => '#ffffff',
		'close_color'      => '#ffffff',
		'close_color_hover' => '#ffffff',
		'hide_wc_notice'   => 1,          // optionally hide WooCommerce native store notice
	);
}

/**
 * Ensure options exist.
 */
register_activation_hook( SNP_FILE, function () {
	if ( ! get_option( 'snp_options' ) ) {
		add_option( 'snp_options', snp_default_options() );
	}
} );

/**
 * Admin + Frontend bootstrap.
 */
require_once SNP_DIR . 'includes/class-snp-admin.php';
require_once SNP_DIR . 'includes/class-snp-frontend.php';

add_action( 'init', function () {
	// Admin UI.
	new SNP_Admin();

	// Front-end rendering.
	if ( ! is_admin() ) {
		new SNP_Frontend();
	}
} );

/**
 * Return a cache-busting version for an asset.
 * - Uses filemtime when the file exists (best for dev & prod).
 * - Falls back to the plugin version.
 */
function snp_asset_ver( $relative_path ) {
	$abs = trailingslashit( SNP_DIR ) . ltrim( $relative_path, '/\\' );
	if ( file_exists( $abs ) ) {
		// Casting to string avoids scientific notation on some PHP versions.
		return (string) filemtime( $abs );
	}
	return defined( 'SNP_VERSION' ) ? SNP_VERSION : '1.0.0';
}


/**
 * Clean up on uninstall.
 * See uninstall.php for delete logic.
 */

