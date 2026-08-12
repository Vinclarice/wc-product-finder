/* eslint-disable jest/no-conditional-expect -- A property of the form
   "when X holds, Y must hold" is an implication, and expressing it means
   asserting inside the X branch. The rule targets expects hidden in
   try/catch or in branches that may never run in an example-based test;
   here fast-check drives both branches across generated inputs, and the
   PHP mirrors (see *PropertyTest.php) express the same implications. */

/**
 * Property-based tests for rules.js, alongside the existing example-based
 * rules.test.js. Mirrors RuleBuilderPropertyTest.php's properties and
 * generator shape exactly.
 */
import fc from 'fast-check';
import { buildRules } from './rules';

// Deliberately mixes hard/soft and select/toggle in one fixed pool —
// varying which subset gets "answered" already exercises every branch of
// shouldIncludeRule() without needing a fully random question generator.
const QUESTION_POOL = [
	{
		key: 'capacity',
		attribute: 'capacity',
		ruleType: 'hard',
		comparator: 'gte',
		weight: 3,
		valueType: 'int',
		input: { type: 'select', options: [] },
	},
	{
		key: 'use_type',
		attribute: 'use_type',
		ruleType: 'soft',
		comparator: 'equals',
		weight: 2,
		valueType: 'string',
		input: { type: 'select', options: [] },
	},
	{
		key: 'is_lightweight',
		attribute: 'is_lightweight',
		ruleType: 'soft',
		comparator: 'equals',
		weight: 1,
		valueType: 'string',
		input: { type: 'toggle', value: true },
	},
];

const answeredQuestionsArb = fc
	.subarray( QUESTION_POOL )
	.map( ( answeredSubset ) => {
		const answers = {};
		answeredSubset.forEach( ( question ) => {
			answers[ question.key ] =
				question.input.type === 'toggle' ? true : '5';
		} );
		return [ QUESTION_POOL, answers ];
	} );

describe( 'buildRules (property-based)', () => {
	it( 'never produces more rules than questions', () => {
		fc.assert(
			fc.property( answeredQuestionsArb, ( [ questions, answers ] ) => {
				const rules = buildRules( questions, answers );
				expect( rules.length ).toBeLessThanOrEqual( questions.length );
			} )
		);
	} );

	it( 'every rule attribute comes from the input questions', () => {
		fc.assert(
			fc.property( answeredQuestionsArb, ( [ questions, answers ] ) => {
				const rules = buildRules( questions, answers );
				const knownAttributes = questions.map( ( q ) => q.attribute );
				rules.forEach( ( rule ) => {
					expect( knownAttributes ).toContain( rule.attribute );
				} );
			} )
		);
	} );

	it( 'soft rules always carry a weight and hard rules never do', () => {
		fc.assert(
			fc.property( answeredQuestionsArb, ( [ questions, answers ] ) => {
				const rules = buildRules( questions, answers );
				rules.forEach( ( rule ) => {
					if ( rule.type === 'soft' ) {
						expect( rule ).toHaveProperty( 'weight' );
					} else {
						expect( rule ).not.toHaveProperty( 'weight' );
					}
				} );
			} )
		);
	} );
} );
