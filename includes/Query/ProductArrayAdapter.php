<?php

declare(strict_types=1);

namespace ProductFinder\Query;

use WC_Product;

/**
 * Converts a WC_Product into the plain, JSON-safe array shape both
 * ProductFinder\Engine\MatchEngine consumes and the Interactivity API embeds
 * as client-side state (§9/§10 of PRODUCT-FINDER-PROPOSAL.md — the client
 * runs its own port of the engine against this same data, so it can't carry
 * the WC_Product object itself). WooCommerce local (non-taxonomy) product
 * attributes are always stored as strings keyed by a sanitized slug (e.g.
 * "Use Type" -> "use-type"), so this is also where that slug is mapped to
 * the finder's own attribute name and the value is cast to the type the
 * engine's comparators expect.
 */
final class ProductArrayAdapter {

	/**
	 * @param array<string, array{slug: string, type: string}> $attribute_map Finder attribute name => WC attribute slug + value type.
	 */
	public static function to_array( WC_Product $product, array $attribute_map ): array {
		$result = array(
			'id'         => $product->get_id(),
			'name'       => $product->get_name(),
			'permalink'  => $product->get_permalink(),
			'price'      => (float) $product->get_price(),
			// Plain text, not wc_price()'s HTML — this gets embedded as JSON state
			// and rendered via a text-only directive on the client.
			'priceLabel' => wp_strip_all_tags( wc_price( $product->get_price() ) ),
		);

		foreach ( $attribute_map as $finder_attribute => $config ) {
			$result[ $finder_attribute ] = self::cast(
				self::raw_attribute_value( $product, $config['slug'] ),
				$config['type']
			);
		}

		return $result;
	}

	private static function raw_attribute_value( WC_Product $product, string $slug ): ?string {
		$attribute = $product->get_attributes()[ $slug ] ?? null;
		if ( $attribute === null ) {
			return null;
		}

		$options = $attribute->get_options();
		return $options[0] ?? null;
	}

	private static function cast( ?string $value, string $type ) {
		if ( $value === null ) {
			return null;
		}

		switch ( $type ) {
			case 'int':
				return (int) $value;
			case 'float':
				return (float) $value;
			default:
				return strtolower( trim( $value ) );
		}
	}
}
