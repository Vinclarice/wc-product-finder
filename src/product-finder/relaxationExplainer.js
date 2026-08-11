/**
 * JS port of RelaxationExplainer.php — see that file's docblock for the
 * scope decision (relaxation-only, not a general "why this fits" for soft
 * preferences) and PRODUCT-FINDER-PROPOSAL.md §5d. Verified against the same
 * fixture via relaxationExplainer.fixtures.test.js.
 *
 * Deliberately NOT translatable, unlike the PHP port: @wordpress/i18n
 * cannot currently be imported into a WordPress *script module* (view.js's
 * format, per block.json's viewScriptModule) — confirmed via a real build
 * failure ("Attempted to use WordPress script in a module: @wordpress/i18n,
 * which is not supported yet"), not a choice made here. The server-rendered/
 * no-JS text (RelaxationExplainer.php) is fully translatable; this
 * client-recomputed text, shown only once JS hydration takes over
 * reactivity, stays plain English until WordPress's script-module system
 * supports @wordpress/i18n.
 */
export function explainRelaxation( relaxedAttributes, questions ) {
	const labels = labelsFor( relaxedAttributes, questions );

	if ( labels.length === 0 ) {
		return null;
	}

	const noun = labels.length === 1 ? 'preference' : 'preferences';

	return `We relaxed your ${ joinWithAnd( labels ) } ${ noun } to show you more options.`;
}

function labelsFor( relaxedAttributes, questions ) {
	const labels = [];
	relaxedAttributes.forEach( ( attribute ) => {
		const question = questions.find( ( q ) => q.attribute === attribute );
		if ( question ) {
			labels.push( question.shortLabel );
		}
	} );
	return labels;
}

function joinWithAnd( items ) {
	if ( items.length === 1 ) {
		return items[ 0 ];
	}

	const last = items[ items.length - 1 ];
	const rest = items.slice( 0, -1 );

	return `${ rest.join( ', ' ) } and ${ last }`;
}
