/**
 * Client-side port of ProductFinder\Engine\MatchEngine (includes/Engine/MatchEngine.php).
 * Deliberately mirrors that file's structure and algorithm rather than being
 * idiomatic "JS-first" — the two are verified to produce identical output
 * against a shared fixture file (see matchEngine.fixtures.test.js), and
 * keeping the shapes parallel is what makes that verification meaningful.
 *
 * Runs entirely in the browser against the category's full product list,
 * which is embedded once at initial render — no per-answer request to the
 * server (§7 of PRODUCT-FINDER-PROPOSAL.md).
 */

const comparators = {
	gte: ( actual, value ) => actual >= value,
	lte: ( actual, value ) => actual <= value,
	equals: ( actual, value ) => actual === value,
};

function satisfies( product, rule ) {
	return comparators[ rule.comparator ]( product[ rule.attribute ], rule.value );
}

function satisfiesAll( product, rules ) {
	return rules.every( ( rule ) => satisfies( product, rule ) );
}

/**
 * Filters products against the hard rules, relaxing one attribute at a time
 * (in the merchant-defined order) if nothing survives, until either
 * something survives or every hard rule has been relaxed.
 *
 * @return {[object[], string[]]} Survivors, and which attributes were relaxed to get them.
 */
function survivorsWithRelaxation( products, hardRules, relaxationOrder ) {
	let activeRules = hardRules;
	const relaxed = [];

	let survivors = products.filter( ( product ) => satisfiesAll( product, activeRules ) );

	for ( const attribute of relaxationOrder ) {
		if ( survivors.length > 0 ) {
			break;
		}

		activeRules = activeRules.filter( ( rule ) => rule.attribute !== attribute );
		relaxed.push( attribute );

		survivors = products.filter( ( product ) => satisfiesAll( product, activeRules ) );
	}

	return [ survivors, relaxed ];
}

function score( product, softRules ) {
	return softRules.reduce(
		( total, rule ) => total + ( satisfies( product, rule ) ? rule.weight : 0 ),
		0
	);
}

function compareByTiebreaker( a, b, tiebreaker ) {
	if ( ! tiebreaker ) {
		return 0;
	}

	const { attribute, direction } = tiebreaker;
	const av = a.product[ attribute ];
	const bv = b.product[ attribute ];
	const comparison = av < bv ? -1 : av > bv ? 1 : 0;

	return direction === 'desc' ? -comparison : comparison;
}

export function match( products, rules, options = {} ) {
	const limit = options.limit ?? 3;
	const relaxationOrder = options.relaxationOrder ?? [];
	const tiebreaker = options.tiebreaker ?? null;

	const hardRules = rules.filter( ( rule ) => rule.type === 'hard' );
	const softRules = rules.filter( ( rule ) => rule.type === 'soft' );

	const [ survivors, relaxedAttributes ] = survivorsWithRelaxation(
		products,
		hardRules,
		relaxationOrder
	);

	const scored = survivors.map( ( product, index ) => ( {
		index, // Preserves original order as the tie-break, matching the PHP port exactly.
		product,
		score: score( product, softRules ),
	} ) );

	scored.sort( ( a, b ) => {
		if ( b.score !== a.score ) {
			return b.score - a.score;
		}
		const tiebreak = compareByTiebreaker( a, b, tiebreaker );
		if ( tiebreak !== 0 ) {
			return tiebreak;
		}
		return a.index - b.index;
	} );

	const ranked = scored.slice( 0, limit );

	return {
		products: ranked.map( ( { product, score: productScore } ) => ( {
			product,
			score: productScore,
		} ) ),
		relaxedAttributes,
	};
}
