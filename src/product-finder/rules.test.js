import { buildRules } from './rules';

const CAPACITY_QUESTION = {
	key: 'capacity',
	attribute: 'capacity',
	ruleType: 'hard',
	comparator: 'gte',
	valueType: 'int',
	input: { type: 'select', options: [] },
};

const USE_TYPE_QUESTION = {
	key: 'use_type',
	attribute: 'use_type',
	ruleType: 'soft',
	comparator: 'equals',
	valueType: 'string',
	weight: 3,
	input: { type: 'select', options: [] },
};

// A soft preference using 'equals' on a *numeric* attribute — the exact
// shape that exposed the original bug: casting was decided by comparator
// (equals -> string) instead of the attribute's real type (int), so this
// rule's value could never match a product's actual numeric season_rating.
const SEASON_RATING_QUESTION = {
	key: 'season_rating',
	attribute: 'season_rating',
	ruleType: 'soft',
	comparator: 'equals',
	valueType: 'int',
	weight: 2,
	input: { type: 'select', options: [] },
};

const PACKED_WEIGHT_QUESTION = {
	key: 'packed_weight',
	attribute: 'packed_weight',
	ruleType: 'soft',
	comparator: 'lte',
	weight: 2,
	input: { type: 'toggle', value: 5 },
};

describe( 'buildRules', () => {
	test( 'unanswered questions produce no rules', () => {
		const answers = { capacity: null, use_type: '' };
		expect( buildRules( [ CAPACITY_QUESTION, USE_TYPE_QUESTION ], answers ) ).toEqual( [] );
	} );

	test( 'a hard numeric question casts the answer to a number', () => {
		const answers = { capacity: '4' }; // select values arrive as strings from the DOM
		expect( buildRules( [ CAPACITY_QUESTION ], answers ) ).toEqual( [
			{ attribute: 'capacity', type: 'hard', comparator: 'gte', value: 4 },
		] );
	} );

	test( 'a soft categorical question lowercases the answer and includes its weight', () => {
		const answers = { use_type: 'Backpacking' };
		expect( buildRules( [ USE_TYPE_QUESTION ], answers ) ).toEqual( [
			{
				attribute: 'use_type',
				type: 'soft',
				comparator: 'equals',
				value: 'backpacking',
				weight: 3,
			},
		] );
	} );

	test( 'an equals comparator on a numeric question casts the answer to a number', () => {
		// Regression test: an 'equals' question on a numeric attribute
		// (season_rating) must produce a numeric rule value, not a string, or
		// MatchEngine's strict === never matches the product's own value.
		const answers = { season_rating: '3' }; // select values arrive as strings from the DOM
		expect( buildRules( [ SEASON_RATING_QUESTION ], answers ) ).toEqual( [
			{
				attribute: 'season_rating',
				type: 'soft',
				comparator: 'equals',
				value: 3,
				weight: 2,
			},
		] );
	} );

	test( 'a non-numeric answer to a numeric question is treated as unanswered', () => {
		// Kept in sync with RuleBuilder.php's equivalent guard: without this,
		// PHP would silently coerce a malformed answer to 0 (near-always-
		// permissive) while JS would produce NaN (always-false) for the same
		// input — the two paths diverging on the same URL/state.
		const answers = { capacity: 'abc' };
		expect( buildRules( [ CAPACITY_QUESTION ], answers ) ).toEqual( [] );
	} );

	test( 'a toggle question is included only when checked, using its fixed threshold', () => {
		expect( buildRules( [ PACKED_WEIGHT_QUESTION ], { packed_weight: false } ) ).toEqual( [] );
		expect( buildRules( [ PACKED_WEIGHT_QUESTION ], { packed_weight: null } ) ).toEqual( [] );
		expect( buildRules( [ PACKED_WEIGHT_QUESTION ], { packed_weight: true } ) ).toEqual( [
			{
				attribute: 'packed_weight',
				type: 'soft',
				comparator: 'lte',
				value: 5,
				weight: 2,
			},
		] );
	} );

	test( 'mixed answers only produce rules for the answered questions', () => {
		const answers = { capacity: '4', use_type: null, packed_weight: true };
		expect(
			buildRules( [ CAPACITY_QUESTION, USE_TYPE_QUESTION, PACKED_WEIGHT_QUESTION ], answers )
		).toEqual( [
			{ attribute: 'capacity', type: 'hard', comparator: 'gte', value: 4 },
			{ attribute: 'packed_weight', type: 'soft', comparator: 'lte', value: 5, weight: 2 },
		] );
	} );
} );
