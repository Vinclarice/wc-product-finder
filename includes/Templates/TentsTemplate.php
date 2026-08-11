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
}
