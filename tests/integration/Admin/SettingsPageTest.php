<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Admin;

use ProductFinder\Admin\SettingsPage;
use ProductFinder\Finder\ConfigRepository;
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

	public function test_the_questions_section_is_prefilled_with_the_starter_templates_questions_by_default(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$category_id = wp_insert_term( 'Backpacks', 'product_cat', array( 'slug' => 'backpacks-qs1' ) )['term_id'];
		$product     = new WC_Product_Simple();
		$product->set_name( 'Trail Pack' );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( $category_id ) );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', '30' ) ) );
		$product->save();

		$_GET['category'] = 'backpacks-qs1';

		ob_start();
		SettingsPage::render();
		$html = ob_get_clean();

		// TentsTemplate's own question text, pre-filled as a starting point
		// — no custom question set has been saved for this category yet.
		$this->assertStringContainsString( 'value="How many people will sleep in it?"', $html );
		$this->assertStringNotContainsString( 'This category has its own custom questions', $html );
	}

	public function test_the_questions_section_reflects_a_saved_custom_question_set_instead_of_the_template(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$category_id = wp_insert_term( 'Backpacks', 'product_cat', array( 'slug' => 'backpacks-qs2' ) )['term_id'];
		$product     = new WC_Product_Simple();
		$product->set_name( 'Trail Pack' );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( $category_id ) );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', '30' ) ) );
		$product->save();

		ConfigRepository::save_questions(
			'backpacks-qs2',
			array(
				array(
					'key'        => 'capacity',
					'label'      => 'How many liters?',
					'shortLabel' => 'Capacity',
					'attribute'  => 'capacity',
					'ruleType'   => 'hard',
					'comparator' => 'gte',
					'valueType'  => 'int',
					'input'      => array(
						'type'    => 'select',
						'options' => array(),
					),
				),
			)
		);

		$_GET['category'] = 'backpacks-qs2';

		ob_start();
		SettingsPage::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="How many liters?"', $html );
		$this->assertStringNotContainsString( 'How many people will sleep in it?', $html );
		$this->assertStringContainsString( 'This category has its own custom questions', $html );
	}

	public function test_render_truncates_a_saved_question_set_with_more_rows_than_available_attributes(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$category_id = wp_insert_term( 'Backpacks', 'product_cat', array( 'slug' => 'backpacks-trunc' ) )['term_id'];
		$product     = new WC_Product_Simple();
		$product->set_name( 'Trail Pack' );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( $category_id ) );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', '30' ) ) );
		$product->save();

		// Simulates stale/hand-edited config data: 6 rows, one per finder
		// attribute plus a duplicate — sanitize_submitted_questions()'s own
		// dedup-by-attribute normally prevents this from ever being saved
		// via the real form, but build_question_rows() has to cope with it
		// regardless (e.g. data saved by a future version with more
		// attributes, then read by this one).
		$row = static fn( string $attribute, string $label ) => array(
			'key'        => $attribute,
			'attribute'  => $attribute,
			'label'      => $label,
			'shortLabel' => $label,
			'ruleType'   => 'soft',
			'comparator' => 'equals',
			'valueType'  => 'string',
			'weight'     => 1,
			'input'      => array(
				'type'    => 'select',
				'options' => array(),
			),
		);
		ConfigRepository::save_questions(
			'backpacks-trunc',
			array(
				$row( 'price', 'Q1' ),
				$row( 'capacity', 'Q2' ),
				$row( 'use_type', 'Q3' ),
				$row( 'season_rating', 'Q4' ),
				$row( 'packed_weight', 'Q5' ),
				$row( 'price', 'Q6 overflow' ),
			)
		);

		$_GET['category'] = 'backpacks-trunc';

		ob_start();
		SettingsPage::render();
		$html = ob_get_clean();

		// No fatal, and exactly 5 attribute-picker rows rendered — the 6th
		// is truncated, not appended as a 6th form row.
		$this->assertStringNotContainsString( 'Q6 overflow', $html );
		preg_match_all( '/name="questions\[(\d+)\]\[attribute\]"/', $html, $matches );
		$this->assertCount( 5, $matches[1] );
	}

	public function test_render_gracefully_handles_a_saved_question_referencing_an_unknown_attribute(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$category_id = wp_insert_term( 'Backpacks', 'product_cat', array( 'slug' => 'backpacks-unknown' ) )['term_id'];
		$product     = new WC_Product_Simple();
		$product->set_name( 'Trail Pack' );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( $category_id ) );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', '30' ) ) );
		$product->save();

		// Simulates a saved config referencing an attribute this plugin no
		// longer recognizes (e.g. after a future template change) —
		// sanitize_submitted_questions() prevents this at save time (see
		// test_sanitize_submitted_questions_drops_a_row_for_an_unknown_attribute),
		// but doesn't retroactively clean up data saved before such a
		// change. This is the distinct render-time path for the same
		// scenario.
		ConfigRepository::save_questions(
			'backpacks-unknown',
			array(
				array(
					'key'        => 'discontinued_attribute',
					'attribute'  => 'discontinued_attribute',
					'label'      => 'Stale question text',
					'shortLabel' => 'Stale',
					'ruleType'   => 'soft',
					'comparator' => 'equals',
					'valueType'  => 'string',
					'weight'     => 1,
					'input'      => array(
						'type'    => 'select',
						'options' => array(),
					),
				),
			)
		);

		$_GET['category'] = 'backpacks-unknown';

		ob_start();
		SettingsPage::render();
		$html = ob_get_clean();

		// No fatal — the form still renders to completion (the submit
		// button proves nothing was cut short) — and the stale row's own
		// data isn't silently discarded, even though its attribute picker
		// won't have a matching option to select.
		$this->assertStringContainsString( 'value="Stale question text"', $html );
		$this->assertStringContainsString( 'Save mapping and questions', $html );
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

	private const VALUE_TYPES = array(
		'price'    => 'float',
		'capacity' => 'int',
		'use_type' => 'string',
	);

	public function test_sanitize_submitted_questions_drops_a_row_with_no_attribute_selected(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => '', 'label' => 'Some question?' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( array(), $result );
	}

	public function test_sanitize_submitted_questions_drops_a_row_with_a_blank_label(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'capacity', 'label' => '' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( array(), $result );
	}

	public function test_sanitize_submitted_questions_drops_a_row_for_an_unknown_attribute(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'not_a_real_attribute', 'label' => 'Some question?' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( array(), $result );
	}

	public function test_sanitize_submitted_questions_keeps_only_the_first_row_for_a_repeated_attribute(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array(
				array( 'attribute' => 'capacity', 'label' => 'First?' ),
				array( 'attribute' => 'capacity', 'label' => 'Second?' ),
			),
			self::VALUE_TYPES
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 'First?', $result[0]['label'] );
	}

	public function test_sanitize_submitted_questions_omits_weight_for_a_hard_question(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'capacity', 'label' => 'How many?', 'ruleType' => 'hard', 'weight' => '5' ) ),
			self::VALUE_TYPES
		);

		$this->assertArrayNotHasKey( 'weight', $result[0] );
	}

	public function test_sanitize_submitted_questions_defaults_an_invalid_weight_to_one_for_a_soft_question(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'capacity', 'label' => 'How many?', 'ruleType' => 'soft', 'weight' => 'not-a-number' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( 1, $result[0]['weight'] );
	}

	public function test_sanitize_submitted_questions_coerces_a_string_attributes_comparator_to_equals(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'use_type', 'label' => 'Which type?', 'comparator' => 'gte' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( 'equals', $result[0]['comparator'] );
	}

	public function test_sanitize_submitted_questions_keeps_a_numeric_attributes_chosen_comparator(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'capacity', 'label' => 'How many?', 'comparator' => 'lte' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( 'lte', $result[0]['comparator'] );
	}

	public function test_sanitize_submitted_questions_falls_back_to_the_label_when_short_label_is_blank(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'capacity', 'label' => 'How many?', 'shortLabel' => '' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( 'How many?', $result[0]['shortLabel'] );
	}

	public function test_sanitize_submitted_questions_casts_a_float_attributes_toggle_threshold_to_float(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'price', 'label' => 'Under budget?', 'inputType' => 'toggle', 'toggleThreshold' => '199.99' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( array( 'type' => 'toggle', 'value' => 199.99 ), $result[0]['input'] );
	}

	public function test_sanitize_submitted_questions_casts_an_int_attributes_toggle_threshold_to_int_not_float(): void {
		// Regression test: MatchEngine's 'equals' comparator does a strict
		// PHP === comparison, and ProductArrayAdapter casts a product's
		// capacity to a real int — a float threshold (e.g. 4.0) could never
		// strictly-equal an int product value (4), the same `3 === 3.0`
		// class of bug this codebase already hit once with season_rating.
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'capacity', 'label' => 'Big enough?', 'inputType' => 'toggle', 'toggleThreshold' => '4' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( array( 'type' => 'toggle', 'value' => 4 ), $result[0]['input'] );
		$this->assertIsInt( $result[0]['input']['value'] );
	}

	public function test_sanitize_submitted_questions_leaves_select_options_empty_pending_discovery(): void {
		$result = SettingsPage::sanitize_submitted_questions(
			array( array( 'attribute' => 'capacity', 'label' => 'How many?' ) ),
			self::VALUE_TYPES
		);

		$this->assertSame( array( 'type' => 'select', 'options' => array() ), $result[0]['input'] );
	}

	public function test_questions_with_discovered_options_fills_in_real_values_for_a_mapped_attribute(): void {
		$category_id = wp_insert_term( 'Tents', 'product_cat', array( 'slug' => 'tents-qwdo' ) )['term_id'];
		$product     = new WC_Product_Simple();
		$product->set_name( 'A Tent' );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( $category_id ) );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', '4' ) ) );
		$product->save();

		$questions = array(
			array(
				'attribute' => 'capacity',
				'input'     => array(
					'type'    => 'select',
					'options' => array(),
				),
			),
		);

		$result = SettingsPage::questions_with_discovered_options(
			'tents-qwdo',
			array( 'capacity' => array( 'slug' => 'capacity', 'type' => 'int' ) ),
			$questions
		);

		$this->assertSame(
			array(
				array(
					'value' => '4',
					'label' => '4',
				),
			),
			$result[0]['input']['options']
		);
	}

	public function test_questions_with_discovered_options_falls_back_to_the_templates_price_breakpoints(): void {
		$questions = array(
			array(
				'attribute' => 'price',
				'input'     => array(
					'type'    => 'select',
					'options' => array(),
				),
			),
		);

		$result = SettingsPage::questions_with_discovered_options( 'tents', array(), $questions );

		$this->assertNotEmpty( $result[0]['input']['options'] );
		$this->assertContains(
			array(
				'value' => 200,
				'label' => '200',
			),
			$result[0]['input']['options']
		);
	}

	public function test_questions_with_discovered_options_leaves_a_toggle_question_untouched(): void {
		$questions = array(
			array(
				'attribute' => 'capacity',
				'input'     => array(
					'type'  => 'toggle',
					'value' => 5.0,
				),
			),
		);

		$result = SettingsPage::questions_with_discovered_options( 'tents', array(), $questions );

		$this->assertSame( array( 'type' => 'toggle', 'value' => 5.0 ), $result[0]['input'] );
	}
}
