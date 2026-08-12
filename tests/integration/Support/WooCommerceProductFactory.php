<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Support;

use WC_Product_Attribute;

trait WooCommerceProductFactory {

	private static function make_local_attribute( string $name, string $value ): WC_Product_Attribute {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( $name );
		$attribute->set_options( array( $value ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		return $attribute;
	}

	/**
	 * A real variable product with one priced, in-stock variation. That
	 * combination is the interesting one: the product *is* purchasable, but
	 * not straight from a listing, because the shopper has to choose a
	 * variation first. Anything simpler (a variable product with no
	 * variations) is unpurchasable for the boring reason of having no price,
	 * and wouldn't exercise the distinction.
	 *
	 * @param int[] $category_ids
	 */
	private static function make_variable_product( string $name, string $price, array $category_ids = array() ): \WC_Product_Variable {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( '2P', '4P' ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$parent = new \WC_Product_Variable();
		$parent->set_name( $name );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		if ( ! empty( $category_ids ) ) {
			$parent->set_category_ids( $category_ids );
		}
		$parent_id = $parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_attributes( array( 'size' => '2P' ) );
		$variation->set_regular_price( $price );
		$variation->set_stock_status( 'instock' );
		$variation->save();

		// Without this the parent's own price/stock data is still the
		// pre-variation state, so is_purchasable() would report false.
		\WC_Product_Variable::sync( $parent_id );

		return wc_get_product( $parent_id );
	}

	/**
	 * Creates (or reuses) a real WooCommerce global/taxonomy attribute and
	 * term, assigns the term to the given already-saved product via the
	 * taxonomy relationship, and returns a WC_Product_Attribute ready to pass
	 * to $product->set_attributes() — mirroring what a merchant's real global
	 * attribute looks like, as opposed to make_local_attribute()'s per-product
	 * custom attributes. Needed because WC_Product_Attribute::get_options()
	 * returns integer term IDs for taxonomy attributes, not raw strings.
	 */
	private static function make_taxonomy_attribute( int $product_id, string $attribute_name, string $term_name ): WC_Product_Attribute {
		$taxonomy = wc_attribute_taxonomy_name( $attribute_name );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			wc_create_attribute(
				array(
					'name'    => $attribute_name,
					'slug'    => sanitize_title( $attribute_name ),
					'type'    => 'select',
					'orderby' => 'menu_order',
				)
			);
			foreach ( wc_get_attribute_taxonomies() as $tax ) {
				register_taxonomy(
					wc_attribute_taxonomy_name( $tax->attribute_name ),
					array( 'product' ),
					array( 'hierarchical' => false )
				);
			}
		}

		$existing_term = get_term_by( 'name', $term_name, $taxonomy );
		$term_id       = $existing_term ? $existing_term->term_id : wp_insert_term( $term_name, $taxonomy )['term_id'];

		wp_set_object_terms( $product_id, array( (int) $term_id ), $taxonomy );

		$attribute = new WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $attribute_name ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options( array( (int) $term_id ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		return $attribute;
	}
}
