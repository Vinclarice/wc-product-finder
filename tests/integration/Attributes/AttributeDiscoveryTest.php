<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Attributes;

use ProductFinder\Attributes\AttributeDiscovery;
use ProductFinder\Tests\Integration\Support\WooCommerceProductFactory;
use WC_Product_Simple;
use WP_UnitTestCase;

final class AttributeDiscoveryTest extends WP_UnitTestCase {

	use WooCommerceProductFactory;

	public function test_returns_distinct_attribute_slugs_and_labels_used_in_the_category(): void {
		$category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];

		$product_a = new WC_Product_Simple();
		$product_a->set_name( 'Tent A' );
		$product_a->set_category_ids( array( $category_id ) );
		$product_a->set_attributes(
			array(
				self::make_local_attribute( 'Capacity', '4' ),
				self::make_local_attribute( 'Use Type', 'Backpacking' ),
			)
		);
		$product_a->save();

		// A second product using a differently-named attribute — proves
		// discovery isn't hardcoded to our own seed data's slugs.
		$product_b = new WC_Product_Simple();
		$product_b->set_name( 'Tent B' );
		$product_b->set_category_ids( array( $category_id ) );
		$product_b->set_attributes(
			array(
				self::make_local_attribute( 'Sleeps', '2' ),
			)
		);
		$product_b->save();

		$discovered = AttributeDiscovery::for_category( 'tents' );

		$slugs = array_column( $discovered, 'slug' );
		$this->assertContains( 'capacity', $slugs );
		$this->assertContains( 'use-type', $slugs );
		$this->assertContains( 'sleeps', $slugs );

		$labels = array_combine( $slugs, array_column( $discovered, 'label' ) );
		$this->assertSame( 'Capacity', $labels['capacity'] );
		$this->assertSame( 'Sleeps', $labels['sleeps'] );
	}

	public function test_each_slug_appears_only_once_even_if_used_on_multiple_products(): void {
		$category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];

		foreach ( array( 'Tent A', 'Tent B' ) as $name ) {
			$product = new WC_Product_Simple();
			$product->set_name( $name );
			$product->set_category_ids( array( $category_id ) );
			$product->set_attributes( array( self::make_local_attribute( 'Capacity', '4' ) ) );
			$product->save();
		}

		$discovered = AttributeDiscovery::for_category( 'tents' );

		$this->assertCount( 1, $discovered );
	}

	public function test_empty_category_returns_no_attributes(): void {
		wp_insert_term( 'Tents', 'product_cat' );

		$this->assertSame( array(), AttributeDiscovery::for_category( 'tents' ) );
	}

	public function test_raw_values_include_every_discovered_slug_per_product_even_when_absent(): void {
		$category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];

		$product_a = new WC_Product_Simple();
		$product_a->set_name( 'Tent A' );
		$product_a->set_category_ids( array( $category_id ) );
		$product_a->set_attributes(
			array(
				self::make_local_attribute( 'Capacity', '4' ),
				self::make_local_attribute( 'Use Type', 'Backpacking' ),
			)
		);
		$product_a->save();

		// Only has "capacity" — "use-type" is a discovered slug (from product A)
		// this product doesn't have, and must still appear here, as null, so
		// AttributeCompleteness::calculate() can count it as unset rather than
		// silently skipping it.
		$product_b = new WC_Product_Simple();
		$product_b->set_name( 'Tent B' );
		$product_b->set_category_ids( array( $category_id ) );
		$product_b->set_attributes( array( self::make_local_attribute( 'Capacity', '2' ) ) );
		$product_b->save();

		$raw = AttributeDiscovery::raw_values_for_category( 'tents' );

		$this->assertCount( 2, $raw );
		$this->assertEqualsCanonicalizing(
			array(
				'capacity' => '4',
				'use-type' => 'Backpacking',
			),
			self::find_by( $raw, 'capacity', '4' )
		);
		$this->assertEqualsCanonicalizing(
			array(
				'capacity' => '2',
				'use-type' => null,
			),
			self::find_by( $raw, 'capacity', '2' )
		);
	}

	public function test_distinct_values_returns_every_real_value_used_in_the_category_sorted(): void {
		$category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];

		foreach ( array( '4', '2', '10' ) as $capacity ) {
			$product = new WC_Product_Simple();
			$product->set_name( "Tent {$capacity}" );
			$product->set_category_ids( array( $category_id ) );
			$product->set_attributes( array( self::make_local_attribute( 'Capacity', $capacity ) ) );
			$product->save();
		}

		$values = AttributeDiscovery::distinct_values_for_attribute( 'tents', 'capacity' );

		// Natural sort, not lexical — "10" comes after "4", not between "1" and "2".
		$this->assertSame(
			array(
				array(
					'value' => '2',
					'label' => '2',
				),
				array(
					'value' => '4',
					'label' => '4',
				),
				array(
					'value' => '10',
					'label' => '10',
				),
			),
			$values
		);
	}

	public function test_distinct_values_deduplicates_repeated_real_values(): void {
		$category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];

		foreach ( array( 'Tent A', 'Tent B' ) as $name ) {
			$product = new WC_Product_Simple();
			$product->set_name( $name );
			$product->set_category_ids( array( $category_id ) );
			$product->set_attributes( array( self::make_local_attribute( 'Capacity', '4' ) ) );
			$product->save();
		}

		$values = AttributeDiscovery::distinct_values_for_attribute( 'tents', 'capacity' );

		$this->assertCount( 1, $values );
	}

	public function test_distinct_values_resolves_a_taxonomy_attributes_term_names_not_ids(): void {
		$category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];

		$product = new WC_Product_Simple();
		$product->set_name( 'Taxonomy Tent' );
		$product->set_category_ids( array( $category_id ) );
		$id = $product->save();

		$taxonomy_attribute = self::make_taxonomy_attribute( $id, 'Discovery Test Season', '3-season' );

		$product = wc_get_product( $id );
		$product->set_attributes( array( $taxonomy_attribute ) );
		$product->save();

		$values = AttributeDiscovery::distinct_values_for_attribute(
			'tents',
			wc_attribute_taxonomy_name( 'Discovery Test Season' )
		);

		$this->assertSame(
			array(
				array(
					'value' => '3-season',
					'label' => '3-season',
				),
			),
			$values
		);
	}

	public function test_distinct_values_for_an_unused_attribute_is_empty(): void {
		wp_insert_term( 'Tents', 'product_cat' );

		$this->assertSame( array(), AttributeDiscovery::distinct_values_for_attribute( 'tents', 'capacity' ) );
	}

	private static function find_by( array $rows, string $key, $value ): array {
		foreach ( $rows as $row ) {
			if ( $row[ $key ] === $value ) {
				return $row;
			}
		}
		self::fail( "No row found with {$key} = {$value}" );
	}
}
