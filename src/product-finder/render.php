<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * Interactivity API wiring (build order step 6): the markup below uses
 * directives (data-wp-*) so WordPress's server-side directive processor
 * (triggered by the "interactivity" block support) expands them into real
 * HTML at render time — the same markup then hydrates client-side via
 * view.js, which recomputes `state.results` locally as the shopper answers
 * questions, with no request back to the server per answer (§7 of
 * PRODUCT-FINDER-PROPOSAL.md). Initial state is seeded once here with every
 * candidate product in the category so the client has what it needs.
 *
 * Only the "capacity" question is wired up so far — the rest are added the
 * same way once this pattern is proven (§10 build order step 6).
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

use ProductFinder\Engine\MatchEngine;
use ProductFinder\Finder\FinderService;
use ProductFinder\Templates\TentsTemplate;

$product_category = $attributes['productCategory'] ?? 'tents';
$match_options     = array(
	'tiebreaker'      => array(
		'attribute' => 'price',
		'direction' => 'asc',
	),
	'limit'           => 3,
	'relaxationOrder' => TentsTemplate::relaxation_order(),
);

if ( class_exists( 'WooCommerce' ) ) {
	$questions   = TentsTemplate::questions();
	$candidates  = FinderService::get_candidates( $product_category );
	$answers     = array_fill_keys( array_column( $questions, 'key' ), null );
	$result      = MatchEngine::match( $candidates, array(), $match_options );
} else {
	$questions  = array();
	$candidates = array();
	$answers    = array();
	$result     = array(
		'products'          => array(),
		'relaxedAttributes' => array(),
	);
}

wp_interactivity_state(
	'product-finder',
	array(
		'products'        => $candidates,
		'questions'       => $questions,
		'answers'         => $answers,
		'relaxationOrder' => $match_options['relaxationOrder'],
		'results'         => $result,
	)
);
?>
<div <?php echo get_block_wrapper_attributes(); ?> data-wp-interactive="product-finder">
	<div class="product-finder__questions">
		<?php foreach ( $questions as $question ) : ?>
			<label class="product-finder__question">
				<?php echo esc_html( $question['label'] ); ?>
				<select
					data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'questionKey' => $question['key'] ) ) ); ?>'
					data-wp-on--change="actions.setAnswer"
				>
					<option value=""><?php esc_html_e( 'Any', 'product-finder' ); ?></option>
					<?php foreach ( $question['input']['options'] as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endforeach; ?>
	</div>

	<p data-wp-bind--hidden="state.hasResults">
		<?php esc_html_e( 'No products found for this category yet.', 'product-finder' ); ?>
	</p>

	<ul class="product-finder__results">
		<template data-wp-each--result="state.results.products" data-wp-each-key="context.result.product.id">
			<li class="product-finder__result">
				<a data-wp-bind--href="context.result.product.permalink" data-wp-text="context.result.product.name"></a>
				<span class="product-finder__price" data-wp-text="context.result.product.priceLabel"></span>
			</li>
		</template>
	</ul>
</div>
