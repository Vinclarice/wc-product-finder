<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * Server-rendered default state (build order step 5): the "no questions answered
 * yet" view — every candidate product in the category, ranked by MatchEngine with
 * no rules applied (so the tiebreaker alone decides order) and capped to 3. This
 * same code path doubles as the eventual no-JavaScript fallback content (§8 MVP).
 * Real shopper-driven rules are wired in client-side at step 6 (Interactivity API).
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

use ProductFinder\Finder\FinderService;

$product_category = $attributes['productCategory'] ?? 'tents';
$result            = class_exists( 'WooCommerce' )
	? FinderService::get_results(
		$product_category,
		array(),
		array(
			'tiebreaker' => array(
				'attribute' => 'price',
				'direction' => 'asc',
			),
			'limit'      => 3,
		)
	)
	: array( 'products' => array() );
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<?php if ( empty( $result['products'] ) ) : ?>
		<p>
			<?php esc_html_e( 'No products found for this category yet.', 'product-finder' ); ?>
		</p>
	<?php else : ?>
		<ul class="product-finder__results">
			<?php foreach ( $result['products'] as $entry ) : ?>
				<?php $product = $entry['product']['_product']; ?>
				<li class="product-finder__result">
					<a href="<?php echo esc_url( $product->get_permalink() ); ?>">
						<?php echo esc_html( $product->get_name() ); ?>
					</a>
					<span class="product-finder__price">
						<?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</div>
