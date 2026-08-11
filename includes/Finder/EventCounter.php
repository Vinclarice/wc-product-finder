<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

/**
 * Basic local aggregate event counts (build order step 9 / §8 MVP scope) —
 * "view" and "zero_match", counted only at render time in render.php.
 *
 * Deliberately server-side only, not a client-side beacon: the interesting
 * signal (a shopper answering their way into zero results without a page
 * reload) happens entirely in view.js's local state and never touches the
 * server by design (§7). Capturing that would mean this plugin's first-ever
 * per-interaction network call, plus real transition-detection logic to
 * avoid over-counting a shopper stuck re-trying answers. That's exactly the
 * kind of interaction-level tracking §12 reserves for the paid Finder
 * Insights tier — this stays a simple undercount by design, not a bug.
 *
 * One wp_options row keyed by category, same pattern as ConfigRepository.
 * No PII, no per-shopper data — just two integers per category.
 *
 * Accepted limitation: increment() is an unlocked get_option -> mutate ->
 * update_option, so concurrent requests could race and lose an increment.
 * Acceptable for "basic aggregate" counts at this traffic scale — not worth
 * atomic-counter infrastructure for approximate stats.
 */
final class EventCounter {

	private const OPTION_NAME = 'product_finder_event_counts';
	private const EVENTS      = array( 'view', 'zero_match' );

	public static function increment( string $category_slug, string $event ): void {
		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return;
		}

		$counts  = get_option( self::OPTION_NAME, array() );
		$current = $counts[ $category_slug ][ $event ] ?? 0;

		$counts[ $category_slug ][ $event ] = $current + 1;

		update_option( self::OPTION_NAME, $counts );
	}

	public static function get_counts( string $category_slug ): array {
		$counts   = get_option( self::OPTION_NAME, array() );
		$category = $counts[ $category_slug ] ?? array();

		$result = array();
		foreach ( self::EVENTS as $event ) {
			$result[ $event ] = $category[ $event ] ?? 0;
		}
		return $result;
	}
}
