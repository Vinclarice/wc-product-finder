<?php
/**
 * Seeds ~24 sample tent products into WooCommerce for local development.
 *
 * Idempotent: re-running updates existing products (matched by SKU) rather
 * than creating duplicates. Run via WP-CLI inside the wp-env container:
 *
 *   npm run seed
 *
 * Attributes are stored as WooCommerce *local* (per-product, non-taxonomy)
 * product attributes — the realistic case of "a merchant already has these
 * as WooCommerce attributes" that the finder is meant to map against.
 * Values are kept as clean scalars ("4", "3.2") rather than display strings
 * ("4 lbs") since the MVP's mapping step expects pre-defined, typed
 * attributes rather than free-text needing normalization (see §5c).
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This script must be run via `wp eval-file`, not directly.\n" );
	exit( 1 );
}

if ( ! class_exists( 'WooCommerce' ) ) {
	fwrite( STDERR, "WooCommerce is not active — cannot seed tent products.\n" );
	exit( 1 );
}

$tents = array(
	array( 'name' => 'Solo Skyline 1P', 'sku' => 'TENT-001', 'price' => 179.00, 'capacity' => 1, 'packed_weight' => 2.1, 'season_rating' => 3, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Solo Skyline 1P Winter', 'sku' => 'TENT-002', 'price' => 289.00, 'capacity' => 1, 'packed_weight' => 3.4, 'season_rating' => 4, 'use_type' => 'Backpacking' ),
	array( 'name' => 'TrailLite 2P', 'sku' => 'TENT-003', 'price' => 219.00, 'capacity' => 2, 'packed_weight' => 3.2, 'season_rating' => 3, 'use_type' => 'Backpacking' ),
	array( 'name' => 'TrailLite 2P Ultralight', 'sku' => 'TENT-004', 'price' => 349.00, 'capacity' => 2, 'packed_weight' => 2.4, 'season_rating' => 3, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Ridgeback 2P Basecamp', 'sku' => 'TENT-005', 'price' => 259.00, 'capacity' => 2, 'packed_weight' => 6.8, 'season_rating' => 3, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Alpine Storm 2P', 'sku' => 'TENT-006', 'price' => 399.00, 'capacity' => 2, 'packed_weight' => 4.9, 'season_rating' => 4, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Wayfarer 3P', 'sku' => 'TENT-007', 'price' => 299.00, 'capacity' => 3, 'packed_weight' => 4.6, 'season_rating' => 3, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Wayfarer 3P Family', 'sku' => 'TENT-008', 'price' => 239.00, 'capacity' => 3, 'packed_weight' => 8.1, 'season_rating' => 2, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Horizon 3P All-Season', 'sku' => 'TENT-009', 'price' => 459.00, 'capacity' => 3, 'packed_weight' => 5.3, 'season_rating' => 4, 'use_type' => 'Both' ),
	array( 'name' => 'Basecamp 4P Cabin', 'sku' => 'TENT-010', 'price' => 279.00, 'capacity' => 4, 'packed_weight' => 12.5, 'season_rating' => 2, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Summit Trail 4P', 'sku' => 'TENT-011', 'price' => 389.00, 'capacity' => 4, 'packed_weight' => 6.2, 'season_rating' => 3, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Summit Trail 4P Lite', 'sku' => 'TENT-012', 'price' => 449.00, 'capacity' => 4, 'packed_weight' => 4.8, 'season_rating' => 3, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Homestead 4P Weekender', 'sku' => 'TENT-013', 'price' => 199.00, 'capacity' => 4, 'packed_weight' => 14.0, 'season_rating' => 2, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Northface Pro 4P', 'sku' => 'TENT-014', 'price' => 549.00, 'capacity' => 4, 'packed_weight' => 7.1, 'season_rating' => 4, 'use_type' => 'Both' ),
	array( 'name' => 'Basecamp 5P Cabin', 'sku' => 'TENT-015', 'price' => 329.00, 'capacity' => 5, 'packed_weight' => 15.8, 'season_rating' => 2, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Expedition 5P', 'sku' => 'TENT-016', 'price' => 599.00, 'capacity' => 5, 'packed_weight' => 8.9, 'season_rating' => 4, 'use_type' => 'Both' ),
	array( 'name' => 'Wanderer 5P Trail', 'sku' => 'TENT-017', 'price' => 429.00, 'capacity' => 5, 'packed_weight' => 7.4, 'season_rating' => 3, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Homestead 6P Weekender', 'sku' => 'TENT-018', 'price' => 259.00, 'capacity' => 6, 'packed_weight' => 18.2, 'season_rating' => 2, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Basecamp 6P Family', 'sku' => 'TENT-019', 'price' => 389.00, 'capacity' => 6, 'packed_weight' => 20.5, 'season_rating' => 3, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Expedition 6P Pro', 'sku' => 'TENT-020', 'price' => 649.00, 'capacity' => 6, 'packed_weight' => 11.3, 'season_rating' => 4, 'use_type' => 'Both' ),
	array( 'name' => 'Solo Skyline 1P Value', 'sku' => 'TENT-021', 'price' => 99.00, 'capacity' => 1, 'packed_weight' => 2.9, 'season_rating' => 2, 'use_type' => 'Backpacking' ),
	array( 'name' => 'TrailLite 2P Value', 'sku' => 'TENT-022', 'price' => 149.00, 'capacity' => 2, 'packed_weight' => 3.8, 'season_rating' => 2, 'use_type' => 'Backpacking' ),
	array( 'name' => 'Ridgeback 3P Value', 'sku' => 'TENT-023', 'price' => 189.00, 'capacity' => 3, 'packed_weight' => 5.5, 'season_rating' => 2, 'use_type' => 'Car Camping' ),
	array( 'name' => 'Alpine Storm 4P Pro', 'sku' => 'TENT-024', 'price' => 519.00, 'capacity' => 4, 'packed_weight' => 5.9, 'season_rating' => 4, 'use_type' => 'Backpacking' ),
);

$category_id = get_term_by( 'slug', 'tents', 'product_cat' );
if ( ! $category_id ) {
	$created = wp_insert_term( 'Tents', 'product_cat', array( 'slug' => 'tents' ) );
	$category_id = $created['term_id'];
} else {
	$category_id = $category_id->term_id;
}

$created_count = 0;
$updated_count = 0;

foreach ( $tents as $tent ) {
	$existing_id = wc_get_product_id_by_sku( $tent['sku'] );
	$product     = $existing_id ? new WC_Product_Simple( $existing_id ) : new WC_Product_Simple();

	$product->set_name( $tent['name'] );
	$product->set_sku( $tent['sku'] );
	$product->set_regular_price( (string) $tent['price'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_manage_stock( false );
	$product->set_stock_status( 'instock' );
	$product->set_category_ids( array( $category_id ) );

	$attributes = array();
	foreach ( array(
		'capacity'      => 'Capacity',
		'packed_weight' => 'Packed Weight',
		'season_rating' => 'Season Rating',
		'use_type'      => 'Use Type',
	) as $key => $label ) {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( $label );
		$attribute->set_options( array( (string) $tent[ $key ] ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		$attributes[] = $attribute;
	}
	$product->set_attributes( $attributes );

	$product->save();

	$existing_id ? $updated_count++ : $created_count++;
}

echo "Seeded tents: {$created_count} created, {$updated_count} updated.\n";
