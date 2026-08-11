<?php

declare(strict_types=1);

namespace ProductFinder\Engine;

final class MatchEngine {

	private static function comparators(): array {
		return array(
			'gte'    => static fn( $actual, $value ) => $actual >= $value,
			'lte'    => static fn( $actual, $value ) => $actual <= $value,
			'equals' => static fn( $actual, $value ) => $actual === $value,
		);
	}

	public static function match( array $products, array $rules, array $options = array() ): array {
		$limit            = $options['limit'] ?? 3;
		$relaxation_order = $options['relaxationOrder'] ?? array();

		$hard_rules = array_values(
			array_filter( $rules, static fn( $rule ) => $rule['type'] === 'hard' )
		);
		$soft_rules = array_values(
			array_filter( $rules, static fn( $rule ) => $rule['type'] === 'soft' )
		);

		[ $survivors, $relaxed_attributes ] = self::survivors_with_relaxation(
			$products,
			$hard_rules,
			$relaxation_order
		);

		$scored = array();
		foreach ( $survivors as $index => $product ) {
			$scored[] = array(
				'index'   => $index, // Preserves original order as the tie-break, independent of usort's stability guarantees.
				'product' => $product,
				'score'   => self::score( $product, $soft_rules ),
			);
		}

		$tiebreaker = $options['tiebreaker'] ?? null;

		usort(
			$scored,
			static function ( $a, $b ) use ( $tiebreaker ) {
				return $b['score'] <=> $a['score']
					?: self::compare_by_tiebreaker( $a, $b, $tiebreaker )
					?: $a['index'] <=> $b['index'];
			}
		);

		$ranked = array_slice( $scored, 0, $limit );

		return array(
			'products'         => array_map(
				static fn( $entry ) => array(
					'product' => $entry['product'],
					'score'   => $entry['score'],
				),
				$ranked
			),
			'relaxedAttributes' => $relaxed_attributes,
		);
	}

	/**
	 * Filters products against the hard rules, relaxing one attribute at a time
	 * (in the merchant-defined order) if nothing survives, until either something
	 * survives or every hard rule has been relaxed.
	 *
	 * @return array{0: array, 1: string[]} Survivors, and which attributes were relaxed to get them.
	 */
	private static function survivors_with_relaxation( array $products, array $hard_rules, array $relaxation_order ): array {
		$active_rules = $hard_rules;
		$relaxed      = array();

		$survivors = array_values(
			array_filter( $products, static fn( $product ) => self::satisfies_all( $product, $active_rules ) )
		);

		foreach ( $relaxation_order as $attribute ) {
			if ( ! empty( $survivors ) ) {
				break;
			}

			$active_rules = array_values(
				array_filter( $active_rules, static fn( $rule ) => $rule['attribute'] !== $attribute )
			);
			$relaxed[]    = $attribute;

			$survivors = array_values(
				array_filter( $products, static fn( $product ) => self::satisfies_all( $product, $active_rules ) )
			);
		}

		return array( $survivors, $relaxed );
	}

	private static function compare_by_tiebreaker( array $a, array $b, ?array $tiebreaker ): int {
		if ( $tiebreaker === null ) {
			return 0;
		}

		$attribute = $tiebreaker['attribute'];
		$comparison = $a['product'][ $attribute ] <=> $b['product'][ $attribute ];

		return $tiebreaker['direction'] === 'desc' ? -$comparison : $comparison;
	}

	private static function score( array $product, array $soft_rules ): int {
		$score = 0;
		foreach ( $soft_rules as $rule ) {
			if ( self::satisfies( $product, $rule ) ) {
				$score += $rule['weight'];
			}
		}
		return $score;
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
		$actual = $product[ $rule['attribute'] ] ?? null;
		// Missing data can't satisfy any comparator — without this guard, a
		// product missing the compared attribute would silently pass an lte
		// filter as if its value were 0 (null <= a non-negative threshold is
		// true), while incorrectly *failing* the equivalent gte filter only
		// by accident of comparison semantics, not by design.
		if ( $actual === null ) {
			return false;
		}
		$comparator = self::comparators()[ $rule['comparator'] ];
		return $comparator( $actual, $rule['value'] );
	}
}
