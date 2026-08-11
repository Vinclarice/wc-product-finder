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
					'label' => 'Capacity',
					'value' => '4 people',
				),
				array(
					'label' => 'Use type',
					'value' => 'Backpacking',
				),
				array(
					'label' => 'Season rating',
					'value' => '3',
				),
				array(
					'label' => 'Packed weight',
					'value' => '3.2 lb',
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
					'label' => 'Capacity',
					'value' => '4 people',
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
					'label' => 'Season',
					'value' => '3',
				),
			),
			$specs
		);
	}
}
