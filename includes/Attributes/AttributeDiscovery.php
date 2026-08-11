<?php

declare(strict_types=1);

namespace ProductFinder\Attributes;

use ProductFinder\Query\ProductQuery;

/**
 * Finds the WooCommerce attributes actually in use across a category's
 * products — global (taxonomy) and local attributes alike, since
 * WC_Product::get_attributes() already merges both into one array keyed by
 * slug. This is what populates the attribute-mapping screen's dropdowns
 * (build order step 7 / §5c) with a merchant's *real* attribute names,
 * rather than assuming they match our own seed data's slugs.
 */
final class AttributeDiscovery {

	public static function for_category( string $category_slug ): array {
		$products = ProductQuery::for_category( $category_slug, -1 );

		$discovered = array();
		foreach ( $products as $product ) {
			foreach ( $product->get_attributes() as $slug => $attribute ) {
				if ( ! isset( $discovered[ $slug ] ) ) {
					$discovered[ $slug ] = wc_attribute_label( $attribute->get_name() );
				}
			}
		}

		$result = array();
		foreach ( $discovered as $slug => $label ) {
			$result[] = array(
				'slug'  => $slug,
				'label' => $label,
			);
		}
		return $result;
	}

	/**
	 * One assoc array per product in the category, containing every slug
	 * discovered anywhere in the category (not just that product's own
	 * attributes) — feeds directly into AttributeCompleteness::calculate()
	 * for the mapping screen's completeness view, without duplicating its
	 * "what counts as set" logic here.
	 */
	public static function raw_values_for_category( string $category_slug ): array {
		$products = ProductQuery::for_category( $category_slug, -1 );

		$all_slugs = array();
		foreach ( $products as $product ) {
			$all_slugs = array_merge( $all_slugs, array_keys( $product->get_attributes() ) );
		}
		$all_slugs = array_unique( $all_slugs );

		$rows = array();
		foreach ( $products as $product ) {
			$attributes = $product->get_attributes();
			$row        = array();
			foreach ( $all_slugs as $slug ) {
				$options      = isset( $attributes[ $slug ] ) ? $attributes[ $slug ]->get_options() : array();
				$row[ $slug ] = $options[0] ?? null;
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
