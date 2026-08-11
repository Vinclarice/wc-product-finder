/**
 * Turns a starter template's question config + the shopper's current
 * answers into MatchEngine rules. Kept separate from view.js (which just
 * wires this into the Interactivity store) so it's unit-testable without
 * booting the store/DOM machinery — the branching here (toggle vs. select,
 * numeric vs. categorical casting) is real logic worth covering directly.
 */

export function buildRules( questions, answers ) {
	return questions
		.filter( ( question ) => shouldIncludeRule( question, answers[ question.key ] ) )
		.map( ( question ) => buildRule( question, answers[ question.key ] ) );
}

export function shouldIncludeRule( question, answerValue ) {
	if ( question.input.type === 'toggle' ) {
		return answerValue === true;
	}
	return answerValue !== undefined && answerValue !== null && answerValue !== '';
}

export function buildRule( question, answerValue ) {
	const rule = {
		attribute: question.attribute,
		type: question.ruleType,
		comparator: question.comparator,
		value: ruleValue( question, answerValue ),
	};
	if ( question.ruleType === 'soft' ) {
		rule.weight = question.weight;
	}
	return rule;
}

/**
 * Casts based on the question's declared valueType (matching
 * TentsTemplate::attribute_map()'s type for the same attribute), not the
 * comparator. This used to branch on comparator (gte/lte -> numeric, else ->
 * string), which silently broke season_rating: its comparator is 'equals'
 * but its attribute is numeric, so the old logic always produced a string
 * rule value that could never strictly-equal the product's actual numeric
 * value embedded in state.
 */
function ruleValue( question, answerValue ) {
	// A toggle's rule value is the fixed threshold from its config (e.g.
	// "under 5 lb"), not the checkbox's own true/false answer.
	if ( question.input.type === 'toggle' ) {
		return question.input.value;
	}
	if ( question.valueType === 'int' || question.valueType === 'float' ) {
		return Number( answerValue );
	}
	return String( answerValue ).toLowerCase();
}
