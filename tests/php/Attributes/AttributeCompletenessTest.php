<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Attributes;

use PHPUnit\Framework\TestCase;
use ProductFinder\Attributes\AttributeCompleteness;

final class AttributeCompletenessTest extends TestCase {

	public function test_attribute_set_on_every_product_is_100_percent(): void {
		$products = array(
			array( 'id' => 1, 'capacity' => 4 ),
			array( 'id' => 2, 'capacity' => 2 ),
		);

		$result = AttributeCompleteness::calculate( $products, array( 'capacity' ) );

		$this->assertSame(
			array(
				'attribute'  => 'capacity',
				'total'      => 2,
				'set'        => 2,
				'percentage' => 100,
			),
			$result['capacity']
		);
	}

	public function test_partial_completeness_counts_missing_key_null_and_empty_string_as_unset(): void {
		$products = array(
			array( 'id' => 1, 'packed_weight' => 4.2 ),
			array( 'id' => 2, 'packed_weight' => null ),
			array( 'id' => 3, 'packed_weight' => '' ),
			array( 'id' => 4 ), // key entirely absent
		);

		$result = AttributeCompleteness::calculate( $products, array( 'packed_weight' ) );

		$this->assertSame(
			array(
				'attribute'  => 'packed_weight',
				'total'      => 4,
				'set'        => 1,
				'percentage' => 25,
			),
			$result['packed_weight']
		);
	}

	public function test_zero_is_a_valid_set_value_not_treated_as_missing(): void {
		$products = array(
			array( 'id' => 1, 'packed_weight' => 0 ),
		);

		$result = AttributeCompleteness::calculate( $products, array( 'packed_weight' ) );

		$this->assertSame( 1, $result['packed_weight']['set'] );
	}

	public function test_multiple_attributes_are_calculated_independently(): void {
		$products = array(
			array( 'id' => 1, 'capacity' => 4, 'season_rating' => 3 ),
			array( 'id' => 2, 'capacity' => 2 ), // season_rating missing
		);

		$result = AttributeCompleteness::calculate( $products, array( 'capacity', 'season_rating' ) );

		$this->assertSame( 100, $result['capacity']['percentage'] );
		$this->assertSame( 50, $result['season_rating']['percentage'] );
	}

	public function test_empty_catalogue_does_not_divide_by_zero(): void {
		$result = AttributeCompleteness::calculate( array(), array( 'capacity' ) );

		$this->assertSame(
			array(
				'attribute'  => 'capacity',
				'total'      => 0,
				'set'        => 0,
				'percentage' => 0,
			),
			$result['capacity']
		);
	}
}
