<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Finder;

use PHPUnit\Framework\TestCase;
use ProductFinder\Finder\QuestionSetResolver;

final class QuestionSetResolverTest extends TestCase {

	private const TEMPLATE_QUESTIONS = array(
		array(
			'key'       => 'price',
			'attribute' => 'price',
			'ruleType'  => 'hard',
		),
		array(
			'key'       => 'capacity',
			'attribute' => 'capacity',
			'ruleType'  => 'hard',
		),
		array(
			'key'       => 'use_type',
			'attribute' => 'use_type',
			'ruleType'  => 'soft',
		),
	);

	public function test_no_saved_questions_returns_the_template_questions_unchanged(): void {
		$result = QuestionSetResolver::resolve( self::TEMPLATE_QUESTIONS, array() );

		$this->assertSame( self::TEMPLATE_QUESTIONS, $result['questions'] );
	}

	public function test_saved_questions_entirely_replace_the_template_ones(): void {
		$saved = array(
			array(
				'key'       => 'season_rating',
				'attribute' => 'season_rating',
				'ruleType'  => 'soft',
			),
		);

		$result = QuestionSetResolver::resolve( self::TEMPLATE_QUESTIONS, $saved );

		$this->assertSame( $saved, $result['questions'] );
	}

	public function test_relaxation_order_is_derived_from_the_hard_questions_in_display_order(): void {
		$result = QuestionSetResolver::resolve( self::TEMPLATE_QUESTIONS, array() );

		// price appears before capacity in TEMPLATE_QUESTIONS, so it relaxes
		// first — not alphabetical, not declaration-independent, purely
		// "top to bottom, hard ones only".
		$this->assertSame( array( 'price', 'capacity' ), $result['relaxationOrder'] );
	}

	public function test_soft_questions_are_excluded_from_the_relaxation_order(): void {
		$result = QuestionSetResolver::resolve( self::TEMPLATE_QUESTIONS, array() );

		$this->assertNotContains( 'use_type', $result['relaxationOrder'] );
	}

	public function test_relaxation_order_is_derived_from_saved_questions_when_present(): void {
		$saved = array(
			array(
				'key'       => 'season_rating',
				'attribute' => 'season_rating',
				'ruleType'  => 'hard',
			),
			array(
				'key'       => 'capacity',
				'attribute' => 'capacity',
				'ruleType'  => 'hard',
			),
		);

		$result = QuestionSetResolver::resolve( self::TEMPLATE_QUESTIONS, $saved );

		// The reverse of TEMPLATE_QUESTIONS' own [price, capacity] order —
		// confirms this comes from the *saved* set, not a leftover default.
		$this->assertSame( array( 'season_rating', 'capacity' ), $result['relaxationOrder'] );
	}

	public function test_no_hard_questions_produces_an_empty_relaxation_order_not_an_error(): void {
		$all_soft = array(
			array(
				'key'       => 'use_type',
				'attribute' => 'use_type',
				'ruleType'  => 'soft',
			),
		);

		$result = QuestionSetResolver::resolve( self::TEMPLATE_QUESTIONS, $all_soft );

		$this->assertSame( array(), $result['relaxationOrder'] );
	}
}
