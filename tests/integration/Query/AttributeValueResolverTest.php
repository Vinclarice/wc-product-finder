<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Query;

use ProductFinder\Query\AttributeValueResolver;
use ProductFinder\Tests\Integration\Support\WooCommerceProductFactory;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Extracted from ProductArrayAdapter so AttributeDiscovery's value
 * auto-discovery (§13's per-category question editor, Phase 2) can resolve
 * a product's real attribute value the same way ProductArrayAdapter does,
 * without either duplicating the taxonomy-vs-local logic or reaching across
 * into the other class for it.
 */
final class AttributeValueResolverTest extends WP_UnitTestCase {

	use WooCommerceProductFactory;

	public function test_resolves_a_local_attributes_raw_value(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Tent' );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', '4' ) ) );
		$id      = $product->save();
		$product = wc_get_product( $id );

		$this->assertSame( '4', AttributeValueResolver::resolve( $product, 'capacity' ) );
	}

	public function test_resolves_a_taxonomy_attribute_to_its_term_name_not_its_id(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test Tent' );
		$id = $product->save();

		$taxonomy_attribute = self::make_taxonomy_attribute( $id, 'Resolver Test Season', '3-season' );

		$product = wc_get_product( $id );
		$product->set_attributes( array( $taxonomy_attribute ) );
		$product->save();
		$product = wc_get_product( $id );

		$this->assertSame(
			'3-season',
			AttributeValueResolver::resolve( $product, wc_attribute_taxonomy_name( 'Resolver Test Season' ) )
		);
	}

	public function test_a_product_without_the_attribute_at_all_resolves_to_null(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Bare Tent' );
		$id      = $product->save();
		$product = wc_get_product( $id );

		$this->assertNull( AttributeValueResolver::resolve( $product, 'capacity' ) );
	}
}
