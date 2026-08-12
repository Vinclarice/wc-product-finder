<?php

declare(strict_types=1);

namespace ProductFinder\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ProductFinder\Attributes\AttributeCompleteness;
use ProductFinder\Attributes\AttributeDiscovery;
use ProductFinder\Finder\AttributeMapResolver;
use ProductFinder\Finder\ConfigRepository;
use ProductFinder\Finder\EventCounter;
use ProductFinder\Finder\QuestionSetResolver;
use ProductFinder\Templates\TentsTemplate;

/**
 * The attribute-mapping + question-editor admin screen (build order step 7
 * / §5c/§6 for attribute mapping; §13's per-category question editor epic
 * for the "Questions" section). Classic server-rendered PHP form — no build
 * step, no REST endpoint — since the actual UI (a category picker and a
 * handful of dropdowns/radios) doesn't need live interactivity:
 * completeness for every discoverable attribute, and real discovered
 * answer choices for select-type questions, are both computed up front on
 * page load. The finder-attribute set itself (capacity/packed_weight/
 * season_rating/use_type/price) stays fixed — this screen lets a merchant
 * customize which of those are asked about and how, not invent new ones.
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

		$raw_map = array();
		if ( isset( $_POST['attribute_map'] ) && is_array( $_POST['attribute_map'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized field by field by sanitize_submitted_map() just below; the sniff can't follow sanitization into a helper.
			$raw_map = wp_unslash( $_POST['attribute_map'] );
		}
		$attribute_map = self::sanitize_submitted_map( $raw_map );
		ConfigRepository::save_attribute_map( $category, $attribute_map );

		$raw_questions = array();
		if ( isset( $_POST['questions'] ) && is_array( $_POST['questions'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized row by row by sanitize_submitted_questions() just below; same reason as above.
			$raw_questions = wp_unslash( $_POST['questions'] );
		}
		$questions = self::sanitize_submitted_questions( $raw_questions, self::attribute_value_types() );
		$effective_map = AttributeMapResolver::resolve( TentsTemplate::attribute_map(), $attribute_map );
		ConfigRepository::save_questions( $category, self::questions_with_discovered_options( $category, $effective_map, $questions ) );

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

	/**
	 * Validates and coerces the question editor's submitted rows (§13's
	 * per-category question editor, Phase 3) into question configs matching
	 * TentsTemplate::questions()' shape. A plain array in, plain array out —
	 * same reasoning as sanitize_submitted_map(). Deliberately doesn't fill
	 * in select-type input.options: that needs AttributeDiscovery (a
	 * WP/WC-touching lookup), done separately by
	 * questions_with_discovered_options().
	 *
	 * A row is dropped entirely (treated as "not used") if it has no
	 * attribute selected, no question text, references an attribute this
	 * plugin doesn't know about, or repeats an attribute an earlier row in
	 * the same submission already used (first occurrence wins).
	 *
	 * @param array<int, array<string, mixed>> $raw_rows
	 * @param array<string, string> $value_types Finder attribute => 'int'|'float'|'string'.
	 * @return array<int, array>
	 */
	public static function sanitize_submitted_questions( array $raw_rows, array $value_types ): array {
		$questions = array();
		$seen      = array();

		foreach ( $raw_rows as $row ) {
			$attribute = sanitize_key( (string) ( $row['attribute'] ?? '' ) );
			$label     = sanitize_text_field( trim( (string) ( $row['label'] ?? '' ) ) );

			if ( '' === $attribute || '' === $label || ! isset( $value_types[ $attribute ] ) || isset( $seen[ $attribute ] ) ) {
				continue;
			}
			$seen[ $attribute ] = true;

			$value_type = $value_types[ $attribute ];
			$rule_type  = 'hard' === ( $row['ruleType'] ?? '' ) ? 'hard' : 'soft';

			$comparator = in_array( $row['comparator'] ?? '', array( 'gte', 'lte', 'equals' ), true )
				? $row['comparator']
				: 'equals';
			// gte/lte on a string attribute would just be PHP's lexicographic
			// comparison — not meaningless, but never what a merchant setting
			// this up actually intends, so coerce rather than let it through.
			if ( 'string' === $value_type ) {
				$comparator = 'equals';
			}

			$short_label = sanitize_text_field( trim( (string) ( $row['shortLabel'] ?? '' ) ) );
			if ( '' === $short_label ) {
				$short_label = $label;
			}

			$question = array(
				'key'        => $attribute,
				'label'      => $label,
				'shortLabel' => $short_label,
				'attribute'  => $attribute,
				'ruleType'   => $rule_type,
				'comparator' => $comparator,
				'valueType'  => $value_type,
			);

			if ( 'soft' === $rule_type ) {
				$weight             = (int) ( $row['weight'] ?? 0 );
				$question['weight'] = $weight > 0 ? $weight : 1;
			}

			if ( 'toggle' === ( $row['inputType'] ?? '' ) ) {
				$threshold = $row['toggleThreshold'] ?? 0;
				// Cast by the attribute's own valueType, not always to
				// float: MatchEngine's 'equals' comparator does a strict
				// PHP === check, and 4 === 4.0 is false — an int-typed
				// attribute's threshold has to actually be an int, the same
				// `3 === 3.0` bug class already hit once with season_rating.
				$question['input'] = array(
					'type'  => 'toggle',
					'value' => 'int' === $value_type ? (int) $threshold : (float) $threshold,
				);
			} else {
				$question['input'] = array(
					'type'    => 'select',
					'options' => array(),
				);
			}

			$questions[] = $question;
		}

		return $questions;
	}

	/**
	 * Fills in select-type questions' input.options with the category's
	 * real, distinct attribute values (§13, Phase 2's
	 * AttributeDiscovery::distinct_values_for_attribute()) — separate from
	 * sanitize_submitted_questions() so that pure validation stays testable
	 * without WordPress/WooCommerce, matching this file's existing
	 * sanitize_submitted_map()/maybe_save() split.
	 *
	 * price is a special case: it isn't a mappable WooCommerce attribute
	 * (it's WC_Product's own native field), so there's nothing to discover
	 * values from — a select-type price question keeps the starter
	 * template's own round-number breakpoints (200/300/400/500/600) instead.
	 *
	 * @param array<string, array{slug: string, type: string}> $effective_attribute_map
	 */
	public static function questions_with_discovered_options( string $category_slug, array $effective_attribute_map, array $questions ): array {
		foreach ( $questions as &$question ) {
			if ( 'select' !== $question['input']['type'] ) {
				continue;
			}

			if ( 'price' === $question['attribute'] ) {
				$question['input']['options'] = self::price_template_options();
				continue;
			}

			$wc_slug = $effective_attribute_map[ $question['attribute'] ]['slug'] ?? null;
			$question['input']['options'] = $wc_slug
				? AttributeDiscovery::distinct_values_for_attribute( $category_slug, $wc_slug )
				: array();
		}
		unset( $question );

		return $questions;
	}

	/**
	 * Finder attribute => value type, for every attribute a question can be
	 * asked about — TentsTemplate::attribute_map()'s mapped attributes, plus
	 * price (WC_Product's native field, not part of that map).
	 */
	private static function attribute_value_types(): array {
		$types = array( 'price' => 'float' );
		foreach ( TentsTemplate::attribute_map() as $finder_attribute => $config ) {
			$types[ $finder_attribute ] = $config['type'];
		}
		return $types;
	}

	private static function price_template_options(): array {
		foreach ( TentsTemplate::questions() as $question ) {
			if ( 'price' === $question['attribute'] ) {
				return $question['input']['options'];
			}
		}
		return array();
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

		$selected_category = $categories[0]->slug ?? '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses which category this read-only screen displays; it saves nothing, and maybe_save() does verify a nonce before writing.
		if ( isset( $_GET['category'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same read-only selection as above.
			$selected_category = sanitize_title( wp_unslash( $_GET['category'] ) );
		}

		$selected_category_term = null;
		foreach ( $categories as $category ) {
			if ( $category->slug === $selected_category ) {
				$selected_category_term = $category;
				break;
			}
		}
		$selected_category_name = $selected_category_term ? $selected_category_term->name : $selected_category;

		$discovered   = $selected_category ? AttributeDiscovery::for_category( $selected_category ) : array();
		$completeness = array();
		if ( ! empty( $discovered ) ) {
			$raw_products = AttributeDiscovery::raw_values_for_category( $selected_category );
			$completeness = AttributeCompleteness::calculate( $raw_products, wp_list_pluck( $discovered, 'slug' ) );
		}

		$current_map  = ConfigRepository::get_attribute_map( $selected_category );
		$template_map = TentsTemplate::attribute_map();
		$usage_counts = $selected_category ? EventCounter::get_counts( $selected_category ) : array();

		$saved_questions      = $selected_category ? ConfigRepository::get_questions( $selected_category ) : array();
		$has_custom_questions = ! empty( $saved_questions );
		$current_questions    = QuestionSetResolver::resolve( TentsTemplate::questions(), $saved_questions )['questions'];
		// The preview shown on this screen (as opposed to what's actually
		// saved/live on the front end) always reflects live discovered
		// values, not whatever was frozen in at the last save — this is an
		// authenticated, low-traffic admin page, so recomputing on every
		// load is cheap, and it's what lets the screen's own copy ("Answer
		// choices are the real values found on this category's products")
		// actually be true before a merchant has saved anything yet.
		if ( ! empty( $discovered ) ) {
			$effective_map     = AttributeMapResolver::resolve( $template_map, $current_map );
			$current_questions = self::questions_with_discovered_options( $selected_category, $effective_map, $current_questions );
		}
		$question_rows    = self::build_question_rows( $current_questions );
		$attribute_labels = self::attribute_labels();

		require __DIR__ . '/settings-page.php';
	}

	/**
	 * Exactly one row per available finder attribute (§13's per-category
	 * question editor scope: customizing which of the existing attributes
	 * are asked about, not inventing new ones — see TentsTemplate's
	 * docblock) — padded with blank rows if the effective question set has
	 * fewer than that, so the form always shows every attribute a merchant
	 * could add a question for.
	 */
	private static function build_question_rows( array $current_questions ): array {
		$attribute_count = count( self::attribute_value_types() );
		return array_slice( array_pad( $current_questions, $attribute_count, null ), 0, $attribute_count );
	}

	/**
	 * Finder attribute => short display name, for the question editor's
	 * attribute picker — reuses TentsTemplate's own shortLabels rather than
	 * a second hardcoded set of names for the same attributes.
	 */
	private static function attribute_labels(): array {
		$labels = array();
		foreach ( TentsTemplate::questions() as $question ) {
			$labels[ $question['attribute'] ] = $question['shortLabel'];
		}
		return $labels;
	}
}
