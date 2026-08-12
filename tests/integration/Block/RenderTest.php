<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Block;

use ProductFinder\Admin\SettingsPage;
use ProductFinder\Finder\ConfigRepository;
use ProductFinder\Finder\EventCounter;
use ProductFinder\Tests\Integration\Support\WooCommerceProductFactory;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Verifies render.php's wiring — not MatchEngine/RuleBuilder correctness
 * itself (already covered elsewhere), but that render.php actually reads
 * $_GET, builds rules from it via RuleBuilder, and reflects that in the
 * server-rendered output. This is the no-JS fallback's real behavior
 * (build order step 8).
 */
final class RenderTest extends WP_UnitTestCase {

	use WooCommerceProductFactory;

	private int $category_id;

	public function set_up(): void {
		parent::set_up();
		$this->category_id = wp_insert_term( 'Tents', 'product_cat' )['term_id'];
	}

	public function tear_down(): void {
		$_GET = array();
		parent::tear_down();
	}

	public function test_get_params_filter_results_via_the_hard_filter_rule(): void {
		$this->create_tent( 'Small Tent', 2 );
		$this->create_tent( 'Big Tent', 6 );

		$_GET['product_finder'] = array( 'tents' => array( 'capacity' => '4' ) );

		$html = $this->render_block();

		$this->assertStringContainsString( 'Big Tent', $this->results_markup( $html ) );
		$this->assertStringNotContainsString( 'Small Tent', $this->results_markup( $html ) );
	}

	public function test_no_get_params_falls_back_to_the_default_unfiltered_view(): void {
		$this->create_tent( 'Small Tent', 2 );
		$this->create_tent( 'Big Tent', 6 );

		$html = $this->render_block();

		$this->assertStringContainsString( 'Big Tent', $html );
		$this->assertStringContainsString( 'Small Tent', $html );
	}

	public function test_answered_select_options_are_marked_selected(): void {
		$this->create_tent( 'Big Tent', 6 );

		$_GET['product_finder'] = array( 'tents' => array( 'capacity' => '4' ) );

		$html = $this->render_block();

		$this->assertMatchesRegularExpression(
			'/<option\s+value="4"\s+selected=[\'"]selected[\'"]/',
			$html
		);
	}

	public function test_a_non_string_product_category_attribute_does_not_fatal(): void {
		// A hand-edited block (raw markup in the Code Editor) could set
		// productCategory to a non-string value. Verified this is already
		// safe: WP_Block_Type::prepare_attributes_for_render() validates
		// every attribute against block.json's declared schema before
		// render.php ever runs, and an invalid value gets unset and
		// repopulated from the schema's "default" ("tents") — contingent on
		// block.json declaring both "type": "string" and a "default", which
		// it does. Kept as a regression guard for that property, not because
		// render.php itself does anything defensive here.
		$html = $this->render_block( array( 'productCategory' => array( 'not', 'a', 'string' ) ) );

		$this->assertIsString( $html );
	}

	public function test_every_render_counts_as_a_view(): void {
		$this->create_tent( 'Big Tent', 6 );

		$this->render_block();
		$this->render_block();

		$this->assertSame( 2, EventCounter::get_counts( 'tents' )['view'] );
	}

	public function test_a_render_with_no_matching_products_counts_as_a_zero_match(): void {
		// No tents created at all: fallback relaxation (relaxation_order
		// covers both capacity and price) always finds *something* once every
		// hard filter is relaxed — the only genuine zero-match is an empty
		// category, nothing left to relax into.

		$this->render_block();

		$counts = EventCounter::get_counts( 'tents' );
		$this->assertSame( 1, $counts['view'] );
		$this->assertSame( 1, $counts['zero_match'] );
	}

	public function test_a_render_with_matching_products_does_not_count_as_a_zero_match(): void {
		$this->create_tent( 'Big Tent', 6 );

		$this->render_block();

		$this->assertSame( 0, EventCounter::get_counts( 'tents' )['zero_match'] );
	}

	public function test_heading_reflects_the_selected_categorys_real_name(): void {
		// set_up() creates the 'tents' category with the display name "Tents".
		$html = $this->render_block();

		$this->assertStringContainsString( 'Find your Tents match', $html );
		$this->assertStringNotContainsString( 'Find your tent<', $html );
	}

	public function test_heading_adapts_to_a_different_real_category(): void {
		wp_insert_term( 'Backpacks', 'product_cat', array( 'slug' => 'backpacks' ) );

		$html = $this->render_block( array( 'productCategory' => 'backpacks' ) );

		$this->assertStringContainsString( 'Find your Backpacks match', $html );
	}

	public function test_heading_falls_back_to_a_generic_message_for_an_unknown_category(): void {
		$html = $this->render_block( array( 'productCategory' => 'does-not-exist' ) );

		$this->assertStringContainsString( 'Find your perfect match', $html );
	}

	public function test_a_saved_custom_question_set_replaces_the_templates_questions(): void {
		// Saved directly rather than via the admin screen (includes/Admin/
		// SettingsPage.php) — this proves QuestionSetResolver's wiring into
		// render.php works in isolation from that screen's own form
		// handling, which has its own dedicated tests.
		ConfigRepository::save_questions(
			'tents',
			array(
				array(
					'key'        => 'season_rating',
					'label'      => 'Custom season question?',
					'shortLabel' => 'Season',
					'attribute'  => 'season_rating',
					'ruleType'   => 'soft',
					'comparator' => 'equals',
					'valueType'  => 'int',
					'weight'     => 1,
					'input'      => array(
						'type'    => 'select',
						'options' => array(
							array(
								'value' => 3,
								'label' => '3-season',
							),
						),
					),
				),
			)
		);

		$html = $this->render_block();

		$this->assertStringContainsString( 'Custom season question?', $html );
		$this->assertStringNotContainsString( 'How many people will sleep in it?', $html );
	}

	public function test_relaxation_order_is_derived_from_the_custom_question_sets_hard_questions(): void {
		$this->create_tent( 'Only Tent', 4 );

		ConfigRepository::save_questions(
			'tents',
			array(
				array(
					'key'        => 'capacity',
					'label'      => 'Capacity?',
					'shortLabel' => 'Capacity',
					'attribute'  => 'capacity',
					'ruleType'   => 'hard',
					'comparator' => 'gte',
					'valueType'  => 'int',
					'input'      => array(
						'type'    => 'select',
						'options' => array(
							array(
								'value' => 99,
								'label' => '99',
							),
						),
					),
				),
			)
		);

		$_GET['product_finder'] = array( 'tents' => array( 'capacity' => '99' ) );

		$html = $this->render_block();

		// An impossible capacity (99) forces relaxation of the custom
		// question set's own single hard filter — not TentsTemplate's
		// [price, capacity], which this category no longer uses at all.
		$this->assertStringContainsString( 'Only Tent', $this->results_markup( $html ) );
	}

	public function test_a_toggle_question_with_no_get_answer_is_not_treated_as_active_on_the_server(): void {
		$this->create_tent( 'Big Tent', 6 );
		$this->create_tent( 'Small Tent', 2 );

		ConfigRepository::save_questions(
			'tents',
			array(
				array(
					'key'        => 'capacity',
					'label'      => 'Capacity toggle?',
					'shortLabel' => 'Capacity',
					'attribute'  => 'capacity',
					'ruleType'   => 'hard',
					'comparator' => 'gte',
					'valueType'  => 'int',
					'input'      => array(
						'type'  => 'toggle',
						'value' => 4,
					),
				),
			)
		);

		// No $_GET at all — the toggle was never checked. This exercises the
		// server-side "state.results" derived-state closure (built from the
		// data-wp-each--result="state.results.products" markup, via real
		// directive processing), not the imperative $result used only for
		// the EventCounter check — that's the code path RuleBuilder's own
		// docblock says needs $_GET-shaped answers (toggle key absent when
		// unanswered), which this test's fixture setup deliberately leaves
		// untouched.
		$html = $this->render_block();

		// If the toggle were incorrectly treated as always-on, the
		// capacity>=4 hard filter would exclude Small Tent.
		$this->assertStringContainsString( 'Big Tent', $this->results_markup( $html ) );
		$this->assertStringContainsString( 'Small Tent', $this->results_markup( $html ) );
	}

	public function test_an_equals_toggle_question_on_an_int_attribute_matches_the_checked_answer(): void {
		$this->create_tent( 'Exact Match Tent', 4 );
		$this->create_tent( 'Different Tent', 6 );

		// Routed through the real admin-screen sanitizer, not a
		// hand-constructed config — this is what actually catches a
		// wrong-type cast in sanitize_submitted_questions() itself, not
		// just what a test could construct around it.
		$sanitized = SettingsPage::sanitize_submitted_questions(
			array(
				array(
					'attribute'       => 'capacity',
					'label'           => 'Exactly 4?',
					'ruleType'        => 'hard',
					'comparator'      => 'equals',
					'inputType'       => 'toggle',
					'toggleThreshold' => '4',
				),
			),
			array( 'capacity' => 'int' )
		);
		ConfigRepository::save_questions( 'tents', $sanitized );

		// A no-JS checkbox submission reports presence, not a boolean value.
		$_GET['product_finder'] = array( 'tents' => array( 'capacity' => 'on' ) );

		$html = $this->render_block();

		// If the threshold were cast to (float) 4.0 instead of (int) 4 (as
		// it was before this fix), this 'equals' hard filter could never
		// strictly-match any product's int-cast capacity — 4 === 4.0 is
		// false in PHP.
		$this->assertStringContainsString( 'Exact Match Tent', $this->results_markup( $html ) );
		$this->assertStringNotContainsString( 'Different Tent', $this->results_markup( $html ) );
	}

	public function test_two_block_instances_on_one_page_do_not_leak_each_others_products(): void {
		// Two tents (index 0 and 1) so the second instance's state, being a
		// shorter list (one backpack), can't accidentally overwrite cleanly —
		// this is what actually exposes wp_interactivity_state()'s
		// array_replace_recursive merge behavior: a shorter list overlaid on
		// a longer one replaces index 0 but leaves index 1's product
		// (Small Tent) sitting in the merged state under the wrong category.
		$this->create_tent( 'Big Tent', 6 );
		$this->create_tent( 'Small Tent', 2 );

		$backpacks_category_id = wp_insert_term( 'Backpacks', 'product_cat' )['term_id'];
		$backpack              = new WC_Product_Simple();
		$backpack->set_name( 'Trail Pack' );
		$backpack->set_status( 'publish' );
		$backpack->set_category_ids( array( $backpacks_category_id ) );
		$backpack->save();

		// Rendering the tents instance first is what seeds the shared
		// namespace state that the backpacks instance's render can then
		// collide with.
		$this->render_block();
		$backpacks_html = $this->render_block( array( 'productCategory' => 'backpacks' ) );

		$this->assertStringContainsString( 'Trail Pack', $backpacks_html );
		$this->assertStringNotContainsString( 'Big Tent', $backpacks_html );
		$this->assertStringNotContainsString( 'Small Tent', $backpacks_html );
	}

	/**
	 * Nothing stops a visitor hand-editing the query string into a shape the
	 * form itself never produces: `?product_finder[tents][capacity][]=4`
	 * makes the answer an array where a string is expected. Casting that to
	 * a string raises "Array to string conversion" on a public front-end
	 * page, so an array-valued answer is dropped and treated as unanswered
	 * instead.
	 */
	public function test_an_array_valued_get_answer_is_ignored_rather_than_coerced(): void {
		$this->create_tent( 'Small Tent', 2 );
		$this->create_tent( 'Big Tent', 6 );

		$_GET['product_finder'] = array( 'tents' => array( 'capacity' => array( '4' ) ) );

		$html = $this->render_block();

		// Unanswered, so neither tent is filtered out.
		$this->assertStringContainsString( 'Big Tent', $this->results_markup( $html ) );
		$this->assertStringContainsString( 'Small Tent', $this->results_markup( $html ) );
	}

	/**
	 * The visible, server-rendered results list only — excludes the root
	 * element's data-wp-context attribute, which (by design, since the
	 * multi-instance fix) carries every candidate product in the category
	 * so the client can recompute results locally as answers change, not
	 * just whichever ones currently pass the hard filters. Asserting
	 * absence against the whole $html would find a filtered-out product's
	 * name sitting harmlessly in that JSON, even though it's correctly
	 * missing from the actual rendered results.
	 *
	 * Uses the *last* </ul> in the document, not the first: each result now
	 * nests its own <ul class="product-finder__specs">, so the first </ul>
	 * after the opening tag would close that inner list instead of the
	 * outer results one.
	 */
	private function results_markup( string $html ): string {
		$start = strpos( $html, '<ul class="product-finder__results">' );
		$end   = strrpos( $html, '</ul>' );
		return substr( $html, $start, $end - $start );
	}

	private function render_block( array $attrs = array() ): string {
		return render_block(
			array(
				'blockName'    => 'product-finder/product-finder',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	private function create_tent( string $name, int $capacity ): void {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_category_ids( array( $this->category_id ) );
		$product->set_attributes( array( self::make_local_attribute( 'Capacity', (string) $capacity ) ) );
		$product->save();
	}
}
