<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

use ProductFinder\Engine\MatchEngine;
use ProductFinder\Query\ProductArrayAdapter;
use ProductFinder\Query\ProductQuery;
use ProductFinder\Templates\TentsTemplate;

/**
 * Ties the WooCommerce data layer to the WordPress-free MatchEngine: fetches
 * every published product in a category, adapts each to the engine's plain
 * array shape, and returns the match result. The starter template's
 * attribute map (§5c) is hardcoded here for now — it becomes merchant-
 * configurable once the attribute-mapping admin screen ships (build order step 7).
 */
final class FinderService {

	/**
	 * Every published product in the category, adapted to the engine's plain
	 * array shape but not yet run through MatchEngine. This is what gets
	 * embedded as Interactivity API state (build order step 6) so the client
	 * can recompute results locally as the shopper answers questions, without
	 * a request back to the server per answer.
	 */
	public static function get_candidates( string $category_slug ): array {
		$products = ProductQuery::for_category( $category_slug, -1 );

		return array_map(
			static fn( $product ) => ProductArrayAdapter::to_array( $product, TentsTemplate::attribute_map() ),
			$products
		);
	}

	public static function get_results( string $category_slug, array $rules, array $options = array() ): array {
		return MatchEngine::match( self::get_candidates( $category_slug ), $rules, $options );
	}
}
