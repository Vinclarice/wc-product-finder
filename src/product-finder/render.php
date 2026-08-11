<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * Static skeleton (build order step 4): shows the top 3 published products in
 * the block's configured category. No questions/matching yet — that's wired
 * in at step 5 (§10 of PRODUCT-FINDER-PROPOSAL.md).
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 */

use ProductFinder\Query\ProductQuery;

$product_category = $attributes['productCategory'] ?? 'tents';
$products          = class_exists( 'WooCommerce' ) ? ProductQuery::for_category( $product_category, 3 ) : array();
?>
<div <?php echo get_block_wrapper_attributes(); ?>>
	<?php if ( empty( $products ) ) : ?>
		<p>
			<?php esc_html_e( 'No products found for this category yet.', 'product-finder' ); ?>
		</p>
	<?php else : ?>
		<ul class="product-finder__results">
			<?php foreach ( $products as $product ) : ?>
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
