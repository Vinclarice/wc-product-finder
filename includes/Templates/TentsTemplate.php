<?php

declare(strict_types=1);

namespace ProductFinder\Templates;

/**
 * The "Outdoor Gear Finder" starter template's pre-defined attribute
 * taxonomy (§5c/§6 of PRODUCT-FINDER-PROPOSAL.md) — merchants fill in
 * values for these WooCommerce attributes rather than inventing their own
 * structure. These are the *defaults*; a merchant's saved overrides (via
 * the attribute-mapping admin screen, build order step 7) are merged on top
 * by ProductFinder\Finder\AttributeMapResolver, read from
 * ProductFinder\Finder\ConfigRepository — see ProductFinder\Finder\FinderService.
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
				'shortLabel' => __( 'Capacity', 'product-finder' ),
				'attribute'  => 'capacity',
				'ruleType'   => 'hard',
				'comparator' => 'gte',
				'valueType'  => 'int', // Matches attribute_map()'s type for 'capacity'.
				'input'      => array(
					'type'    => 'select',
					'options' => self::scalar_options( array( 1, 2, 3, 4, 5, 6 ) ),
				),
			),
			array(
				'key'        => 'use_type',
				'label'      => __( 'Car camping, hiking, or backpacking?', 'product-finder' ),
				'shortLabel' => __( 'Use type', 'product-finder' ),
				'attribute'  => 'use_type',
				'ruleType'   => 'soft',
				'comparator' => 'equals',
				'valueType'  => 'string', // Matches attribute_map()'s type for 'use_type'.
				'weight'     => 3,
				'input'      => array(
					'type'    => 'select',
					'options' => self::scalar_options( array( 'Backpacking', 'Car Camping', 'Both' ) ),
				),
			),
			array(
				'key'        => 'season_rating',
				'label'      => __( 'Three-season or winter use?', 'product-finder' ),
				'shortLabel' => __( 'Season rating', 'product-finder' ),
				'attribute'  => 'season_rating',
				'ruleType'   => 'soft',
				'comparator' => 'equals',
				// Matches attribute_map()'s type for 'season_rating'. This is
				// the piece that was missing before the fix: an 'equals'
				// comparator alone doesn't tell you whether to cast the rule
				// value as a number or a string, and getting it wrong here
				// (defaulting to string) meant this rule could never match.
				'valueType'  => 'int',
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
				'shortLabel' => __( 'Budget', 'product-finder' ),
				'attribute'  => 'price',
				'ruleType'   => 'hard',
				'comparator' => 'lte',
				'valueType'  => 'float', // Matches ProductArrayAdapter's (float) cast of the product's native price.
				'input'      => array(
					'type'    => 'select',
					'options' => self::scalar_options( array( 200, 300, 400, 500, 600 ) ),
				),
			),
			array(
				'key'        => 'packed_weight',
				'label'      => __( 'Is packed weight important?', 'product-finder' ),
				'shortLabel' => __( 'Packed weight', 'product-finder' ),
				'attribute'  => 'packed_weight',
				'ruleType'   => 'soft',
				'comparator' => 'lte',
				// Not actually consulted for a toggle (its rule value always
				// comes from input.value below), included for consistency.
				'valueType'  => 'float',
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
	 * "Key specs" for a result card (§4 of PRODUCT-FINDER-PROPOSAL.md) — every
	 * finder attribute except price, which the card already shows separately
	 * as its own price/priceLabel. Skips attributes missing from the product
	 * rather than showing an empty value.
	 *
	 * @param array<string, mixed> $product An adapted product array (see ProductArrayAdapter::to_array()).
	 * @return array<int, array{label: string, value: string}>
	 */
	public static function format_specs( array $product ): array {
		$specs = array();

		foreach ( self::questions() as $question ) {
			if ( 'price' === $question['attribute'] ) {
				continue;
			}

			$value = $product[ $question['attribute'] ] ?? null;
			if ( null === $value ) {
				continue;
			}

			$specs[] = array(
				'label' => $question['shortLabel'],
				'value' => self::format_spec_value( $question['attribute'], $value ),
			);
		}

		return $specs;
	}

	/**
	 * @param mixed $value
	 */
	private static function format_spec_value( string $attribute, $value ): string {
		switch ( $attribute ) {
			case 'capacity':
				return $value . ' ' . __( 'people', 'product-finder' );
			case 'packed_weight':
				return $value . ' ' . __( 'lb', 'product-finder' );
			case 'use_type':
				return ucfirst( (string) $value );
			default:
				return (string) $value;
		}
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
