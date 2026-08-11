/**
 * Small, critical-path e2e suite for the Product Finder block, run against a
 * real wp-env WordPress + WooCommerce instance (§9 of
 * PRODUCT-FINDER-PROPOSAL.md: "a small number of critical-path scenarios...
 * rather than trying to unit-test markup").
 *
 * Covers exactly the two scenarios the proposal calls out:
 *   1. answer questions -> see filtered results update, entirely client-side
 *      (no full page navigation - the Interactivity API updates local state,
 *      see src/product-finder/view.js)
 *   2. the zero-match fallback triggers and explains itself
 *
 * Fixture data this suite depends on:
 *   - The "Find Your Tent" page (post ID 34, slug /find-your-tent/), seeded
 *     by the project setup, with a Product Finder block over the "Tents"
 *     category.
 *   - 24 seeded tent products (`npm run seed`) - exact names/prices below
 *     are taken directly from scripts/seed-tents.php's fixture list.
 *   - The "E2E Empty Category" page (slug /e2e-empty-category/), created by
 *     this suite's own global-setup.js (scripts/seed-e2e-pages.php) so the
 *     zero-match test doesn't depend on constructing a relaxation-defeating
 *     combination from the tent data (relaxationOrder currently relaxes
 *     price then capacity, and always finds *something* once both are
 *     relaxed - see includes/Templates/TentsTemplate::relaxation_order()).
 *
 * Timing note: `expect(...).toHaveText(...)` etc. rely on Playwright's
 * built-in auto-waiting/retrying, not manual waits - the Interactivity API
 * update itself is synchronous local state, no network round trip per
 * answer. The generous config-level timeouts (playwright.config.js) are
 * there for this sandbox's slow Docker port-forwarding to wp-env, not
 * because the app itself is slow.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const FINDER_PAGE = '/find-your-tent/';
const EMPTY_CATEGORY_PAGE = '/e2e-empty-category/';

const CAPACITY_LABEL = 'How many people will sleep in it?';
const PRICE_LABEL = "What's your budget?";

const resultNames = ( page ) =>
	page.locator( '.product-finder__result a' ).allTextContents();

test.describe( 'Product Finder - critical path', () => {
	test( 'answering a hard-filter question updates results in place, without a full page reload', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		// Default (no answers): the 3 cheapest tents overall.
		await expect( resultNames( page ) ).resolves.toEqual( [
			'Solo Skyline 1P Value',
			'TrailLite 2P Value',
			'Solo Skyline 1P',
		] );
		await expect(
			page.getByText( 'No products found for this category yet.' )
		).toBeHidden();

		// A marker on `window` survives a client-side state update but is
		// wiped by any full navigation/reload - proves the Interactivity API
		// handled this rather than a form submission round-tripping the server.
		await page.evaluate( () => {
			window.__e2eNoReloadMarker = 'still-here';
		} );

		await page
			.getByLabel( CAPACITY_LABEL )
			.selectOption( '6' );

		// Hard filter (capacity >= 6): only the three 6-person tents qualify,
		// sorted by price ascending (the default tiebreaker).
		await expect( resultNames( page ) ).resolves.toEqual( [
			'Homestead 6P Weekender',
			'Basecamp 6P Family',
			'Expedition 6P Pro',
		] );

		expect( await page.evaluate( () => window.__e2eNoReloadMarker ) ).toBe(
			'still-here'
		);
	} );

	test( 'combining two hard-filter answers narrows to exactly the tents matching both', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		await page.getByLabel( CAPACITY_LABEL ).selectOption( '4' );
		await page.getByLabel( PRICE_LABEL ).selectOption( '300' );

		// capacity >= 4 AND price <= 300 leaves exactly three seeded tents:
		// Homestead 4P Weekender ($199), Homestead 6P Weekender ($259),
		// Basecamp 4P Cabin ($279) - sorted ascending by price.
		await expect( resultNames( page ) ).resolves.toEqual( [
			'Homestead 4P Weekender',
			'Homestead 6P Weekender',
			'Basecamp 4P Cabin',
		] );
	} );

	test( 'shows the zero-match fallback message, and only that, for a category with no products', async ( {
		page,
	} ) => {
		await page.goto( EMPTY_CATEGORY_PAGE );

		await expect(
			page.getByText( 'No products found for this category yet.' )
		).toBeVisible();
		await expect( page.locator( '.product-finder__result' ) ).toHaveCount(
			0
		);
	} );
} );
