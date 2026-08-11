<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Templates;

use ProductFinder\Templates\TentsTemplate;
use WP_UnitTestCase;

/**
 * format_specs() is pure input->output logic, but lives here rather than in
 * tests/php/ (the no-WP-bootstrap tier) because questions() (its usual
 * caller) uses WordPress's __() for every label — untestable without WP
 * loaded. format_specs() itself takes the question set as a parameter
 * (rather than calling questions() internally) so it works for a merchant's
 * saved custom question set too, not just TentsTemplate's own — see
 * QuestionSetResolver.
 */
final class TentsTemplateTest extends WP_UnitTestCase {

	public function test_format_specs_returns_a_short_labelled_pair_per_mapped_attribute_excluding_price(): void {
		$product = array(
			'capacity'      => 4,
			'use_type'      => 'backpacking',
			'season_rating' => 3,
			'packed_weight' => 3.2,
			'price'         => 249.0,
		);

		$specs = TentsTemplate::format_specs( $product, TentsTemplate::questions() );

		// price is deliberately absent — it's already shown separately as
		// the result card's price/priceLabel, not as a "spec".
		$this->assertSame(
			array(
				array(
					'attribute' => 'capacity',
					'label'     => 'Capacity',
					'value'     => '4 people',
				),
				array(
					'attribute' => 'use_type',
					'label'     => 'Use type',
					'value'     => 'Backpacking',
				),
				array(
					'attribute' => 'season_rating',
					'label'     => 'Season rating',
					'value'     => '3',
				),
				array(
					'attribute' => 'packed_weight',
					'label'     => 'Packed weight',
					'value'     => '3.2 lb',
				),
			),
			$specs
		);
	}

	public function test_format_specs_skips_attributes_missing_from_the_product(): void {
		$specs = TentsTemplate::format_specs( array( 'capacity' => 4 ), TentsTemplate::questions() );

		$this->assertSame(
			array(
				array(
					'attribute' => 'capacity',
					'label'     => 'Capacity',
					'value'     => '4 people',
				),
			),
			$specs
		);
	}

	public function test_format_specs_uses_a_custom_question_sets_short_labels_and_attributes(): void {
		$custom_questions = array(
			array(
				'attribute'  => 'season_rating',
				'shortLabel' => 'Season',
			),
		);

		$specs = TentsTemplate::format_specs(
			array(
				'season_rating' => 3,
				// Not referenced by the custom question set, so must not
				// appear even though TentsTemplate's own default set would
				// have included it.
				'capacity'      => 4,
			),
			$custom_questions
		);

		$this->assertSame(
			array(
				array(
					'attribute' => 'season_rating',
					'label'     => 'Season',
					'value'     => '3',
				),
			),
			$specs
		);
	}

	public function test_format_specs_gives_each_spec_a_stable_key_independent_of_merchant_editable_labels(): void {
		// Regression test: render.php's specs each-loop uses `attribute`,
		// not `label`, as its data-wp-each-key — two questions sharing the
		// same merchant-typed short label must not collide.
		$custom_questions = array(
			array(
				'attribute'  => 'capacity',
				'shortLabel' => 'Info',
			),
			array(
				'attribute'  => 'season_rating',
				'shortLabel' => 'Info',
			),
		);

		$specs = TentsTemplate::format_specs(
			array(
				'capacity'      => 4,
				'season_rating' => 3,
			),
			$custom_questions
		);

		$this->assertSame(
			array( 'capacity', 'season_rating' ),
			array_column( $specs, 'attribute' )
		);
	}
}
