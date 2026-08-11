<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Query;

use ProductFinder\Query\ProductQuery;
use WC_Product_Simple;
use WP_UnitTestCase;

final class ProductQueryTest extends WP_UnitTestCase {

	public function test_for_category_returns_only_products_in_that_category_up_to_the_limit(): void {
		$tents_category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];
		$other_category_id = wp_insert_term( 'Backpacks', 'product_cat' )['term_id'];

		$tent_ids = array();
		foreach ( array( 'Tent A', 'Tent B', 'Tent C', 'Tent D' ) as $name ) {
			$product = new WC_Product_Simple();
			$product->set_name( $name );
			$product->set_status( 'publish' );
			$product->set_category_ids( array( $tents_category_id ) );
			$tent_ids[] = $product->save();
		}

		$backpack = new WC_Product_Simple();
		$backpack->set_name( 'Backpack A' );
		$backpack->set_status( 'publish' );
		$backpack->set_category_ids( array( $other_category_id ) );
		$backpack->save();

		$results = ProductQuery::for_category( 'tents', 3 );

		$this->assertCount( 3, $results );
		foreach ( $results as $product ) {
			$this->assertContains( $product->get_id(), $tent_ids );
		}
	}
}
