<?php

declare(strict_types=1);

namespace ProductFinder\Engine;

final class MatchEngine {

	private static function comparators(): array {
		return array(
			'gte' => static fn( $actual, $value ) => $actual >= $value,
		);
	}

	public static function match( array $products, array $rules, array $options = array() ): array {
		$hard_rules = array_values(
			array_filter( $rules, static fn( $rule ) => $rule['type'] === 'hard' )
		);

		$survivors = array_values(
			array_filter(
				$products,
				static fn( $product ) => self::satisfies_all( $product, $hard_rules )
			)
		);

		return array(
			'products'         => array_map(
				static fn( $product ) => array(
					'product' => $product,
					'score'   => 0,
				),
				$survivors
			),
			'relaxedAttribute' => null,
		);
	}

	private static function satisfies_all( array $product, array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( ! self::satisfies( $product, $rule ) ) {
				return false;
			}
		}
		return true;
	}

	private static function satisfies( array $product, array $rule ): bool {
		$comparator = self::comparators()[ $rule['comparator'] ];
		return $comparator( $product[ $rule['attribute'] ], $rule['value'] );
	}
}
