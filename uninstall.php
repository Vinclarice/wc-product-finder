<?php
/**
 * Fired when the plugin is deleted via the Plugins screen.
 *
 * WordPress only includes this file (it never loads product-finder.php)
 * for the delete flow, so it needs its own guard and its own autoloader
 * require rather than relying on anything the main plugin file sets up.
 *
 * @package ProductFinder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

\ProductFinder\Finder\ConfigRepository::uninstall();
\ProductFinder\Finder\EventCounter::uninstall();
