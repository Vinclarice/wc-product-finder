/**
 * Regression coverage for the multi-instance Interactivity API state
 * collision: two Product Finder blocks on one page share the
 * 'product-finder' namespace, so if either instance's data lived in shared
 * `state` (as it originally did) rather than a per-instance
 * `data-wp-context`, answering a question in one block would corrupt or
 * leak into the other's results after client-side hydration takes over.
 *
 * The PHP-side counterpart of this bug (server-rendered markup, before any
 * JS runs) is covered by
 * RenderTest::test_two_block_instances_on_one_page_do_not_leak_each_others_products
 * - that test can reproduce the corruption via render_block() alone,
 * without a browser. This suite covers what that one can't: the *client*,
 * post-hydration, after a shopper actually interacts with one instance.
 *
 * Fixture: /e2e-multiple-instances/ (scripts/seed-e2e-pages.php), a Tents
 * finder followed by an Empty Category finder - reusing the existing tent
 * seed data and the same empty-category fixture product-finder.spec.js's
 * zero-match test depends on, rather than seeding new product data.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const MULTI_INSTANCE_PAGE = '/e2e-multiple-instances/';
const CAPACITY_LABEL = 'How many people will sleep in it?';
const NO_PRODUCTS_TEXT = 'No products found for this category yet.';

test.describe( 'Product Finder - multiple instances on one page', () => {
	test( 'answering a question in one instance does not affect a second instance in the same namespace', async ( {
		page,
	} ) => {
		await page.goto( MULTI_INSTANCE_PAGE );

		const instances = page.locator(
			'.wp-block-product-finder-product-finder'
		);
		await expect( instances ).toHaveCount( 2 );
		const tentsInstance = instances.nth( 0 );
		const emptyCategoryInstance = instances.nth( 1 );

		// Sanity check the fixture itself before testing isolation: the two
		// instances must actually start in different states, or a bug that
		// merges them wouldn't be visible either.
		await expect(
			tentsInstance.locator( '.product-finder__result' ).first()
		).toBeVisible();
		await expect(
			emptyCategoryInstance.getByText( NO_PRODUCTS_TEXT )
		).toBeVisible();
		await expect(
			emptyCategoryInstance.locator( '.product-finder__result' )
		).toHaveCount( 0 );

		await tentsInstance.getByLabel( CAPACITY_LABEL ).selectOption( '6' );

		// The tents instance actually reacted to its own answer (otherwise
		// this test wouldn't be exercising the interaction it claims to).
		await expect(
			tentsInstance.locator( '.product-finder__result' )
		).toHaveCount( 3 );

		// The empty-category instance is unaffected: still empty, still
		// showing its own fallback message, and - the corruption this bug
		// actually produces - has not picked up any of the tents instance's
		// products.
		await expect(
			emptyCategoryInstance.getByText( NO_PRODUCTS_TEXT )
		).toBeVisible();
		await expect(
			emptyCategoryInstance.locator( '.product-finder__result' )
		).toHaveCount( 0 );
	} );
} );
