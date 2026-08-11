<?php

declare(strict_types=1);

namespace ProductFinder\Finder;

/**
 * Resolves the effective question set for a category (the per-category
 * question editor epic — see PRODUCT-FINDER-PROPOSAL.md §13, "question
 * customization is narrower than §6 describes") and derives the hard-filter
 * relaxation order from it.
 *
 * Relaxation order is no longer a separately-declared value (contrast
 * TentsTemplate's now-removed relaxation_order()): it's the hard-type
 * questions' own display order, top to bottom. This is what lets a
 * merchant's arbitrary saved question set relax sensibly without a second,
 * independently-maintained "which order to relax in" control — but it also
 * means display order and relaxation order can no longer disagree, which
 * TentsTemplate::questions() previously did (capacity displayed before
 * price, but price relaxed first) — its declaration order was corrected to
 * match when this was introduced.
 *
 * Pure and WordPress-free by design, same reasoning as AttributeMapResolver:
 * the WP-touching parts (reading the template, reading saved overrides from
 * wp_options) stay in the caller (render.php, FinderService).
 */
final class QuestionSetResolver {

	/**
	 * @param array<int, array{key: string, attribute: string, ruleType: string}> $template_questions
	 * @param array<int, array{key: string, attribute: string, ruleType: string}> $saved_questions
	 * @return array{questions: array, relaxationOrder: string[]}
	 */
	public static function resolve( array $template_questions, array $saved_questions ): array {
		$questions = empty( $saved_questions ) ? $template_questions : $saved_questions;

		$relaxation_order = array();
		foreach ( $questions as $question ) {
			if ( 'hard' === $question['ruleType'] ) {
				$relaxation_order[] = $question['attribute'];
			}
		}

		return array(
			'questions'       => $questions,
			'relaxationOrder' => $relaxation_order,
		);
	}
}
