<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

/**
 * Merges a merchant's saved attribute-slug overrides onto the starter
 * template's type-annotated defaults (build order step 7 / §5c). The
 * merchant only ever overrides *which* WooCommerce attribute a finder
 * attribute maps to — the finder attribute's type (int/float/string) stays
 * template-defined, since that's structure, not a per-store choice.
 *
 * Pure and WordPress-free by design: the WP-touching parts (reading the
 * template, reading saved overrides from wp_options) stay in FinderService.
 */
final class AttributeMapResolver {

	/**
	 * @param array<string, array{slug: string, type: string}> $template_map Finder attribute => WC slug + type.
	 * @param array<string, string>                             $overrides    Finder attribute => merchant-chosen WC slug.
	 */
	public static function resolve( array $template_map, array $overrides ): array {
		foreach ( $overrides as $finder_attribute => $wc_slug ) {
			if ( isset( $template_map[ $finder_attribute ] ) ) {
				$template_map[ $finder_attribute ]['slug'] = $wc_slug;
			}
		}

		return $template_map;
	}
}
