<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ProductFinder\Engine\MatchEngine;
use ProductFinder\Query\ProductArrayAdapter;
use ProductFinder\Query\ProductQuery;
use ProductFinder\Templates\TentsTemplate;

/**
 * Ties the WooCommerce data layer to the WordPress-free MatchEngine: fetches
 * every published product in a category, adapts each to the engine's plain
 * array shape, and returns the match result. The attribute map used is the
 * starter template's defaults with any merchant-saved overrides merged in
 * (build order step 7 / §5c) — see AttributeMapResolver and ConfigRepository.
 * Each candidate's "specs" list is likewise built from whichever question
 * set is effective for the category — the starter template's, or a
 * merchant's saved custom one (§13) — see QuestionSetResolver.
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
		$products      = ProductQuery::for_category( $category_slug, -1 );
		$attribute_map = AttributeMapResolver::resolve(
			TentsTemplate::attribute_map(),
			ConfigRepository::get_attribute_map( $category_slug )
		);
		$questions     = QuestionSetResolver::resolve(
			TentsTemplate::questions(),
			ConfigRepository::get_questions( $category_slug )
		)['questions'];

		return array_map(
			static function ( $product ) use ( $attribute_map, $questions ) {
				$adapted          = ProductArrayAdapter::to_array( $product, $attribute_map );
				$adapted['specs'] = TentsTemplate::format_specs( $adapted, $questions );
				return $adapted;
			},
			$products
		);
	}

	public static function get_results( string $category_slug, array $rules, array $options = array() ): array {
		return MatchEngine::match( self::get_candidates( $category_slug ), $rules, $options );
	}
}
