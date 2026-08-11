<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Finder;

use ProductFinder\Finder\EventCounter;
use WP_UnitTestCase;

final class EventCounterTest extends WP_UnitTestCase {

	public function test_counts_are_zero_when_nothing_has_been_recorded(): void {
		$this->assertSame(
			array(
				'view'       => 0,
				'zero_match' => 0,
			),
			EventCounter::get_counts( 'tents' )
		);
	}

	public function test_increment_increases_the_named_event_by_one(): void {
		EventCounter::increment( 'tents', 'view' );
		EventCounter::increment( 'tents', 'view' );
		EventCounter::increment( 'tents', 'zero_match' );

		$this->assertSame(
			array(
				'view'       => 2,
				'zero_match' => 1,
			),
			EventCounter::get_counts( 'tents' )
		);
	}

	public function test_counts_for_one_category_do_not_affect_another(): void {
		EventCounter::increment( 'tents', 'view' );
		EventCounter::increment( 'backpacks', 'view' );
		EventCounter::increment( 'backpacks', 'view' );

		$this->assertSame( 1, EventCounter::get_counts( 'tents' )['view'] );
		$this->assertSame( 2, EventCounter::get_counts( 'backpacks' )['view'] );
	}

	public function test_uninstall_removes_counts_for_every_category(): void {
		EventCounter::increment( 'tents', 'view' );
		EventCounter::increment( 'backpacks', 'zero_match' );

		EventCounter::uninstall();

		$this->assertSame(
			array(
				'view'       => 0,
				'zero_match' => 0,
			),
			EventCounter::get_counts( 'tents' )
		);
		$this->assertFalse( get_option( 'product_finder_event_counts' ) );
	}
}
