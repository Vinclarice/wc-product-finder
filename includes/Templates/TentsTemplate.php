<?php

declare(strict_types=1);

namespace ProductFinder\Templates;

/**
 * The "Outdoor Gear Finder" starter template's pre-defined attribute
 * taxonomy (§5c/§6 of PRODUCT-FINDER-PROPOSAL.md) — merchants fill in
 * values for these WooCommerce attributes rather than inventing their own
 * structure. Becomes configurable per-merchant once the attribute-mapping
 * admin screen ships (build order step 7); hardcoded here until then.
 */
final class TentsTemplate {

	public static function attribute_map(): array {
		return array(
			'capacity'      => array(
				'slug' => 'capacity',
				'type' => 'int',
			),
			'packed_weight' => array(
				'slug' => 'packed-weight',
				'type' => 'float',
			),
			'season_rating' => array(
				'slug' => 'season-rating',
				'type' => 'int',
			),
			'use_type'      => array(
				'slug' => 'use-type',
				'type' => 'string',
			),
		);
	}

	/**
	 * The starter template's questions (§4 "Product experience" and the §5d
	 * example table of PRODUCT-FINDER-PROPOSAL.md). All five are wired up
	 * client-side (build order step 6), added one at a time following the
	 * pattern "capacity" proved out first.
	 *
	 * Hard/soft and comparator per question match §5d's example table
	 * directly: capacity and price are "Required" (hard); use type and
	 * season are "Prefer"/"Strongly prefer" (soft); packed weight is the
	 * one §5d itself left ambiguous ("Filter or strongly prefer") — modeled
	 * here as a toggle that adds a soft preference at a fixed threshold
	 * when switched on, rather than taking a raw answer value.
	 */
	public static function questions(): array {
		return array(
			array(
				'key'        => 'capacity',
				'label'      => __( 'How many people will sleep in it?', 'product-finder' ),
				'attribute'  => 'capacity',
				'ruleType'   => 'hard',
				'comparator' => 'gte',
				'input'      => array(
					'type'    => 'select',
					'options' => self::scalar_options( array( 1, 2, 3, 4, 5, 6 ) ),
				),
			),
			array(
				'key'        => 'use_type',
				'label'      => __( 'Car camping, hiking, or backpacking?', 'product-finder' ),
				'attribute'  => 'use_type',
				'ruleType'   => 'soft',
				'comparator' => 'equals',
				'weight'     => 3,
				'input'      => array(
					'type'    => 'select',
					'options' => self::scalar_options( array( 'Backpacking', 'Car Camping', 'Both' ) ),
				),
			),
			array(
				'key'        => 'season_rating',
				'label'      => __( 'Three-season or winter use?', 'product-finder' ),
				'attribute'  => 'season_rating',
				'ruleType'   => 'soft',
				'comparator' => 'equals',
				'weight'     => 2,
				'input'      => array(
					'type'    => 'select',
					'options' => array(
						array(
							'value' => 2,
							'label' => __( '2-season', 'product-finder' ),
						),
						array(
							'value' => 3,
							'label' => __( '3-season', 'product-finder' ),
						),
						array(
							'value' => 4,
							'label' => __( 'Winter (4-season)', 'product-finder' ),
						),
					),
				),
			),
			array(
				'key'        => 'price',
				'label'      => __( "What's your budget?", 'product-finder' ),
				'attribute'  => 'price',
				'ruleType'   => 'hard',
				'comparator' => 'lte',
				'input'      => array(
					'type'    => 'select',
					'options' => self::scalar_options( array( 200, 300, 400, 500, 600 ) ),
				),
			),
			array(
				'key'        => 'packed_weight',
				'label'      => __( 'Is packed weight important?', 'product-finder' ),
				'attribute'  => 'packed_weight',
				'ruleType'   => 'soft',
				'comparator' => 'lte',
				'weight'     => 2,
				'input'      => array(
					'type'  => 'toggle',
					// The fixed threshold used when the toggle is on (§5d's "under 5 lb").
					'value' => 5,
				),
			),
		);
	}

	/**
	 * Order in which hard filters are relaxed when nothing matches (§5d) —
	 * price before capacity, matching the proposal's own "relax budget
	 * before relaxing capacity" example.
	 */
	public static function relaxation_order(): array {
		return array( 'price', 'capacity' );
	}

	/**
	 * @param array<int, int|string> $values
	 * @return array<int, array{value: int|string, label: string}>
	 */
	private static function scalar_options( array $values ): array {
		return array_map(
			static fn( $value ) => array(
				'value' => $value,
				'label' => (string) $value,
			),
			$values
		);
	}
}
