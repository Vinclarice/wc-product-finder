<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Engine;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use ProductFinder\Engine\MatchEngine;

/**
 * Property-based tests for MatchEngine, alongside the existing example-based
 * MatchEngineTest.php. Where the example tests pin specific worked cases,
 * these check invariants that must hold for *any* input — the kind of edge
 * case a hand-picked fixture is unlikely to stumble into by accident.
 *
 * Uses eris (see composer.json), and deliberately checks properties simpler
 * than MatchEngine's own algorithm rather than reimplementing it as the
 * "checker" — that's the well-known PBT trap that makes a property test just
 * a second copy of the bug.
 *
 * eris is pinned to 0.14.x, not the current 1.x line: 1.x initializes itself
 * via #[Before]/#[BeforeClass] PHP attributes, which are a PHPUnit 10+
 * feature — this project is on PHPUnit ^9.6 (see composer.json), which
 * silently never calls them, leaving eris's random source uninitialized
 * ("Call to a member function seed() on null" from inside forAll()). 0.14.x
 * uses the older @before/@beforeClass docblock hooks PHPUnit 9 does support.
 * Confirmed by grepping PHPUnit 9's own source: no Attributes\Before usage
 * anywhere in it.
 */
final class MatchEnginePropertyTest extends TestCase {

	use TestTrait;

	public function test_never_returns_more_products_than_the_limit(): void {
		$this->forAll(
			self::products_generator(),
			self::hard_rules_generator(),
			Generators::choose( 1, 5 )
		)->then( function ( array $products, array $hard_rules, int $limit ) {
			$result = MatchEngine::match(
				$products,
				$hard_rules,
				array(
					'limit'           => $limit,
					'relaxationOrder' => self::attributes_of( $hard_rules ),
				)
			);

			$this->assertLessThanOrEqual( $limit, count( $result['products'] ) );
		} );
	}

	public function test_every_returned_product_satisfies_every_unrelaxed_hard_rule(): void {
		$this->forAll(
			self::products_generator(),
			self::hard_rules_generator()
		)->then( function ( array $products, array $hard_rules ) {
			$result = MatchEngine::match(
				$products,
				$hard_rules,
				array(
					'limit'           => 10,
					'relaxationOrder' => self::attributes_of( $hard_rules ),
				)
			);

			$active_rules = array_values(
				array_filter(
					$hard_rules,
					static fn( $rule ) => ! in_array( $rule['attribute'], $result['relaxedAttributes'], true )
				)
			);

			foreach ( $result['products'] as $entry ) {
				foreach ( $active_rules as $rule ) {
					$this->assertTrue(
						self::satisfies( $entry['product'][ $rule['attribute'] ], $rule ),
						"Product {$entry['product']['id']} violates unrelaxed rule on {$rule['attribute']}"
					);
				}
			}
		} );
	}

	public function test_relaxation_only_happens_when_the_full_hard_rule_set_matches_nothing(): void {
		$this->forAll(
			self::products_generator(),
			self::hard_rules_generator()
		)->then( function ( array $products, array $hard_rules ) {
			$anything_matches_every_hard_rule = false;
			foreach ( $products as $product ) {
				if ( self::satisfies_all( $product, $hard_rules ) ) {
					$anything_matches_every_hard_rule = true;
					break;
				}
			}

			$result = MatchEngine::match(
				$products,
				$hard_rules,
				array(
					'limit'           => 10,
					'relaxationOrder' => self::attributes_of( $hard_rules ),
				)
			);

			if ( $anything_matches_every_hard_rule ) {
				$this->assertSame( array(), $result['relaxedAttributes'] );
			}
		} );
	}

	public function test_relaxed_attributes_are_always_drawn_from_the_hard_rules_given(): void {
		$this->forAll(
			self::products_generator(),
			self::hard_rules_generator()
		)->then( function ( array $products, array $hard_rules ) {
			$result = MatchEngine::match(
				$products,
				$hard_rules,
				array(
					'limit'           => 10,
					'relaxationOrder' => self::attributes_of( $hard_rules ),
				)
			);

			$known_attributes = self::attributes_of( $hard_rules );
			foreach ( $result['relaxedAttributes'] as $attribute ) {
				$this->assertContains( $attribute, $known_attributes );
			}
		} );
	}

	private static function products_generator() {
		// Kept small deliberately: eris's shrinker does a cartesian product
		// across every tuple/vector element when minimizing a failure (see
		// TupleGenerator::optionsFromTheseGenerators's own TODO about this),
		// so a wider vector here blows up memory on a genuine failure
		// instead of reporting one cleanly. Confirmed empirically — 6
		// products combined with the rules/limit tuple reliably exhausted
		// 512MB during shrinking; 3 does not.
		return Generators::vector(
			3,
			Generators::associative(
				array(
					'id'       => Generators::pos(),
					'capacity' => Generators::choose( 1, 10 ),
					'price'    => Generators::choose( 50, 1000 ),
				)
			)
		);
	}

	private static function hard_rules_generator() {
		return Generators::vector(
			2,
			Generators::oneOf(
				Generators::map(
					static fn( $value ) => array(
						'attribute'  => 'capacity',
						'type'       => 'hard',
						'comparator' => 'gte',
						'value'      => $value,
					),
					Generators::choose( 1, 10 )
				),
				Generators::map(
					static fn( $value ) => array(
						'attribute'  => 'price',
						'type'       => 'hard',
						'comparator' => 'lte',
						'value'      => $value,
					),
					Generators::choose( 50, 1000 )
				)
			)
		);
	}

	private static function attributes_of( array $rules ): array {
		return array_values( array_unique( array_column( $rules, 'attribute' ) ) );
	}

	private static function satisfies_all( array $product, array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( ! self::satisfies( $product[ $rule['attribute'] ], $rule ) ) {
				return false;
			}
		}
		return true;
	}

	private static function satisfies( $actual, array $rule ): bool {
		switch ( $rule['comparator'] ) {
			case 'gte':
				return $actual >= $rule['value'];
			case 'lte':
				return $actual <= $rule['value'];
			default:
				return $actual === $rule['value'];
		}
	}
}
