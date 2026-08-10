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
	}
}
