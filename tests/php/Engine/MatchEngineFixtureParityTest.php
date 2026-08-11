<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Engine;

use PHPUnit\Framework\TestCase;
use ProductFinder\Engine\MatchEngine;

/**
 * Runs MatchEngine against the same fixture cases the JS port (matchEngine.js)
 * is verified against in tests/js/matchEngine.fixtures.test.js. Both engines
 * reading identical inputs and producing identical outputs is how the plan's
 * shared-fixture strategy (§9 of PRODUCT-FINDER-PROPOSAL.md) catches the two
 * implementations drifting apart in CI rather than a merchant finding it.
 *
 * This supplements MatchEngineTest.php rather than replacing it — that file's
 * hand-written cases carry per-case documentation this data-driven test can't.
 */
final class MatchEngineFixtureParityTest extends TestCase {

	/**
	 * @dataProvider fixture_cases
	 */
	public function test_matches_the_shared_fixture( array $case ): void {
		$result = MatchEngine::match( $case['products'], $case['rules'], $case['options'] );

		$this->assertEquals( $case['expected'], $result, $case['name'] );
	}

	public function fixture_cases(): array {
		$path  = __DIR__ . '/../../fixtures/match-engine-cases.json';
		$cases = json_decode( file_get_contents( $path ), true );

		$named = array();
		foreach ( $cases as $case ) {
			$named[ $case['name'] ] = array( $case );
		}
		return $named;
	}
}
