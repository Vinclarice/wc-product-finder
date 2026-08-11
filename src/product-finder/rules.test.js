import { buildRules } from './rules';

const CAPACITY_QUESTION = {
	key: 'capacity',
	attribute: 'capacity',
	ruleType: 'hard',
	comparator: 'gte',
	input: { type: 'select', options: [] },
};

const USE_TYPE_QUESTION = {
	key: 'use_type',
	attribute: 'use_type',
	ruleType: 'soft',
	comparator: 'equals',
	weight: 3,
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
