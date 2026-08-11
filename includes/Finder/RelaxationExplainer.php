<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

/**
 * Turns MatchEngine's relaxedAttributes list into the "why this fits" text
 * §5d of PRODUCT-FINDER-PROPOSAL.md calls for — scoped to the relaxation
 * case only (a decision, not an oversight): explaining which soft
 * preferences a product also matched would need MatchEngine to return
 * matched-rule data per product, not just a score, which is a meaningfully
 * bigger change deferred for now.
 *
 * Mirrored by relaxationExplainer.js, verified against the same fixture —
 * see RelaxationExplainerFixtureParityTest.
 */
final class RelaxationExplainer {

	/**
	 * @param string[] $relaxed_attributes
	 * @param array<int, array{attribute: string, shortLabel: string}> $questions
	 */
	public static function explain( array $relaxed_attributes, array $questions ): ?string {
		$labels = self::labels_for( $relaxed_attributes, $questions );

		if ( empty( $labels ) ) {
			return null;
		}

		return sprintf(
			/* translators: %s: the relaxed preference(s), joined into a list, e.g. "Budget" or "Budget and Capacity". */
			_n(
				'We relaxed your %s preference to show you more options.',
				'We relaxed your %s preferences to show you more options.',
				count( $labels ),
				'product-finder'
			),
			self::join_with_and( $labels )
		);
	}

	/**
	 * @param string[] $relaxed_attributes
	 * @param array<int, array{attribute: string, shortLabel: string}> $questions
	 * @return string[]
	 */
	private static function labels_for( array $relaxed_attributes, array $questions ): array {
		$labels = array();
		foreach ( $relaxed_attributes as $attribute ) {
			foreach ( $questions as $question ) {
				if ( $question['attribute'] === $attribute ) {
					$labels[] = $question['shortLabel'];
					break;
				}
			}
		}
		return $labels;
	}

	/**
	 * @param string[] $items
	 */
	private static function join_with_and( array $items ): string {
		if ( count( $items ) === 1 ) {
			return $items[0];
		}

		$last = array_pop( $items );

		return sprintf(
			/* translators: 1: comma-joined list of all but the last item, 2: the last item. */
			__( '%1$s and %2$s', 'product-finder' ),
			implode( ', ', $items ),
			$last
		);
	}
}
