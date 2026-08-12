/**
 * Runs the JS port of RelaxationExplainer against the same fixture cases the
 * PHP port is verified against in
 * tests/integration/Finder/RelaxationExplainerFixtureParityTest.php. See that
 * file's docblock for why this exists.
 */
import { explainRelaxation } from './relaxationExplainer';

import fixtureCases from '../../tests/fixtures/relaxation-explainer-cases.json';

describe( 'relaxationExplainer fixture parity', () => {
	test.each( fixtureCases.map( ( c ) => [ c.name, c ] ) )(
		'%s',
		( name, testCase ) => {
			const result = explainRelaxation(
				testCase.relaxedAttributes,
				testCase.questions
			);

			expect( result ).toEqual( testCase.expected );
		}
	);
} );
