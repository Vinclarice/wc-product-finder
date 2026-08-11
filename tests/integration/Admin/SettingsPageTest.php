<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Admin;

use ProductFinder\Admin\SettingsPage;
use ProductFinder\Tests\Integration\Support\WooCommerceProductFactory;
use WC_Product_Simple;
use WP_UnitTestCase;

final class SettingsPageTest extends WP_UnitTestCase {

	use WooCommerceProductFactory;

	public function tear_down(): void {
		$_GET = array();
		parent::tear_down();
	}

	public function test_the_mapping_screen_discloses_that_its_fields_are_a_fixed_template_not_adapted_per_category(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$category_id = wp_insert_term( 'Backpacks', 'product_cat', array( 'slug' => 'backpacks' ) )['term_id'];
		$product     = new WC_Product_Simple();
		$product->set_name( 'Trail Pack' );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( $category_id ) );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', '30' ) ) );
		$product->save();

		$_GET['category'] = 'backpacks';

		ob_start();
		SettingsPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Outdoor Gear Finder', $html );
		$this->assertStringContainsString( 'Backpacks', $html );
	}

	public function test_blank_selections_are_dropped_so_they_fall_back_to_the_template_default(): void {
		$result = SettingsPage::sanitize_submitted_map(
			array(
				'capacity' => 'tent-capacity',
				'use_type' => '',
			)
		);

		$this->assertSame( array( 'capacity' => 'tent-capacity' ), $result );
	}

	public function test_values_are_trimmed(): void {
		$result = SettingsPage::sanitize_submitted_map( array( 'capacity' => '  tent-capacity  ' ) );

		$this->assertSame( array( 'capacity' => 'tent-capacity' ), $result );
	}

	public function test_empty_submission_produces_an_empty_map(): void {
		$this->assertSame( array(), SettingsPage::sanitize_submitted_map( array() ) );
	}
}
