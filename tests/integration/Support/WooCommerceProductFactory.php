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
