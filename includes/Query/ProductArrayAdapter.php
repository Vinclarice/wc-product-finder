<?php

declare(strict_types=1);

namespace ProductFinder\Query;

use WC_Product;

/**
 * Converts a WC_Product into the plain array shape ProductFinder\Engine\MatchEngine
 * consumes. WooCommerce local (non-taxonomy) product attributes are always stored
 * as strings keyed by a sanitized slug (e.g. "Use Type" -> "use-type"), so this is
 * also where that slug is mapped to the finder's own attribute name and the value
 * is cast to the type the engine's comparators expect.
 */
final class ProductArrayAdapter {

	/**
	 * @param array<string, array{slug: string, type: string}> $attribute_map Finder attribute name => WC attribute slug + value type.
	 */
	public static function to_array( WC_Product $product, array $attribute_map ): array {
		$result = array(
			'id'        => $product->get_id(),
			'price'     => (float) $product->get_price(),
			'_product'  => $product,
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
