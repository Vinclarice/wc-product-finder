<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Finder;

use ProductFinder\Finder\RelaxationExplainer;
use WP_UnitTestCase;

/**
 * Runs RelaxationExplainer against the same fixture cases the JS port
 * (relaxationExplainer.js) is verified against in
 * src/product-finder/relaxationExplainer.fixtures.test.js — the same
 * shared-fixture drift safety net used for MatchEngine (§9 of
 * PRODUCT-FINDER-PROPOSAL.md).
 *
 * Lives under tests/integration/ rather than the plain tests/php/ tier
 * because explain() uses WordPress's __()/_n() for translatable output —
 * unlike MatchEngine/RuleBuilder, this class produces shopper-facing text.
 */
final class RelaxationExplainerFixtureParityTest extends WP_UnitTestCase {

	/**
	 * @dataProvider fixture_cases
	 */
	public function test_matches_the_shared_fixture( array $case ): void {
		$result = RelaxationExplainer::explain( $case['relaxedAttributes'], $case['questions'] );

		$this->assertSame( $case['expected'], $result, $case['name'] );
	}

	public function fixture_cases(): array {
		$path  = __DIR__ . '/../../fixtures/relaxation-explainer-cases.json';
		$cases = json_decode( file_get_contents( $path ), true );

		$named = array();
		foreach ( $cases as $case ) {
			$named[ $case['name'] ] = array( $case );
		}
		return $named;
	}
}
