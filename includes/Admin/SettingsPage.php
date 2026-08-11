<?php

declare(strict_types=1);

namespace ProductFinder\Admin;

use ProductFinder\Attributes\AttributeCompleteness;
use ProductFinder\Attributes\AttributeDiscovery;
use ProductFinder\Finder\ConfigRepository;
use ProductFinder\Finder\EventCounter;
use ProductFinder\Templates\TentsTemplate;

/**
 * The attribute-mapping admin screen (build order step 7 / §5c/§6). Classic
 * server-rendered PHP form — no build step, no REST endpoint — since the
 * actual UI (a category picker and a handful of dropdowns) doesn't need
 * live interactivity: completeness for every discoverable attribute is
 * computed up front on page load. Scoped to attribute mapping only; see
 * PRODUCT-FINDER-PROPOSAL.md §13 for what's deliberately deferred (question
 * customization, hard/soft editing).
 */
final class SettingsPage {

	private const CAPABILITY   = 'manage_woocommerce';
	private const PAGE_SLUG    = 'product-finder-settings';
	private const NONCE_ACTION = 'product_finder_save_mapping';

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_menu_page' ) );
		add_action( 'admin_init', array( self::class, 'maybe_save' ) );
	}

	public static function add_menu_page(): void {
		add_submenu_page(
			'woocommerce',
			__( 'Product Finder', 'product-finder' ),
			__( 'Product Finder', 'product-finder' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( self::class, 'render' )
		);
	}

	public static function maybe_save(): void {
		if ( ! isset( $_POST['product_finder_save_mapping'] ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		check_admin_referer( self::NONCE_ACTION );

		$category = isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '';
		if ( '' === $category ) {
			return;
		}

		$raw_map = ( isset( $_POST['attribute_map'] ) && is_array( $_POST['attribute_map'] ) )
			? wp_unslash( $_POST['attribute_map'] )
			: array();

		ConfigRepository::save_attribute_map( $category, self::sanitize_submitted_map( $raw_map ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => self::PAGE_SLUG,
					'category' => $category,
					'updated'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Drops blank selections (so that finder attribute falls back to the
	 * template default) and sanitizes what's left. A plain array in, plain
	 * array out — kept separate from maybe_save() so it's testable without
	 * simulating $_POST/nonce/capability plumbing.
	 */
	public static function sanitize_submitted_map( array $raw_map ): array {
		$map = array();
		foreach ( $raw_map as $finder_attribute => $wc_slug ) {
			$value = sanitize_text_field( trim( (string) $wc_slug ) );
			if ( '' !== $value ) {
				$map[ sanitize_key( (string) $finder_attribute ) ] = $value;
			}
		}
		return $map;
	}

	public static function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'product-finder' ) );
		}

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}

		$selected_category = isset( $_GET['category'] )
			? sanitize_title( wp_unslash( $_GET['category'] ) )
			: ( $categories[0]->slug ?? '' );

		$discovered   = $selected_category ? AttributeDiscovery::for_category( $selected_category ) : array();
		$completeness = array();
		if ( ! empty( $discovered ) ) {
			$raw_products = AttributeDiscovery::raw_values_for_category( $selected_category );
			$completeness = AttributeCompleteness::calculate( $raw_products, wp_list_pluck( $discovered, 'slug' ) );
		}

		$current_map  = ConfigRepository::get_attribute_map( $selected_category );
		$template_map = TentsTemplate::attribute_map();
		$usage_counts = $selected_category ? EventCounter::get_counts( $selected_category ) : array();

		require __DIR__ . '/settings-page.php';
	}
}
