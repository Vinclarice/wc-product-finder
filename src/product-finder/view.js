/**
 * Interactivity API store for the Product Finder block (build order step 6).
 *
 * Server-embedded initial state (via wp_interactivity_state() in render.php)
 * supplies `products` (every candidate in the category), `questions` (the
 * starter template's question config), `relaxationOrder`, and `answers`
 * (starts as every question key mapped to null). This module only adds the
 * reactive `results` getter and the `setAnswer` action — everything needed
 * to compute results already lives in state, seeded once from the server.
 * No request back to the server happens per answer (§7 of the proposal).
 */

import { store, getContext } from '@wordpress/interactivity';
import { match } from './matchEngine';

const { state } = store( 'product-finder', {
	state: {
		get results() {
			const rules = buildRules( state.questions, state.answers );
			return match( state.products, rules, {
				tiebreaker: { attribute: 'price', direction: 'asc' },
				limit: 3,
				relaxationOrder: state.relaxationOrder,
			} );
		},
		// Directive expressions only support plain property paths, not inline
		// operators — so "is there anything to show" has to be its own getter
		// rather than a `state.results.products.length > 0` comparison inline
		// in the markup.
		get hasResults() {
			return state.results.products.length > 0;
		},
	},
	actions: {
		setAnswer( event ) {
			const { questionKey } = getContext();
			state.answers[ questionKey ] = event.target.value;
		},
	},
} );

function buildRules( questions, answers ) {
	return questions
		.filter( ( question ) => isAnswered( answers[ question.key ] ) )
		.map( ( question ) => buildRule( question, answers[ question.key ] ) );
}

function isAnswered( value ) {
	return value !== undefined && value !== null && value !== '';
}

function buildRule( question, answerValue ) {
	const rule = {
		attribute: question.attribute,
		type: question.ruleType,
		comparator: question.comparator,
		value: castAnswer( answerValue, question.comparator ),
	};
	if ( question.ruleType === 'soft' ) {
		rule.weight = question.weight;
	}
	return rule;
}

function castAnswer( value, comparator ) {
	if ( comparator === 'gte' || comparator === 'lte' ) {
		return Number( value );
	}
	return String( value ).toLowerCase();
}
