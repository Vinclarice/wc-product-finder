<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Engine;

use PHPUnit\Framework\TestCase;
use ProductFinder\Engine\MatchEngine;

final class MatchEngineTest extends TestCase {

	public function test_hard_filter_excludes_products_that_do_not_satisfy_it(): void {
		$products = array(
			array( 'id' => 1, 'capacity' => 4 ),
			array( 'id' => 2, 'capacity' => 2 ),
		);

		$rules = array(
			array(
				'attribute'  => 'capacity',
				'type'       => 'hard',
				'comparator' => 'gte',
				'value'      => 4,
			),
		);

		$result = MatchEngine::match( $products, $rules );

		$this->assertCount( 1, $result['products'] );
		$this->assertSame( 1, $result['products'][0]['product']['id'] );
		$this->assertSame( array(), $result['relaxedAttributes'] );
	}

	public function test_multiple_hard_filters_require_all_to_be_satisfied(): void {
		$products = array(
			array( 'id' => 1, 'capacity' => 4, 'use_type' => 'backpacking' ),
			array( 'id' => 2, 'capacity' => 4, 'use_type' => 'car_camping' ),
			array( 'id' => 3, 'capacity' => 2, 'use_type' => 'backpacking' ),
		);

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

		$result = MatchEngine::match( $products, $rules );

		$this->assertCount( 1, $result['products'] );
		$this->assertSame( 1, $result['products'][0]['product']['id'] );
	}

	public function test_lte_comparator_excludes_products_above_the_value(): void {
		$products = array(
			array( 'id' => 1, 'price' => 320 ),
			array( 'id' => 2, 'price' => 400 ),
		);

		$rules = array(
			array(
				'attribute'  => 'price',
				'type'       => 'hard',
				'comparator' => 'lte',
				'value'      => 350,
			),
		);

		$result = MatchEngine::match( $products, $rules );

		$this->assertCount( 1, $result['products'] );
		$this->assertSame( 1, $result['products'][0]['product']['id'] );
	}

	public function test_soft_preferences_rank_by_summed_weight_and_default_to_top_three(): void {
		$products = array(
			array( 'id' => 1, 'use_type' => 'car_camping', 'season_rating' => 2 ), // 0
			array( 'id' => 2, 'use_type' => 'backpacking', 'season_rating' => 2 ), // 3
			array( 'id' => 3, 'use_type' => 'backpacking', 'season_rating' => 3 ), // 5
			array( 'id' => 4, 'use_type' => 'car_camping', 'season_rating' => 3 ), // 2
			array( 'id' => 5, 'use_type' => 'backpacking', 'season_rating' => 3 ), // 5, ties with #3
		);

		$rules = array(
			array(
				'attribute'  => 'use_type',
				'type'       => 'soft',
				'comparator' => 'equals',
				'value'      => 'backpacking',
				'weight'     => 3,
			),
			array(
				'attribute'  => 'season_rating',
				'type'       => 'soft',
				'comparator' => 'equals',
				'value'      => 3,
				'weight'     => 2,
			),
		);

		$result = MatchEngine::match( $products, $rules );

		$this->assertCount( 3, $result['products'] );
		$ids = array_map( static fn( $entry ) => $entry['product']['id'], $result['products'] );
		// #3 and #5 both score 5 and must lead (order between the tied pair isn't asserted here);
		// #2 scores 3 and must be third.
		$this->assertEqualsCanonicalizing( array( 3, 5 ), array( $ids[0], $ids[1] ) );
		$this->assertSame( 2, $ids[2] );
		$this->assertSame( 5, $result['products'][0]['score'] );
		$this->assertSame( 5, $result['products'][1]['score'] );
		$this->assertSame( 3, $result['products'][2]['score'] );
	}

	public function test_tiebreaker_option_orders_equally_scored_products(): void {
		$products = array(
			array( 'id' => 1, 'price' => 300 ),
			array( 'id' => 2, 'price' => 250 ),
			array( 'id' => 3, 'price' => 275 ),
		);

		// No soft rules, so every survivor scores 0 and the tiebreaker alone decides order.
		$result = MatchEngine::match(
			$products,
			array(),
			array( 'tiebreaker' => array( 'attribute' => 'price', 'direction' => 'asc' ) )
		);

		$ids = array_map( static fn( $entry ) => $entry['product']['id'], $result['products'] );
		$this->assertSame( array( 2, 3, 1 ), $ids );
	}

	public function test_fallback_relaxes_one_hard_filter_when_nothing_survives(): void {
		$products = array(
			array( 'id' => 1, 'capacity' => 4, 'price' => 400 ), // fails price only
			array( 'id' => 2, 'capacity' => 2, 'price' => 200 ), // fails capacity only
		);

		$rules = array(
			array(
				'attribute'  => 'capacity',
				'type'       => 'hard',
				'comparator' => 'gte',
				'value'      => 4,
			),
			array(
				'attribute'  => 'price',
				'type'       => 'hard',
				'comparator' => 'lte',
				'value'      => 300,
			),
		);

		$result = MatchEngine::match(
			$products,
			$rules,
			array( 'relaxationOrder' => array( 'price', 'capacity' ) )
		);

		// Dropping the price constraint (relaxed first) lets product #1 through on capacity alone.
		$this->assertCount( 1, $result['products'] );
		$this->assertSame( 1, $result['products'][0]['product']['id'] );
		$this->assertSame( array( 'price' ), $result['relaxedAttributes'] );
	}

	public function test_fallback_relaxes_multiple_hard_filters_in_order_if_still_nothing_survives(): void {
		$products = array(
			array( 'id' => 1, 'capacity' => 2, 'price' => 400 ), // fails both
		);

		$rules = array(
			array(
				'attribute'  => 'capacity',
				'type'       => 'hard',
				'comparator' => 'gte',
				'value'      => 4,
			),
			array(
				'attribute'  => 'price',
				'type'       => 'hard',
				'comparator' => 'lte',
				'value'      => 300,
			),
		);

		$result = MatchEngine::match(
			$products,
			$rules,
			array( 'relaxationOrder' => array( 'price', 'capacity' ) )
		);

		// Relaxing price alone still isn't enough (product also fails capacity), so capacity relaxes too.
		$this->assertCount( 1, $result['products'] );
		$this->assertSame( 1, $result['products'][0]['product']['id'] );
		$this->assertSame( array( 'price', 'capacity' ), $result['relaxedAttributes'] );
	}
}
