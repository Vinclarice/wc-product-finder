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

import { store, getContext } from '@wordpress/interactivity';
import { match } from './matchEngine';
import { buildRules } from './rules';

const { state } = store( 'product-finder', {
	state: {
		get results() {
			const { products, questions, answers, relaxationOrder } = getContext();
			const rules = buildRules( questions, answers );
			return match( products, rules, {
				tiebreaker: { attribute: 'price', direction: 'asc' },
				limit: 3,
				relaxationOrder,
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
	},
} );
