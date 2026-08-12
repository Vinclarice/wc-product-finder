<?php

declare(strict_types=1);

namespace ProductFinder\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use WC_Product;

/**
 * Converts a WC_Product into the plain, JSON-safe array shape both
 * ProductFinder\Engine\MatchEngine consumes and the Interactivity API embeds
 * as client-side state (§9/§10 of PRODUCT-FINDER-PROPOSAL.md — the client
 * runs its own port of the engine against this same data, so it can't carry
 * the WC_Product object itself). AttributeValueResolver handles reading a
 * merchant-mapped attribute's real value regardless of kind (local/custom
 * vs global/taxonomy — see its own docblock); this class maps that value to
 * the finder's own attribute name and casts it to the type the engine's
 * comparators expect.
 */
final class ProductArrayAdapter {

	/**
	 * @param array<string, array{slug: string, type: string}> $attribute_map Finder attribute name => WC attribute slug + value type.
	 */
	public static function to_array( WC_Product $product, array $attribute_map ): array {
		$image_id = $product->get_image_id();

		// Mirrors WooCommerce's own loop add-to-cart button logic
		// (woocommerce_template_loop_add_to_cart): a product only gets the
		// add_to_cart_button/ajax_add_to_cart treatment when it can actually
		// be bought straight from a listing. A variable product can't — the
		// shopper has to pick a variation first — so WooCommerce's
		// wc-add-to-cart.js would fire an AJAX add that fails, instead of
		// sending them to the product page to choose. Grouped and external
		// products are the same shape of problem.
		$purchasable = $product->is_purchasable() && $product->is_in_stock();

		$result = array(
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'permalink'    => $product->get_permalink(),
			'addToCartUrl' => $product->add_to_cart_url(),
			'price'        => (float) $product->get_price(),
			// Plain text, not wc_price()'s HTML — this gets embedded as JSON state
			// and rendered via a text-only directive on the client.
			'priceLabel'   => wp_strip_all_tags( wc_price( $product->get_price() ) ),
			'image'        => $image_id
				? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' )
				: wc_placeholder_img_src(),
			'inStock'      => $product->is_in_stock(),
			// "Add to cart" for a simple in-stock product, "Select options"
			// for a variable one, "Read more" when it can't be bought —
			// WooCommerce's own wording, so the button never promises
			// something the click can't deliver.
			'addToCartLabel'        => $product->add_to_cart_text(),
			'isPurchasable'         => $purchasable,
			'supportsAjaxAddToCart' => $purchasable && $product->supports( 'ajax_add_to_cart' ),
		);

		foreach ( $attribute_map as $finder_attribute => $config ) {
			$result[ $finder_attribute ] = self::cast(
				AttributeValueResolver::resolve( $product, $config['slug'] ),
				$config['type']
			);
		}

		return $result;
	}

	private static function cast( ?string $value, string $type ) {
		if ( $value === null ) {
			return null;
		}

		switch ( $type ) {
			case 'int':
				return (int) $value;
			case 'float':
				return (float) $value;
			default:
				return strtolower( trim( $value ) );
		}
	}
}
