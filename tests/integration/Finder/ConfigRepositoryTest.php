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

	public function test_get_questions_is_empty_when_nothing_has_been_saved(): void {
		$this->assertSame( array(), ConfigRepository::get_questions( 'tents' ) );
	}

	public function test_questions_save_then_get_round_trips_for_the_saved_category(): void {
		$questions = array(
			array(
				'key'       => 'capacity',
				'attribute' => 'capacity',
				'ruleType'  => 'hard',
			),
		);

		ConfigRepository::save_questions( 'tents', $questions );

		$this->assertSame( $questions, ConfigRepository::get_questions( 'tents' ) );
	}

	public function test_questions_and_attribute_map_are_stored_independently_for_the_same_category(): void {
		ConfigRepository::save_attribute_map( 'tents', array( 'capacity' => 'tent-capacity' ) );
		ConfigRepository::save_questions( 'tents', array( array( 'key' => 'capacity' ) ) );

		$this->assertSame( array( 'capacity' => 'tent-capacity' ), ConfigRepository::get_attribute_map( 'tents' ) );
		$this->assertSame( array( array( 'key' => 'capacity' ) ), ConfigRepository::get_questions( 'tents' ) );
	}

	public function test_saving_questions_for_one_category_does_not_affect_another(): void {
		ConfigRepository::save_questions( 'tents', array( array( 'key' => 'tents-question' ) ) );
		ConfigRepository::save_questions( 'backpacks', array( array( 'key' => 'backpacks-question' ) ) );

		$this->assertSame( array( array( 'key' => 'tents-question' ) ), ConfigRepository::get_questions( 'tents' ) );
		$this->assertSame( array( array( 'key' => 'backpacks-question' ) ), ConfigRepository::get_questions( 'backpacks' ) );
	}

	public function test_uninstall_removes_saved_config_for_every_category(): void {
		ConfigRepository::save_attribute_map( 'tents', array( 'capacity' => 'tent-capacity' ) );
		ConfigRepository::save_questions( 'backpacks', array( array( 'key' => 'backpacks-question' ) ) );

		ConfigRepository::uninstall();

		$this->assertSame( array(), ConfigRepository::get_attribute_map( 'tents' ) );
		$this->assertSame( array(), ConfigRepository::get_questions( 'backpacks' ) );
		$this->assertFalse( get_option( 'product_finder_configs' ) );
	}
}
