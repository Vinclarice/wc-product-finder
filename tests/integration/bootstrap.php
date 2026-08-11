<?php
/**
 * Bootstrap for integration tests (WP_UnitTestCase) — the tier that needs a
 * real WordPress + WooCommerce environment, unlike the plain unit tests in
 * tests/php/. Runs inside wp-env's tests-cli container, which already has
 * the WP core PHPUnit test suite installed at $WP_TESTS_DIR.
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	fwrite( STDERR, "WP_TESTS_DIR is not set — integration tests must run inside wp-env's tests-cli container.\n" );
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Loads this plugin and WooCommerce into the test environment before WordPress
 * finishes booting, the same way a real site would load them as plugins.
 */
function _product_finder_load_plugins_for_tests() {
	// wp-env installs WooCommerce from a direct zip URL (see .wp-env.json), so its
	// plugin directory is named after the zip (e.g. "woocommerce.latest-stable"),
	// not the "woocommerce" slug — glob it rather than hardcoding that name.
	$woocommerce_main_file = glob( WP_CONTENT_DIR . '/plugins/woocommerce*/woocommerce.php' )[0] ?? null;
	if ( ! $woocommerce_main_file ) {
		fwrite( STDERR, "Could not locate WooCommerce's main plugin file under wp-content/plugins/.\n" );
		exit( 1 );
	}
	require $woocommerce_main_file;

	require dirname( __DIR__, 2 ) . '/product-finder.php';
}
tests_add_filter( 'muplugins_loaded', '_product_finder_load_plugins_for_tests' );

require $_tests_dir . '/includes/bootstrap.php';
