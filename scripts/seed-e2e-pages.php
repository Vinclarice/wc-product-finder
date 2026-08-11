<?php
/**
 * Creates (idempotently) the WordPress pages the Playwright e2e suite
 * (tests/e2e/) navigates to. Run via WP-CLI inside the wp-env container,
 * from the Playwright global setup (tests/e2e/global-setup.js):
 *
 *   wp eval-file wp-content/plugins/product-finder/scripts/seed-e2e-pages.php
 *
 * Kept as a `wp eval-file` script rather than a `wp post create` shell
 * command (contrast scripts/seed-tents.php) specifically so the Product
 * Finder block's JSON attributes can be written as a real PHP array and
 * serialized correctly, instead of fighting shell-quoting differences
 * between the sandbox's bash and a plain Windows child_process shell.
 *
 * Idempotent: matched by post_name, updated in place rather than
 * duplicated, same as seed-tents.php's SKU matching.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This script must be run via `wp eval-file`, not directly.\n" );
	exit( 1 );
}

/**
 * @param string $slug
 * @param string $title
 * @param string[] $block_comments One or more `<!-- wp:product-finder/... -->` comments, in page order.
 */
function product_finder_seed_e2e_page_with_blocks( string $slug, string $title, array $block_comments ): void {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );

	$post_args = array(
		'post_type'    => 'page',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_status'  => 'publish',
		'post_content' => implode( "\n\n", $block_comments ),
	);

	if ( $existing ) {
		$post_args['ID'] = $existing->ID;
		wp_update_post( $post_args );
		echo "Updated page '{$slug}' (ID {$existing->ID}).\n";
		return;
	}

	$new_id = wp_insert_post( $post_args, true );
	if ( is_wp_error( $new_id ) ) {
		fwrite( STDERR, "Failed to create page '{$slug}': {$new_id->get_error_message()}\n" );
		exit( 1 );
	}
	echo "Created page '{$slug}' (ID {$new_id}).\n";
}

/**
 * @param string $slug
 * @param string $title
 * @param array<string, mixed> $block_attributes
 */
function product_finder_seed_e2e_page( string $slug, string $title, array $block_attributes ): void {
	product_finder_seed_e2e_page_with_blocks(
		$slug,
		$title,
		array( sprintf( '<!-- wp:product-finder/product-finder %s /-->', wp_json_encode( $block_attributes ) ) )
	);
}

// Points the Product Finder block at a category slug with zero products, so
// the zero-match fallback ("No products found for this category yet.") has
// a deterministic page to render on, independent of the tent seed data or
// any relaxation logic (see includes/Finder/QuestionSetResolver.php, which
// derives relaxation order from the effective question set's hard-type
// questions).
product_finder_seed_e2e_page(
	'e2e-empty-category',
	'E2E Empty Category',
	array( 'productCategory' => 'empty-category' )
);

// Two instances on one page, sharing the 'product-finder' Interactivity API
// namespace - the real-world shape of the multi-instance state collision bug
// (§5b of PRODUCT-FINDER-PROPOSAL.md advertises multiple finder instances).
// Reuses the tents/empty-category fixtures above rather than seeding new
// product data: if either instance's client-side state leaked into the
// other's, the empty-category instance would start showing tent products or
// its "no products" message would incorrectly disappear.
product_finder_seed_e2e_page_with_blocks(
	'e2e-multiple-instances',
	'E2E Multiple Instances',
	array(
		sprintf( '<!-- wp:product-finder/product-finder %s /-->', wp_json_encode( array( 'productCategory' => 'tents' ) ) ),
		sprintf( '<!-- wp:product-finder/product-finder %s /-->', wp_json_encode( array( 'productCategory' => 'empty-category' ) ) ),
	)
);
