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
	 * The starter template's questions (§4 "Product experience" of
	 * PRODUCT-FINDER-PROPOSAL.md). Only "capacity" is wired up client-side so
	 * far (build order step 6) — the remaining four (use type, season,
	 * budget, packed weight) are added the same way once that pattern proves
	 * out, per the plan.
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
					'options' => array( 1, 2, 3, 4, 5, 6 ),
				),
			),
		);
	}

	/**
	 * Order in which hard filters are relaxed when nothing matches (§5d) —
	 * currently only one hard-filter question exists, so this is trivial;
	 * it grows alongside questions().
	 */
	public static function relaxation_order(): array {
		return array( 'capacity' );
	}
}
