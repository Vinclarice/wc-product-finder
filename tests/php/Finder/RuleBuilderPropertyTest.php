<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Finder;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use ProductFinder\Finder\RuleBuilder;

/**
 * Property-based tests for RuleBuilder, alongside the existing example-based
 * RuleBuilderTest.php. These check structural invariants that hold no matter
 * which subset of a question set gets answered — deliberately not
 * re-deriving should_include()'s own branching as the checker, since that
 * would just be a second copy of the logic under test.
 */
final class RuleBuilderPropertyTest extends TestCase {

	use TestTrait;

	// Deliberately mixes hard/soft and select/toggle in one fixed pool —
	// varying which subset gets "answered" already exercises every branch
	// of should_include() without needing a fully random question generator.
	private const QUESTION_POOL = array(
		array(
			'key'        => 'capacity',
			'attribute'  => 'capacity',
			'ruleType'   => 'hard',
			'comparator' => 'gte',
			'weight'     => 3,
			'valueType'  => 'int',
			'input'      => array( 'type' => 'select', 'options' => array() ),
		),
		array(
			'key'        => 'use_type',
			'attribute'  => 'use_type',
			'ruleType'   => 'soft',
			'comparator' => 'equals',
			'weight'     => 2,
			'valueType'  => 'string',
			'input'      => array( 'type' => 'select', 'options' => array() ),
		),
		array(
			'key'        => 'is_lightweight',
			'attribute'  => 'is_lightweight',
			'ruleType'   => 'soft',
			'comparator' => 'equals',
			'weight'     => 1,
			'valueType'  => 'string',
			'input'      => array( 'type' => 'toggle', 'value' => true ),
		),
	);

	public function test_never_produces_more_rules_than_questions(): void {
		$this->forAll( self::answered_questions_generator() )
			->then( function ( array $pair ) {
				[ $questions, $answers ] = $pair;
				$rules = RuleBuilder::build( $questions, $answers );

				$this->assertLessThanOrEqual( count( $questions ), count( $rules ) );
			} );
	}

	public function test_every_rule_attribute_comes_from_the_input_questions(): void {
		$this->forAll( self::answered_questions_generator() )
			->then( function ( array $pair ) {
				[ $questions, $answers ] = $pair;
				$rules             = RuleBuilder::build( $questions, $answers );
				$known_attributes = array_column( $questions, 'attribute' );

				foreach ( $rules as $rule ) {
					$this->assertContains( $rule['attribute'], $known_attributes );
				}
			} );
	}

	public function test_soft_rules_always_carry_a_weight_and_hard_rules_never_do(): void {
		$this->forAll( self::answered_questions_generator() )
			->then( function ( array $pair ) {
				[ $questions, $answers ] = $pair;
				$rules = RuleBuilder::build( $questions, $answers );

				foreach ( $rules as $rule ) {
					if ( 'soft' === $rule['type'] ) {
						$this->assertArrayHasKey( 'weight', $rule );
					} else {
						$this->assertArrayNotHasKey( 'weight', $rule );
					}
				}
			} );
	}

	/**
	 * Generates [$questions, $answers] where $questions is the full pool and
	 * $answers covers a random subset of it — a placeholder value of the
	 * right shape for the question's own type, since these properties don't
	 * depend on the specific value answered, only on which questions were.
	 */
	private static function answered_questions_generator() {
		return Generators::map(
			static function ( array $answered_subset ) {
				$answers = array();
				foreach ( $answered_subset as $question ) {
					$answers[ $question['key'] ] = 'toggle' === $question['input']['type'] ? true : '5';
				}
				return array( self::QUESTION_POOL, $answers );
			},
			Generators::subset( self::QUESTION_POOL )
		);
	}
}
