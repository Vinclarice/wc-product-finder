<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Block;

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

		$this->assertStringContainsString( 'Big Tent', $html );
		$this->assertStringNotContainsString( 'Small Tent', $html );
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

	private function render_block(): string {
		return render_block(
			array(
				'blockName'    => 'product-finder/product-finder',
				'attrs'        => array(),
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
