<?php

declare(strict_types=1);

namespace ProductFinder\Query;

use WC_Product;

/**
 * Resolves a WC_Product's real value for a given WooCommerce attribute
 * slug, handling both attribute kinds a merchant can map a finder attribute
 * to: local/custom attributes, stored as raw strings, and global/taxonomy
 * attributes, whose values are term IDs resolved here to the term's name so
 * both kinds produce a comparable value.
 *
 * Extracted from ProductArrayAdapter (which still uses it, for the same
 * per-product-attribute resolution it always did) so
 * ProductFinder\Attributes\AttributeDiscovery's value auto-discovery (§13's
 * per-category question editor, Phase 2) can resolve values the same way,
 * without duplicating this logic.
 */
final class AttributeValueResolver {

	public static function resolve( WC_Product $product, string $slug ): ?string {
		$attribute = $product->get_attributes()[ $slug ] ?? null;
		if ( $attribute === null ) {
			return null;
		}

		if ( $attribute->is_taxonomy() ) {
			$term_id = $attribute->get_options()[0] ?? null;
			if ( $term_id === null ) {
				return null;
			}
			$term = get_term( (int) $term_id, $attribute->get_taxonomy() );
			return ( $term && ! is_wp_error( $term ) ) ? $term->name : null;
		}

		$options = $attribute->get_options();
		return $options[0] ?? null;
	}
}
