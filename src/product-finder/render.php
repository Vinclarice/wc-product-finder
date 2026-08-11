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
 * custom question set (§13's per-category question editor, resolved by
 * QuestionSetResolver) — no admin UI creates one yet, so in practice this
 * is still always TentsTemplate's for now. edit.js's editor-side notice and
 * the admin mapping screen's disclaimer are still accurate until that UI
 * ships (see their own docblocks for the plan to make them conditional).
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

$raw_get_answers = $_GET['product_finder'][ $product_category ] ?? array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filtering, not a state-changing action.
if ( ! is_array( $raw_get_answers ) ) {
	$raw_get_answers = array();
}
$answers = array();
foreach ( $raw_get_answers as $key => $value ) {
	$answers[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
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
$initial_js_answers = array();
foreach ( $questions as $question ) {
	$key = $question['key'];
	if ( 'toggle' === $question['input']['type'] ) {
		$initial_js_answers[ $key ] = array_key_exists( $key, $answers ) ? true : null;
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
	)
);
?>
<div <?php echo get_block_wrapper_attributes(); ?> data-wp-interactive="product-finder" data-wp-context='<?php echo esc_attr( $instance_context ); ?>'>
	<h2 class="product-finder__heading">
		<?php echo esc_html( $heading ); ?>
	</h2>

	<form method="get" data-wp-on--submit="actions.preventFormSubmit">
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
			<button type="button" class="button" data-wp-on--click="actions.reset">
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
			<a class="button" href="<?php echo esc_url( remove_query_arg( 'product_finder' ) ); ?>">
				<?php esc_html_e( 'Reset', 'product-finder' ); ?>
			</a>
		</noscript>
	</form>

	<div aria-live="polite" aria-atomic="true">
		<p data-wp-bind--hidden="state.hasResults">
			<?php esc_html_e( 'No products found for this category yet.', 'product-finder' ); ?>
		</p>

		<p
			class="product-finder__relaxation-message"
			data-wp-bind--hidden="!state.results.relaxationMessage"
			data-wp-text="state.results.relaxationMessage"
		></p>

		<ul class="product-finder__results">
			<template data-wp-each--result="state.results.products" data-wp-each-key="context.result.product.id">
				<li class="product-finder__result">
					<img data-wp-bind--src="context.result.product.image" data-wp-bind--alt="context.result.product.name" />
					<a data-wp-bind--href="context.result.product.permalink" data-wp-text="context.result.product.name"></a>
					<span class="product-finder__price" data-wp-text="context.result.product.priceLabel"></span>
					<span class="product-finder__stock" data-wp-bind--hidden="!context.result.product.inStock">
						<?php esc_html_e( 'In stock', 'product-finder' ); ?>
					</span>
					<span class="product-finder__stock" data-wp-bind--hidden="context.result.product.inStock">
						<?php esc_html_e( 'Out of stock', 'product-finder' ); ?>
					</span>
					<ul class="product-finder__specs">
						<template data-wp-each--spec="context.result.product.specs" data-wp-each-key="context.spec.label">
							<li>
								<span data-wp-text="context.spec.label"></span>:
								<span data-wp-text="context.spec.value"></span>
							</li>
						</template>
					</ul>
					<a
						class="button add_to_cart_button ajax_add_to_cart"
						data-wp-bind--href="context.result.product.addToCartUrl"
						data-wp-bind--data-product_id="context.result.product.id"
						data-quantity="1"
						rel="nofollow"
					>
						<?php esc_html_e( 'Add to cart', 'product-finder' ); ?>
					</a>
				</li>
			</template>
		</ul>
	</div>
</div>
