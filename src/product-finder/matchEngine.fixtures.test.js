/**
 * Runs the JS port of MatchEngine against the same fixture cases the PHP
 * engine is verified against in tests/php/Engine/MatchEngineFixtureParityTest.php.
 * See that file's docblock for why this exists — this is the cross-language
 * drift safety net from §9 of PRODUCT-FINDER-PROPOSAL.md, not a replacement
 * for matchEngine.test.js's own hand-written cases.
 */
import { match } from './matchEngine';

import fixtureCases from '../../tests/fixtures/match-engine-cases.json';

describe( 'matchEngine fixture parity', () => {
	test.each( fixtureCases.map( ( c ) => [ c.name, c ] ) )(
		'%s',
		( name, testCase ) => {
			const result = match(
				testCase.products,
				testCase.rules,
				testCase.options
			);

			expect( result ).toEqual( testCase.expected );
		}
	);
} );
