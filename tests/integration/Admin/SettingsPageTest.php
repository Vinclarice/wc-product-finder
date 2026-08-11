<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Admin;

use ProductFinder\Admin\SettingsPage;
use WP_UnitTestCase;

final class SettingsPageTest extends WP_UnitTestCase {

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
