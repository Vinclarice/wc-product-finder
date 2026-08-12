<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
 * One wp_options row keyed by category, same pattern as ConfigRepository —
 * but deliberately NOT autoloaded, unlike ConfigRepository's row. The
 * distinction is read pattern, not size: ConfigRepository is read on every
 * front-end render (render.php resolves the category's question set), so
 * autoloading it saves a query on the requests that matter. These counters
 * are only ever read back on the admin settings screen, while increment()
 * below writes them on every render — autoloading them would put a row on
 * every request site-wide, including admin and AJAX requests that have no
 * finder block on them at all, to serve a read that happens once per admin
 * page view.
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

		// Explicit false, not the default: this both creates the option
		// unautoloaded and flips an already-autoloaded row from an earlier
		// version on its next write, so existing installs self-heal without
		// an upgrade routine.
		update_option( self::OPTION_NAME, $counts, false );
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

	/**
	 * Called from the plugin root's uninstall.php when a merchant deletes
	 * the plugin — removes the aggregate view/zero_match counts for every
	 * category, since they all live in this one option.
	 */
	public static function uninstall(): void {
		delete_option( self::OPTION_NAME );
	}
}
