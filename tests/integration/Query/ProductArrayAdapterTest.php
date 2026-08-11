<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Query;

use ProductFinder\Query\ProductArrayAdapter;
use ProductFinder\Tests\Integration\Support\WooCommerceProductFactory;
use WC_Product_Simple;
use WP_UnitTestCase;

final class ProductArrayAdapterTest extends WP_UnitTestCase {

	use WooCommerceProductFactory;

	private const ATTRIBUTE_MAP = array(
		'capacity'      => array( 'slug' => 'capacity', 'type' => 'int' ),
		'packed_weight' => array( 'slug' => 'packed-weight', 'type' => 'float' ),
		'season_rating' => array( 'slug' => 'season-rating', 'type' => 'int' ),
		'use_type'      => array( 'slug' => 'use-type', 'type' => 'string' ),
	);

	public function test_converts_wc_product_local_attributes_to_a_typed_array(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Tent' );
		$product->set_regular_price( '249.00' );
		$product->set_attributes(
			array(
				self::make_local_attribute( 'Capacity', '4' ),
				self::make_local_attribute( 'Packed Weight', '3.2' ),
				self::make_local_attribute( 'Season Rating', '3' ),
				self::make_local_attribute( 'Use Type', 'Backpacking' ),
			)
		);
		$id = $product->save();
		$product = wc_get_product( $id );

		$result = ProductArrayAdapter::to_array( $product, self::ATTRIBUTE_MAP );

		$this->assertSame( $id, $result['id'] );
		$this->assertSame( 'Test Tent', $result['name'] );
		$this->assertSame( $product->get_permalink(), $result['permalink'] );
		$this->assertSame( 249.0, $result['price'] );
		$this->assertIsString( $result['priceLabel'] );
		$this->assertStringContainsString( '249.00', $result['priceLabel'] );
		$this->assertSame( 4, $result['capacity'] );
		$this->assertSame( 3.2, $result['packed_weight'] );
		$this->assertSame( 3, $result['season_rating'] );
		// Categorical string values are lowercased so rule values ('backpacking')
		// don't have to fragilely match WooCommerce's stored display casing.
		$this->assertSame( 'backpacking', $result['use_type'] );
	}

	public function test_result_is_json_safe_for_embedding_as_interactivity_api_state(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Tent' );
		$product->set_regular_price( '249.00' );
		$id = $product->save();
		$product = wc_get_product( $id );

		$result = ProductArrayAdapter::to_array( $product, self::ATTRIBUTE_MAP );

		$this->assertJson( wp_json_encode( $result ) );
	}

	public function test_missing_attribute_becomes_null_rather_than_erroring(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Bare Tent' );
		$product->set_regular_price( '100.00' );
		// No attributes set at all.
		$id = $product->save();
		$product = wc_get_product( $id );

		$result = ProductArrayAdapter::to_array( $product, self::ATTRIBUTE_MAP );

		$this->assertNull( $result['capacity'] );
	}
}
