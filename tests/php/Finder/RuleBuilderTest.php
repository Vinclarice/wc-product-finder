<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Finder;

use PHPUnit\Framework\TestCase;
use ProductFinder\Finder\RuleBuilder;

/**
 * Mirrors rules.js's test cases, adjusted for one real structural difference:
 * a JS checkbox answer is a boolean (event.target.checked); an HTML form
 * only *includes* a checked checkbox's key at all, omitting it entirely
 * when unchecked — so "is this toggle on" is key-presence here, not a
 * boolean comparison. That's different enough from rules.js's input shape
 * that this gets its own tests rather than a shared JSON fixture.
 */
final class RuleBuilderTest extends TestCase {

	private const CAPACITY_QUESTION = array(
		'key'        => 'capacity',
		'attribute'  => 'capacity',
		'ruleType'   => 'hard',
		'comparator' => 'gte',
		'input'      => array( 'type' => 'select' ),
	);

	private const USE_TYPE_QUESTION = array(
		'key'        => 'use_type',
		'attribute'  => 'use_type',
		'ruleType'   => 'soft',
		'comparator' => 'equals',
		'weight'     => 3,
		'input'      => array( 'type' => 'select' ),
	);

	private const PACKED_WEIGHT_QUESTION = array(
		'key'        => 'packed_weight',
		'attribute'  => 'packed_weight',
		'ruleType'   => 'soft',
		'comparator' => 'lte',
		'weight'     => 2,
		'input'      => array(
			'type'  => 'toggle',
			'value' => 5,
		),
	);

	public function test_unanswered_questions_produce_no_rules(): void {
		$answers = array();
		$this->assertSame(
			array(),
			RuleBuilder::build( array( self::CAPACITY_QUESTION, self::USE_TYPE_QUESTION ), $answers )
		);
	}

	public function test_a_hard_numeric_question_casts_the_answer_to_a_number(): void {
		$answers = array( 'capacity' => '4' ); // GET params always arrive as strings.
		$this->assertSame(
			array(
				array(
					'attribute'  => 'capacity',
					'type'       => 'hard',
					'comparator' => 'gte',
					'value'      => 4.0,
				),
			),
			RuleBuilder::build( array( self::CAPACITY_QUESTION ), $answers )
		);
	}

	public function test_a_soft_categorical_question_lowercases_the_answer_and_includes_its_weight(): void {
		$answers = array( 'use_type' => 'Backpacking' );
		$this->assertSame(
			array(
				array(
					'attribute'  => 'use_type',
					'type'       => 'soft',
					'comparator' => 'equals',
					'value'      => 'backpacking',
					'weight'     => 3,
				),
			),
			RuleBuilder::build( array( self::USE_TYPE_QUESTION ), $answers )
		);
	}

	public function test_a_toggle_question_is_included_only_when_its_key_is_present(): void {
		$this->assertSame(
			array(),
			RuleBuilder::build( array( self::PACKED_WEIGHT_QUESTION ), array() )
		);
		$this->assertSame(
			array(
				array(
					'attribute'  => 'packed_weight',
					'type'       => 'soft',
					'comparator' => 'lte',
					'value'      => 5,
					'weight'     => 2,
				),
			),
			// An HTML checkbox's submitted value is conventionally "on", but
			// any presence at all is what matters — the fixed threshold
			// comes from the question config, not the submitted value.
			RuleBuilder::build( array( self::PACKED_WEIGHT_QUESTION ), array( 'packed_weight' => 'on' ) )
		);
	}

	public function test_mixed_answers_only_produce_rules_for_the_answered_questions(): void {
		$answers = array(
			'capacity'      => '4',
			'packed_weight' => 'on',
		);
		$this->assertSame(
			array(
				array(
					'attribute'  => 'capacity',
					'type'       => 'hard',
					'comparator' => 'gte',
					'value'      => 4.0,
				),
				array(
					'attribute'  => 'packed_weight',
					'type'       => 'soft',
					'comparator' => 'lte',
					'value'      => 5,
					'weight'     => 2,
				),
			),
			RuleBuilder::build(
				array( self::CAPACITY_QUESTION, self::USE_TYPE_QUESTION, self::PACKED_WEIGHT_QUESTION ),
				$answers
			)
		);
	}
}
