<?php
/**
 * Plugin Name:       Product Finder
 * Description:       A Gutenberg-native product finder for WooCommerce block-theme stores.
 * Version:           1.0.0
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       product-finder
 *
 * @package ProductFinder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Packaging for distribution (WordPress.org release build) needs to run
// `composer install --no-dev` first so this file exists — see the "starter
// template packaging" step in PRODUCT-FINDER-PROPOSAL.md's build order.
require_once __DIR__ . '/vendor/autoload.php';

/**
 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function product_finder_product_finder_block_init() {
	wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
}
add_action( 'init', 'product_finder_product_finder_block_init' );

/**
 * The attribute-mapping admin screen (build order step 7) needs WooCommerce
 * active — its own data (categories, attributes) doesn't exist otherwise.
 * Hooked to woocommerce_loaded (fired once WooCommerce's own bootstrap is
 * done) rather than plugins_loaded, since a class_exists( 'WooCommerce' )
 * check on plugins_loaded would race against WooCommerce defining its own
 * class inside its own plugins_loaded callback.
 */
function product_finder_register_admin_page() {
	\ProductFinder\Admin\SettingsPage::register();
}
add_action( 'woocommerce_loaded', 'product_finder_register_admin_page' );
