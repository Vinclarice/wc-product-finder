<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Finder;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use ProductFinder\Finder\QuestionSetResolver;

/**
 * Property-based tests for QuestionSetResolver, alongside the existing
 * example-based QuestionSetResolverTest.php.
 *
 * The first property directly guards the exact bug class this class exists
 * to prevent (see its own docblock): relaxation order silently disagreeing
 * with display order. That already happened once with TentsTemplate's
 * hand-maintained ordering — this is the regression net for it happening
 * again with any future question set, not just the one fixture caught.
 */
final class QuestionSetResolverPropertyTest extends TestCase {

	use TestTrait;

	// Deliberately interleaves hard and soft questions rather than grouping
	// them — the order-agreement property is only meaningfully exercised
	// when hard questions aren't already contiguous/first.
	private const QUESTION_POOL = array(
		array( 'key' => 'price', 'attribute' => 'price', 'ruleType' => 'hard' ),
		array( 'key' => 'use_type', 'attribute' => 'use_type', 'ruleType' => 'soft' ),
		array( 'key' => 'capacity', 'attribute' => 'capacity', 'ruleType' => 'hard' ),
		array( 'key' => 'season_rating', 'attribute' => 'season_rating', 'ruleType' => 'soft' ),
		array( 'key' => 'packed_weight', 'attribute' => 'packed_weight', 'ruleType' => 'hard' ),
	);

	public function test_relaxation_order_is_exactly_the_hard_questions_attributes_in_display_order(): void {
		$this->forAll(
			self::questions_generator(),
			self::questions_generator()
		)->then( function ( array $template_questions, array $saved_questions ) {
			$result = QuestionSetResolver::resolve( $template_questions, $saved_questions );

			$effective = empty( $saved_questions ) ? $template_questions : $saved_questions;
			$expected  = array();
			foreach ( $effective as $question ) {
				if ( 'hard' === $question['ruleType'] ) {
					$expected[] = $question['attribute'];
				}
			}

			$this->assertSame( $expected, $result['relaxationOrder'] );
		} );
	}

	public function test_saved_questions_win_over_the_template_whenever_present(): void {
		$this->forAll(
			self::questions_generator(),
			self::questions_generator()
		)->then( function ( array $template_questions, array $saved_questions ) {
			$result = QuestionSetResolver::resolve( $template_questions, $saved_questions );

			$expected = empty( $saved_questions ) ? $template_questions : $saved_questions;
			$this->assertSame( $expected, $result['questions'] );
		} );
	}

	private static function questions_generator() {
		return Generators::subset( self::QUESTION_POOL );
	}
}
