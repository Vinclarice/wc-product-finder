/* eslint-disable jest/no-conditional-expect -- A property of the form
   "when X holds, Y must hold" is an implication, and expressing it means
   asserting inside the X branch. The rule targets expects hidden in
   try/catch or in branches that may never run in an example-based test;
   here fast-check drives both branches across generated inputs, and the
   PHP mirrors (see *PropertyTest.php) express the same implications. */

/**
 * Property-based tests for matchEngine.js, alongside the existing
 * example-based matchEngine.fixtures.test.js. Mirrors
 * MatchEnginePropertyTest.php's properties and generator shape exactly —
 * see that file for the PBT-trap rationale (checkers here are all simpler
 * than match()'s own algorithm, not a reimplementation of it).
 */
import fc from 'fast-check';
import { match } from './matchEngine';

const productArb = fc.record( {
	id: fc.integer( { min: 1, max: 1000 } ),
	capacity: fc.integer( { min: 1, max: 10 } ),
	price: fc.integer( { min: 50, max: 1000 } ),
} );

const productsArb = fc.array( productArb, { minLength: 6, maxLength: 6 } );

const hardRuleArb = fc.oneof(
	fc.integer( { min: 1, max: 10 } ).map( ( value ) => ( {
		attribute: 'capacity',
		type: 'hard',
		comparator: 'gte',
		value,
	} ) ),
	fc.integer( { min: 50, max: 1000 } ).map( ( value ) => ( {
		attribute: 'price',
		type: 'hard',
		comparator: 'lte',
		value,
	} ) )
);

const hardRulesArb = fc.array( hardRuleArb, { minLength: 2, maxLength: 2 } );

function attributesOf( rules ) {
	return [ ...new Set( rules.map( ( rule ) => rule.attribute ) ) ];
}

function satisfies( actual, rule ) {
	switch ( rule.comparator ) {
		case 'gte':
			return actual >= rule.value;
		case 'lte':
			return actual <= rule.value;
		default:
			return actual === rule.value;
	}
}

function satisfiesAll( product, rules ) {
	return rules.every( ( rule ) =>
		satisfies( product[ rule.attribute ], rule )
	);
}

describe( 'match (property-based)', () => {
	it( 'never returns more products than the limit', () => {
		fc.assert(
			fc.property(
				productsArb,
				hardRulesArb,
				fc.integer( { min: 1, max: 5 } ),
				( products, hardRules, limit ) => {
					const result = match( products, hardRules, {
						limit,
						relaxationOrder: attributesOf( hardRules ),
					} );

					expect( result.products.length ).toBeLessThanOrEqual(
						limit
					);
				}
			)
		);
	} );

	it( 'every returned product satisfies every unrelaxed hard rule', () => {
		fc.assert(
			fc.property( productsArb, hardRulesArb, ( products, hardRules ) => {
				const result = match( products, hardRules, {
					limit: 10,
					relaxationOrder: attributesOf( hardRules ),
				} );

				const activeRules = hardRules.filter(
					( rule ) =>
						! result.relaxedAttributes.includes( rule.attribute )
				);

				result.products.forEach( ( entry ) => {
					activeRules.forEach( ( rule ) => {
						expect(
							satisfies( entry.product[ rule.attribute ], rule )
						).toBe( true );
					} );
				} );
			} )
		);
	} );

	it( 'only relaxes when the full hard rule set matches nothing', () => {
		fc.assert(
			fc.property( productsArb, hardRulesArb, ( products, hardRules ) => {
				const anythingMatchesEveryHardRule = products.some(
					( product ) => satisfiesAll( product, hardRules )
				);

				const result = match( products, hardRules, {
					limit: 10,
					relaxationOrder: attributesOf( hardRules ),
				} );

				if ( anythingMatchesEveryHardRule ) {
					expect( result.relaxedAttributes ).toEqual( [] );
				}
			} )
		);
	} );

	it( 'relaxed attributes are always drawn from the hard rules given', () => {
		fc.assert(
			fc.property( productsArb, hardRulesArb, ( products, hardRules ) => {
				const result = match( products, hardRules, {
					limit: 10,
					relaxationOrder: attributesOf( hardRules ),
				} );

				const knownAttributes = attributesOf( hardRules );
				result.relaxedAttributes.forEach( ( attribute ) => {
					expect( knownAttributes ).toContain( attribute );
				} );
			} )
		);
	} );
} );
