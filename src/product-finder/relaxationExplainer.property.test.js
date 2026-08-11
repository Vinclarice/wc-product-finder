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

const relaxedAttributesArb = fc.subarray( QUESTIONS.map( ( q ) => q.attribute ) );

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
				const result = explainRelaxation( relaxedAttributes, QUESTIONS );

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
				const result = explainRelaxation( relaxedAttributes, QUESTIONS );

				if ( relaxedAttributes.length === 0 ) {
					return;
				}

				relaxedAttributes.forEach( ( attribute ) => {
					expect( result ).toContain( shortLabelFor( attribute ) );
				} );
			} )
		);
	} );
} );
