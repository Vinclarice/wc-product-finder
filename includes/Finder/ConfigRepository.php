<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

/**
 * Persists merchant-configured attribute-slug overrides (build order step 7
 * / §5c), keyed by category slug so each of the multiple finder instances
 * (§5b) has its own mapping. One wp_options row for the whole plugin rather
 * than a custom post type or term meta — this is small, plugin-owned
 * settings data, not content.
 *
 * Accepted limitation: save_attribute_map() is an unlocked get_option ->
 * mutate -> update_option, the same race shape as EventCounter::increment().
 * Two concurrent saves (two browser tabs, rapid saves across categories)
 * built from stale snapshots could lose one save's changes. Lower stakes
 * than EventCounter since this is an authenticated manage_woocommerce admin
 * action, not anonymous shopper traffic — not worth locking for now, but a
 * real gap if this screen ever sees frequent concurrent editors.
 */
final class ConfigRepository {

	private const OPTION_NAME = 'product_finder_configs';

	public static function get_attribute_map( string $category_slug ): array {
		$configs = get_option( self::OPTION_NAME, array() );

		return $configs[ $category_slug ]['attributeMap'] ?? array();
	}

	public static function save_attribute_map( string $category_slug, array $attribute_map ): void {
		$configs = get_option( self::OPTION_NAME, array() );

		$configs[ $category_slug ]['attributeMap'] = $attribute_map;

		update_option( self::OPTION_NAME, $configs );
	}
}
