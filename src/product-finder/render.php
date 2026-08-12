<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * No-JS fallback + accessibility pass (build order step 8). The questions
 * are wrapped in a real <form method="get"> — GET params are the source of
 * truth for initial state either way, JS or not, so a shared/bookmarked
 * filtered URL shows the same thing to everyone, and RuleBuilder (a PHP
 * mirror of rules.js) builds the same rules server-side that the client
 * would build from the same answers. The submit button only ever appears
 * inside <noscript> — JS users never need it, since view.js intercepts
 * each control's change event and updates instantly without a page reload
 * (§7 of PRODUCT-FINDER-PROPOSAL.md).
 *
 * Multiple instances on one page: `products`, `questions`, `answers`, and
 * `relaxationOrder` live in a per-block `data-wp-context` on the wrapper
 * div, not in `wp_interactivity_state()` — state is a single namespace-wide
 * object merged across every block via array_replace_recursive, so two
 * instances sharing it corrupts both (confirmed via
 * WP_Interactivity_API::state(), which is exactly this merge). Context is
 * DOM-scoped per instance instead. `results`/`hasResults` stay on `state`
 * (directives read `state.results...`), but are registered as *derived
 * state* closures that call wp_interactivity_get_context() to compute
 * per-instance — the server-side counterpart of the `getContext()`-based
 * getters in view.js, and how the Interactivity API expects a shared-state
 * getter to be scoped to the calling instance.
 *
 * Known limitation: a native GET form submission replaces the whole query
 * string with only its own fields, so a second finder block (different
 * category) on the same page would lose its own answers if a no-JS visitor
 * submits this one. Accepted for now — affects only no-JS visitors on a
 * page with multiple finder instances, not the common case.
 *
 * $questions is TentsTemplate's defaults unless the category has a saved
 * custom question set (§13's per-category question editor) — resolved by
 * QuestionSetResolver, which a merchant creates via the admin "Questions"
 * screen (includes/Admin/SettingsPage.php). edit.js's editor-side notice
 * and the admin mapping screen's disclaimer are both worded to be accurate
 * either way, since neither has a way to know at a glance whether a given
 * category actually has a saved custom set.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ProductFinder\Engine\MatchEngine;
use ProductFinder\Finder\ConfigRepository;
use ProductFinder\Finder\EventCounter;
use ProductFinder\Finder\FinderService;
use ProductFinder\Finder\QuestionSetResolver;
use ProductFinder\Finder\RelaxationExplainer;
use ProductFinder\Finder\RuleBuilder;
use ProductFinder\Templates\TentsTemplate;

$product_category = $attributes['productCategory'] ?? 'tents';
$category_term     = get_term_by( 'slug', $product_category, 'product_cat' );
$heading           = $category_term
	? sprintf(
		/* translators: %s: the selected WooCommerce product category's real name. */
		__( 'Find your %s match', 'product-finder' ),
		$category_term->name
	)
	: __( 'Find your perfect match', 'product-finder' );
$match_options     = array(
	'tiebreaker' => array(
		'attribute' => 'price',
		'direction' => 'asc',
	),
	'limit'      => 3,
);

$raw_get_answers = array();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering of a public page, not a state-changing action; a nonce would break shareable filtered URLs, which are the point of reading answers from the query string at all.
if ( isset( $_GET['product_finder'][ $product_category ] ) ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Same as above; unslashed on this line, and every value is sanitized in the loop below, which the sniff can't follow across statements.
	$raw_get_answers = wp_unslash( $_GET['product_finder'][ $product_category ] );
}
if ( ! is_array( $raw_get_answers ) ) {
	$raw_get_answers = array();
}
$answers = array();
foreach ( $raw_get_answers as $key => $value ) {
	// A hand-edited query string can nest a further array here
	// (?product_finder[tents][capacity][]=4), which the form itself never
	// produces. There's no sensible scalar answer to read out of that, and
	// casting it would raise "Array to string conversion" on a public
	// page, so drop it and leave the question unanswered.
	if ( is_array( $value ) ) {
		continue;
	}
	// Already unslashed above, as a whole array.
	$answers[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
}

if ( class_exists( 'WooCommerce' ) ) {
	$question_set = QuestionSetResolver::resolve(
		TentsTemplate::questions(),
		ConfigRepository::get_questions( $product_category )
	);
	$questions                        = $question_set['questions'];
	$match_options['relaxationOrder'] = $question_set['relaxationOrder'];

	$candidates = FinderService::get_candidates( $product_category );
	$rules      = RuleBuilder::build( $questions, $answers );
	$result     = MatchEngine::match( $candidates, $rules, $match_options );

	// Basic local aggregate counts (build order step 9 / §8 MVP scope) —
	// server-side only, see EventCounter's docblock for why.
	EventCounter::increment( $product_category, 'view' );
	if ( empty( $result['products'] ) ) {
		EventCounter::increment( $product_category, 'zero_match' );
	}
} else {
	$questions                        = array();
	$candidates                       = array();
	$match_options['relaxationOrder'] = array();
}

// The client's `answers` shape differs slightly from $_GET's for toggles: a
// JS checkbox reports a boolean, where a submitted form only reports
// presence — translate here so the client picks up cleanly from the URL.
//
// An unanswered toggle's key is omitted entirely, not set to null: this
// same $initial_js_answers array is what state.results' derived-state
// closure (below) reads back via wp_interactivity_get_context() and passes
// straight to RuleBuilder::build() for server-side rendering — and
// RuleBuilder's own docblock is explicit that it needs $_GET-shaped
// answers, where a toggle's "on" state is signaled by key presence, not a
// boolean value. Setting the key to null here (instead of omitting it)
// previously made every toggle question look "answered" on every server
// render regardless of shopper input — confirmed via
// RenderTest::test_a_toggle_question_with_no_get_answer_is_not_treated_as_active_on_the_server.
// Omitting the key is also what the client needs: rules.js's
// shouldIncludeRule() checks `answerValue === true`, which is equally
// false for `undefined` (key absent) as for `null` (key present), so this
// doesn't change client-side behavior at all.
$initial_js_answers = array();
foreach ( $questions as $question ) {
	$key = $question['key'];
	if ( 'toggle' === $question['input']['type'] ) {
		if ( array_key_exists( $key, $answers ) ) {
			$initial_js_answers[ $key ] = true;
		}
	} else {
		$initial_js_answers[ $key ] = $answers[ $key ] ?? null;
	}
}

$instance_context = wp_json_encode(
	array(
		'products'        => $candidates,
		'questions'       => $questions,
		'answers'         => $initial_js_answers,
		'relaxationOrder' => $match_options['relaxationOrder'],
	)
);

$compute_results = static function () use ( $match_options ) {
	$instance = wp_interactivity_get_context();
	$rules    = RuleBuilder::build( $instance['questions'] ?? array(), $instance['answers'] ?? array() );

	$result = MatchEngine::match(
		$instance['products'] ?? array(),
		$rules,
		array(
			'tiebreaker'      => $match_options['tiebreaker'],
			'limit'           => $match_options['limit'],
			'relaxationOrder' => $instance['relaxationOrder'] ?? array(),
		)
	);

	// §5d of PRODUCT-FINDER-PROPOSAL.md: when a hard filter had to be
	// relaxed to find anything, say so. Deliberately scoped to only the
	// relaxation case — see RelaxationExplainer's docblock.
	$result['relaxationMessage'] = RelaxationExplainer::explain(
		$result['relaxedAttributes'],
		$instance['questions'] ?? array()
	);

	return $result;
};

wp_interactivity_state(
	'product-finder',
	array(
		'results'    => $compute_results,
		'hasResults' => static function () use ( $compute_results ) {
			return ! empty( $compute_results()['products'] );
		},
		// What the live region announces. Fully translatable here, including
		// correct plural forms for locales that have more than two, because
		// this runs in PHP. view.js's client-side counterpart can't be:
		// @wordpress/i18n isn't importable into a script module, the same
		// documented limitation relaxationExplainer.js carries, so the
		// post-hydration announcement stays English until that changes.
		'resultsAnnouncement' => static function () use ( $compute_results ) {
			$products = $compute_results()['products'];
			$count    = count( $products );

			if ( 0 === $count ) {
				return __( 'No matching products', 'product-finder' );
			}

			// The names matter, and not just as a courtesy: results are
			// capped at `limit`, so a count alone reads "3 matching products"
			// for almost every answer a shopper gives. The text would never
			// change, the DOM would never mutate, and a live region that
			// doesn't mutate announces nothing at all — the whole feature
			// would be inert. Naming the matches makes the region change
			// exactly when what's on screen changes.
			$names = implode(
				', ',
				array_map( static fn( $entry ) => $entry['product']['name'], $products )
			);

			return sprintf(
				/* translators: 1: how many products match the shopper's answers. 2: comma-separated list of those product names. */
				_n( '%1$d matching product: %2$s', '%1$d matching products: %2$s', $count, 'product-finder' ),
				$count,
				$names
			);
		},
	)
);
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Returns a ready-made attribute string that WordPress has already escaped internally; escaping it again would corrupt the markup. ?> data-wp-interactive="product-finder" data-wp-context='<?php echo esc_attr( $instance_context ); ?>'>
	<h2 class="product-finder__heading">
		<?php echo esc_html( $heading ); ?>
	</h2>

	<?php // Names the form landmark, so a screen-reader user tabbing or jumping by region knows what this group of controls is for. ?>
	<form method="get" aria-label="<?php esc_attr_e( 'Product finder questions', 'product-finder' ); ?>" data-wp-on--submit="actions.preventFormSubmit">
		<div class="product-finder__questions">
			<?php foreach ( $questions as $question ) : ?>
				<?php
				$question_context = wp_json_encode( array( 'questionKey' => $question['key'] ) );
				$field_name        = "product_finder[{$product_category}][{$question['key']}]";
				?>
				<label class="product-finder__question">
					<?php echo esc_html( $question['label'] ); ?>
					<?php if ( 'toggle' === $question['input']['type'] ) : ?>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $field_name ); ?>"
							<?php checked( array_key_exists( $question['key'], $answers ) ); ?>
							data-wp-context='<?php echo esc_attr( $question_context ); ?>'
							data-wp-on--change="actions.setAnswer"
							data-wp-watch="callbacks.syncCurrentAnswerToControl"
						/>
					<?php else : ?>
						<select
							name="<?php echo esc_attr( $field_name ); ?>"
							data-wp-context='<?php echo esc_attr( $question_context ); ?>'
							data-wp-on--change="actions.setAnswer"
							data-wp-watch="callbacks.syncCurrentAnswerToControl"
						>
							<option value=""><?php esc_html_e( 'Any', 'product-finder' ); ?></option>
							<?php foreach ( $question['input']['options'] as $option ) : ?>
								<option
									value="<?php echo esc_attr( $option['value'] ); ?>"
									<?php selected( $answers[ $question['key'] ] ?? '', (string) $option['value'] ); ?>
								>
									<?php echo esc_html( $option['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
			</div>
			<button type="button" class="button product-finder__reset" data-wp-on--click="actions.reset">
				<?php esc_html_e( 'Reset', 'product-finder' ); ?>
			</button>
			<noscript>
			<?php
			// submit_button() lives in wp-admin/includes/template.php, which
			// WordPress only autoloads for wp-admin requests — not here, on a
			// public front-end page. Without this, every real (non-admin)
			// visitor gets a fatal "Call to undefined function" on this block,
			// no-JS or not.
			if ( ! function_exists( 'submit_button' ) ) {
				require_once ABSPATH . 'wp-admin/includes/template.php';
			}
			submit_button( $heading, 'primary', '', false );
			?>
			<a class="button product-finder__reset" href="<?php echo esc_url( remove_query_arg( 'product_finder' ) ); ?>">
				<?php esc_html_e( 'Reset', 'product-finder' ); ?>
			</a>
		</noscript>
	</form>

	<?php
	// The live region is deliberately small: a screen-reader-only count plus
	// the relaxation message, both a sentence at most. It used to wrap the
	// whole results list with aria-atomic="true", which meant every single
	// answer change re-announced the entire region — measured at 391
	// characters, three full product cards with every spec, read out again
	// on each keystroke-equivalent interaction. The list itself sits outside
	// the region now; the count is what tells a screen-reader user that
	// something changed, and they can then read the results at their own
	// pace.
	?>
	<div class="product-finder__status" aria-live="polite">
		<p class="product-finder__sr-only" data-wp-text="state.resultsAnnouncement"></p>

		<p
			class="product-finder__relaxation-message"
			data-wp-bind--hidden="!state.results.relaxationMessage"
			data-wp-text="state.results.relaxationMessage"
		></p>
	</div>

	<div>
		<p data-wp-bind--hidden="state.hasResults">
			<?php esc_html_e( 'No products found for this category yet.', 'product-finder' ); ?>
		</p>

		<ul class="product-finder__results">
			<template data-wp-each--result="state.results.products" data-wp-each-key="context.result.product.id">
				<li class="product-finder__result">
					<?php
					// Decorative on purpose: the product's name is the very
					// next element, as a link. Giving the image the same name
					// made a screen reader read every product twice — once as
					// an image, once as the link.
					?>
					<img alt="" data-wp-bind--src="context.result.product.image" />
					<a data-wp-bind--href="context.result.product.permalink" data-wp-text="context.result.product.name"></a>
					<span class="product-finder__price" data-wp-text="context.result.product.priceLabel"></span>
					<span class="product-finder__stock" data-wp-bind--hidden="!context.result.product.inStock">
						<?php esc_html_e( 'In stock', 'product-finder' ); ?>
					</span>
					<span class="product-finder__stock" data-wp-bind--hidden="context.result.product.inStock">
						<?php esc_html_e( 'Out of stock', 'product-finder' ); ?>
					</span>
					<ul class="product-finder__specs">
						<template data-wp-each--spec="context.result.product.specs" data-wp-each-key="context.spec.attribute">
							<li>
								<span data-wp-text="context.spec.label"></span>:
								<span data-wp-text="context.spec.value"></span>
							</li>
						</template>
					</ul>
					<?php
					// Both classes are per-product, not fixed: WooCommerce's
					// wc-add-to-cart.js binds to .ajax_add_to_cart, and a
					// variable/grouped/external product can't be added from a
					// listing at all. Applying them unconditionally made the
					// button silently fail on those. See
					// ProductArrayAdapter::to_array().
					?>
					<a
						class="button"
						data-wp-class--add_to_cart_button="context.result.product.isPurchasable"
						data-wp-class--ajax_add_to_cart="context.result.product.supportsAjaxAddToCart"
						data-wp-bind--href="context.result.product.addToCartUrl"
						data-wp-bind--data-product_id="context.result.product.id"
						data-quantity="1"
						rel="nofollow"
						data-wp-text="context.result.product.addToCartLabel"
					></a>
				</li>
			</template>
		</ul>
	</div>
</div>
