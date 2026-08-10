<?php

declare(strict_types=1);

namespace ProductFinder\Attributes;

final class AttributeCompleteness {

	public static function calculate( array $products, array $attributes ): array {
		$total = count( $products );

		$result = array();
		foreach ( $attributes as $attribute ) {
			$set = 0;
			foreach ( $products as $product ) {
				if ( self::is_set( $product, $attribute ) ) {
					++$set;
				}
			}

			$result[ $attribute ] = array(
				'attribute'  => $attribute,
				'total'      => $total,
				'set'        => $set,
				'percentage' => $total === 0 ? 0 : (int) round( ( $set / $total ) * 100 ),
			);
		}

		return $result;
	}

	private static function is_set( array $product, string $attribute ): bool {
		return array_key_exists( $attribute, $product )
			&& $product[ $attribute ] !== null
			&& $product[ $attribute ] !== '';
	}
}
