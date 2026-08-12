/**
 * Interactivity API store for the Product Finder block (build order step 6).
 *
 * `products` (every candidate in the category), `questions` (the starter
 * template's question config), `relaxationOrder`, and `answers` (seeded
 * from $_GET so a shared/bookmarked filtered URL shows the same thing
 * before and after hydration — see render.php and RuleBuilder for the
 * no-JS fallback this shares state with) all live in a per-block
 * `data-wp-context` set on the block's wrapper div in render.php, not in
 * `state`. `state` is one object shared by every instance of this
 * namespace on the page, so a page with two Product Finder blocks (two
 * categories) would have one instance's products/answers stomp the
 * other's — confirmed via how wp_interactivity_state() merges repeated
 * calls (array_replace_recursive across the whole namespace, not scoped
 * per block). `data-wp-context` is DOM-scoped per instance instead, which
 * is what actually isolates two blocks on one page from each other.
 *
 * `results`/`hasResults` stay on `state` (directives read
 * `state.results...`, matching render.php's markup), but as *derived
 * state*: getters that pull their inputs from `getContext()` so each
 * instance's own context — not a shared value — drives the computation.
 * render.php registers PHP-side equivalents (closures that call
 * wp_interactivity_get_context()) so server-side rendering, including the
 * no-JS fallback, is scoped the same way.
 *
 * No request back to the server happens per answer (§7 of the proposal).
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import { match } from './matchEngine';
import { buildRules } from './rules';
import { explainRelaxation } from './relaxationExplainer';

const { state } = store( 'product-finder', {
	state: {
		get results() {
			const { products, questions, answers, relaxationOrder } =
				getContext();
			const rules = buildRules( questions, answers );
			const result = match( products, rules, {
				tiebreaker: { attribute: 'price', direction: 'asc' },
				limit: 3,
				relaxationOrder,
			} );
			return {
				...result,
				relaxationMessage: explainRelaxation(
					result.relaxedAttributes,
					questions
				),
			};
		},
		// Directive expressions only support plain property paths, not inline
		// operators — so "is there anything to show" has to be its own getter
		// rather than a `state.results.products.length > 0` comparison inline
		// in the markup.
		get hasResults() {
			return state.results.products.length > 0;
		},
		// Drives the block's aria-live region, so a screen-reader user is
		// told the results changed without having the whole list read back
		// to them each time. English-only for the same documented reason as
		// relaxationExplainer.js: @wordpress/i18n cannot be imported into a
		// script module. render.php registers a translatable PHP equivalent
		// that covers the initial render and the no-JS path.
		get resultsAnnouncement() {
			const products = state.results.products;

			if ( products.length === 0 ) {
				return 'No matching products';
			}

			// Names, not just a count — see the PHP counterpart in
			// render.php for why a bare count makes the live region inert.
			const names = products
				.map( ( entry ) => entry.product.name )
				.join( ', ' );

			return `${ products.length } matching ${
				products.length === 1 ? 'product' : 'products'
			}: ${ names }`;
		},
		// The value of whichever question control is currently evaluating
		// this — needs to be derived rather than read directly off the
		// answer object in the markup, since directive expressions don't
		// support dynamic property lookups like
		// `context.answers[context.questionKey]`. Consumed by
		// callbacks.syncCurrentAnswerToControl, not directly by a
		// data-wp-bind directive — see that callback for why.
		get currentAnswer() {
			const { answers, questionKey } = getContext();
			return answers[ questionKey ];
		},
	},
	actions: {
		setAnswer( event ) {
			const context = getContext();
			const value =
				event.target.type === 'checkbox'
					? event.target.checked
					: event.target.value;
			context.answers[ context.questionKey ] = value;
		},
		// The <form> exists for the no-JS fallback (build order step 8) — a
		// JS-enabled visitor's answers already update instantly via
		// setAnswer, so an actual form submission/page reload here would
		// only happen accidentally (e.g. an Enter keypress) and should be
		// swallowed rather than navigating away.
		preventFormSubmit( event ) {
			event.preventDefault();
		},
		// Clears every answer back to "unanswered" so the default,
		// unfiltered view returns. Mutating context.answers directly (rather
		// than one key at a time via setAnswer) is what makes
		// callbacks.syncCurrentAnswerToControl necessary — without it, the
		// controls themselves wouldn't visually reset even though the
		// results correctly do.
		reset() {
			const context = getContext();
			Object.keys( context.answers ).forEach( ( key ) => {
				context.answers[ key ] = null;
			} );
		},
	},
	callbacks: {
		// data-wp-bind--value/--checked only reliably applies on a control's
		// first hydration (confirmed empirically: after that, changing
		// context.answers via reset() updates state.currentAnswer and the
		// results correctly, but a native <select>/<input type="checkbox">'s
		// own DOM value/checked property doesn't follow along). A
		// data-wp-watch effect that imperatively sets the DOM property
		// itself is the reliable alternative.
		syncCurrentAnswerToControl() {
			const { ref } = getElement();
			const value = state.currentAnswer;
			if ( 'checkbox' === ref.type ) {
				ref.checked = Boolean( value );
			} else {
				ref.value = value ?? '';
			}
		},
	},
} );
