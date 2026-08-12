/* eslint-disable jest/no-conditional-expect -- A property of the form
   "when X holds, Y must hold" is an implication, and expressing it means
   asserting inside the X branch. The rule targets expects hidden in
   try/catch or in branches that may never run in an example-based test;
   here fast-check drives both branches across generated inputs, and the
   PHP mirrors (see *PropertyTest.php) express the same implications. */

/**
 * Property-based tests for relaxationExplainer.js, alongside the existing
 * example-based relaxationExplainer.fixtures.test.js. Mirrors
 * RelaxationExplainerPropertyTest.php's properties and generator shape
 * exactly — unlike the PHP side, no WordPress bootstrap is needed here,
 * since this port is deliberately not translatable (see this file's own
 * docblock for the platform limitation behind that).
 */
import fc from 'fast-check';
import { explainRelaxation } from './relaxationExplainer';

const QUESTIONS = [
	{ attribute: 'capacity', shortLabel: 'Capacity' },
	{ attribute: 'price', shortLabel: 'Budget' },
	{ attribute: 'season_rating', shortLabel: 'Season rating' },
];

const relaxedAttributesArb = fc.subarray(
	QUESTIONS.map( ( q ) => q.attribute )
);

function shortLabelFor( attribute ) {
	const question = QUESTIONS.find( ( q ) => q.attribute === attribute );
	if ( ! question ) {
		throw new Error( `no question found for attribute ${ attribute }` );
	}
	return question.shortLabel;
}

describe( 'explainRelaxation (property-based)', () => {
	it( 'returns null exactly when nothing was relaxed', () => {
		fc.assert(
			fc.property( relaxedAttributesArb, ( relaxedAttributes ) => {
				const result = explainRelaxation(
					relaxedAttributes,
					QUESTIONS
				);

				if ( relaxedAttributes.length === 0 ) {
					expect( result ).toBeNull();
				} else {
					expect( result ).not.toBeNull();
				}
			} )
		);
	} );

	it( "mentions every relaxed attribute's short label", () => {
		fc.assert(
			fc.property( relaxedAttributesArb, ( relaxedAttributes ) => {
				if ( relaxedAttributes.length === 0 ) {
					return;
				}

				const result = explainRelaxation(
					relaxedAttributes,
					QUESTIONS
				);

				relaxedAttributes.forEach( ( attribute ) => {
					expect( result ).toContain( shortLabelFor( attribute ) );
				} );
			} )
		);
	} );
} );
