<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Finder;

use ProductFinder\Finder\ConfigRepository;
use WP_UnitTestCase;

final class ConfigRepositoryTest extends WP_UnitTestCase {

	public function test_get_attribute_map_is_empty_when_nothing_has_been_saved(): void {
		$this->assertSame( array(), ConfigRepository::get_attribute_map( 'tents' ) );
	}

	public function test_save_then_get_round_trips_for_the_saved_category(): void {
		ConfigRepository::save_attribute_map( 'tents', array( 'capacity' => 'tent-capacity' ) );

		$this->assertSame(
			array( 'capacity' => 'tent-capacity' ),
			ConfigRepository::get_attribute_map( 'tents' )
		);
	}

	public function test_saving_one_category_does_not_affect_another(): void {
		ConfigRepository::save_attribute_map( 'tents', array( 'capacity' => 'tent-capacity' ) );
		ConfigRepository::save_attribute_map( 'backpacks', array( 'capacity' => 'pack-capacity' ) );

		$this->assertSame(
			array( 'capacity' => 'tent-capacity' ),
			ConfigRepository::get_attribute_map( 'tents' )
		);
		$this->assertSame(
			array( 'capacity' => 'pack-capacity' ),
			ConfigRepository::get_attribute_map( 'backpacks' )
		);
	}

	public function test_saving_again_overwrites_the_previous_mapping_for_that_category(): void {
		ConfigRepository::save_attribute_map( 'tents', array( 'capacity' => 'tent-capacity' ) );
		ConfigRepository::save_attribute_map( 'tents', array( 'capacity' => 'v2-capacity' ) );

		$this->assertSame(
			array( 'capacity' => 'v2-capacity' ),
			ConfigRepository::get_attribute_map( 'tents' )
		);
	}
}
