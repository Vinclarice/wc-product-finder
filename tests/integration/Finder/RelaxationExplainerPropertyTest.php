<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Finder;

use Eris\Generators;
use Eris\TestTrait;
use ProductFinder\Finder\RelaxationExplainer;
use WP_UnitTestCase;

/**
 * Property-based tests for RelaxationExplainer, alongside the existing
 * fixture-based RelaxationExplainerFixtureParityTest.php.
 *
 * Lives under tests/integration/, not tests/php/, for the same reason as
 * the fixture parity test: explain() uses WordPress's __()/_n(), which
 * don't exist without a WP bootstrap — see ARCHITECTURE.md's i18n
 * exception to the functional-core boundary rule.
 */
final class RelaxationExplainerPropertyTest extends WP_UnitTestCase {

	use TestTrait;

	private const QUESTIONS = array(
		array( 'attribute' => 'capacity', 'shortLabel' => 'Capacity' ),
		array( 'attribute' => 'price', 'shortLabel' => 'Budget' ),
		array( 'attribute' => 'season_rating', 'shortLabel' => 'Season rating' ),
	);

	public function test_returns_null_exactly_when_nothing_was_relaxed(): void {
		$this->forAll( self::relaxed_attributes_generator() )
			->then( function ( array $relaxed_attributes ) {
				$result = RelaxationExplainer::explain( $relaxed_attributes, self::QUESTIONS );

				if ( empty( $relaxed_attributes ) ) {
					$this->assertNull( $result );
				} else {
					$this->assertNotNull( $result );
				}
			} );
	}

	public function test_message_mentions_every_relaxed_attributes_short_label(): void {
		$this->forAll( self::relaxed_attributes_generator() )
			->then( function ( array $relaxed_attributes ) {
				$result = RelaxationExplainer::explain( $relaxed_attributes, self::QUESTIONS );

				if ( empty( $relaxed_attributes ) ) {
					return;
				}

				foreach ( $relaxed_attributes as $attribute ) {
					$this->assertStringContainsString( self::short_label_for( $attribute ), $result );
				}
			} );
	}

	private static function relaxed_attributes_generator() {
		return Generators::subset( array_column( self::QUESTIONS, 'attribute' ) );
	}

	private static function short_label_for( string $attribute ): string {
		foreach ( self::QUESTIONS as $question ) {
			if ( $question['attribute'] === $attribute ) {
				return $question['shortLabel'];
			}
		}
		throw new \RuntimeException( "no question found for attribute {$attribute}" );
	}
}
