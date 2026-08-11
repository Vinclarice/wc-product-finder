<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Finder;

use ProductFinder\Finder\FinderService;
use ProductFinder\Tests\Integration\Support\WooCommerceProductFactory;
use WC_Product_Simple;
use WP_UnitTestCase;

final class FinderServiceTest extends WP_UnitTestCase {

	use WooCommerceProductFactory;

	private int $category_id;

	public function set_up(): void {
		parent::set_up();
		$this->category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];
	}

	public function test_hard_filter_rules_narrow_results_using_real_woocommerce_data(): void {
		$this->create_tent( 'Small Tent', 2, 3.0, 3, 'Backpacking', 150 );
		$this->create_tent( 'Big Tent', 6, 10.0, 2, 'Car Camping', 300 );
		$this->create_tent( 'Right Tent', 4, 4.5, 3, 'Backpacking', 320 );

		$rules = array(
			array(
				'attribute'  => 'capacity',
				'type'       => 'hard',
				'comparator' => 'gte',
				'value'      => 4,
			),
			array(
				'attribute'  => 'use_type',
				'type'       => 'hard',
				'comparator' => 'equals',
				'value'      => 'backpacking',
			),
		);

		$result = FinderService::get_results( 'tents', $rules );

		$this->assertCount( 1, $result['products'] );
		$this->assertSame( 'Right Tent', $result['products'][0]['product']['name'] );
	}

	public function test_zero_match_relaxes_per_options_and_reports_which_attribute(): void {
		$this->create_tent( 'Only Tent', 2, 3.0, 3, 'Backpacking', 150 );

		$rules = array(
			array(
				'attribute'  => 'capacity',
				'type'       => 'hard',
				'comparator' => 'gte',
				'value'      => 6,
			),
		);

		$result = FinderService::get_results(
			'tents',
			$rules,
			array( 'relaxationOrder' => array( 'capacity' ) )
		);

		$this->assertCount( 1, $result['products'] );
		$this->assertSame( array( 'capacity' ), $result['relaxedAttributes'] );
	}

	public function test_zero_match_with_no_relaxation_order_returns_empty_results(): void {
		$this->create_tent( 'Only Tent', 2, 3.0, 3, 'Backpacking', 150 );

		$rules = array(
			array(
				'attribute'  => 'capacity',
				'type'       => 'hard',
				'comparator' => 'gte',
				'value'      => 6,
			),
		);

		$result = FinderService::get_results( 'tents', $rules );

		$this->assertCount( 0, $result['products'] );
		$this->assertSame( array(), $result['relaxedAttributes'] );
	}

	private function create_tent( string $name, int $capacity, float $packed_weight, int $season_rating, string $use_type, float $price ): void {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( (string) $price );
		$product->set_category_ids( array( $this->category_id ) );
		$product->set_attributes(
			array(
				self::make_local_attribute( 'Capacity', (string) $capacity ),
				self::make_local_attribute( 'Packed Weight', (string) $packed_weight ),
				self::make_local_attribute( 'Season Rating', (string) $season_rating ),
				self::make_local_attribute( 'Use Type', $use_type ),
			)
		);
		$product->save();
	}
}
