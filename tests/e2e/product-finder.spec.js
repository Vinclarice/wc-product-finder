/**
 * Small, critical-path e2e suite for the Product Finder block, run against a
 * real wp-env WordPress + WooCommerce instance (§9 of
 * PRODUCT-FINDER-PROPOSAL.md: "a small number of critical-path scenarios...
 * rather than trying to unit-test markup").
 *
 * Covers exactly the scenarios the proposal calls out:
 *   1. answer questions -> see filtered results update, entirely client-side
 *      (no full page navigation - the Interactivity API updates local state,
 *      see src/product-finder/view.js)
 *   2. the zero-match fallback triggers and explains itself
 *   3. add to cart - relies entirely on WooCommerce's own wc-add-to-cart.js
 *      (enqueued site-wide whenever "AJAX add to cart" is enabled, which it
 *      is by default), bound via event delegation so it needs no glue code
 *      from this plugin even though results are re-rendered dynamically -
 *      see includes/Query/ProductArrayAdapter's addToCartUrl field and
 *      render.php's .ajax_add_to_cart markup.
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
 *     relaxed - see includes/Finder/QuestionSetResolver.php, which derives
 *     relaxation order from the effective question set's hard-type
 *     questions).
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

// Excludes the Add to cart link added alongside each result — without this,
// the selector matches both anchors per result item.
const resultNames = ( page ) =>
	page
		.locator( '.product-finder__result a:not(.add_to_cart_button)' )
		.allTextContents();

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

	test( 'clicking Add to cart on a result actually adds it to the WooCommerce cart', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		const firstResult = page.locator( '.product-finder__result' ).first();
		const productName = await firstResult
			.locator( 'a' )
			.first()
			.textContent();
		const addToCartButton = firstResult.getByRole( 'link', {
			name: 'Add to cart',
		} );

		// wc-add-to-cart.js adds the "added" class to the button only inside
		// its AJAX success callback (confirmed by reading add-to-cart.js
		// directly) - a real, WooCommerce-verified signal of a completed
		// server round trip, not just an optimistic local UI toggle.
		await addToCartButton.click();
		await expect( addToCartButton ).toHaveClass( /added/, {
			timeout: 20_000,
		} );

		// Cross-check against the cart's actual contents via WooCommerce's own
		// exposed cart_url, rather than guessing a page slug/id.
		const cartUrl = await page.evaluate(
			() => window.wc_add_to_cart_params?.cart_url
		);
		expect( cartUrl ).toBeTruthy();

		await page.goto( cartUrl );
		await expect( page.getByText( productName.trim() ) ).toBeVisible();
	} );

	test( 'each result shows an image, stock status, and key specs', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		// Default first result — 'Solo Skyline 1P Value' from
		// scripts/seed-tents.php: capacity 1, packed_weight 2.9,
		// season_rating 2, use_type 'Backpacking'.
		const firstResult = page.locator( '.product-finder__result' ).first();

		await expect( firstResult.locator( 'img' ) ).toHaveAttribute(
			'src',
			/.+/
		);
		await expect( firstResult.getByText( 'In stock' ) ).toBeVisible();

		const specTexts = await firstResult
			.locator( '.product-finder__specs li' )
			.allTextContents();
		expect(
			specTexts.map( ( t ) => t.replace( /\s+/g, ' ' ).trim() )
		).toEqual( [
			'Capacity: 1 people',
			'Use type: Backpacking',
			'Season rating: 2',
			'Packed weight: 2.9 lb',
		] );
	} );

	test( 'shows why results were relaxed when a hard filter had to be dropped to find anything', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		const relaxationMessage = page.locator(
			'.product-finder__relaxation-message'
		);
		await expect( relaxationMessage ).toBeHidden();

		// capacity >= 6 AND price <= 200: the cheapest 6-person tent
		// (Homestead 6P Weekender, $259) is still over budget, so price
		// has to relax before anything can survive.
		await page.getByLabel( CAPACITY_LABEL ).selectOption( '6' );
		await page.getByLabel( PRICE_LABEL ).selectOption( '200' );

		await expect( relaxationMessage ).toBeVisible();
		await expect( relaxationMessage ).toHaveText(
			'We relaxed your Budget preference to show you more options.'
		);
		await expect( resultNames( page ) ).resolves.not.toHaveLength( 0 );
	} );

	test( 'the reset button clears every answer and restores the default results, including the controls themselves', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		const capacitySelect = page.getByLabel( CAPACITY_LABEL );
		await capacitySelect.selectOption( '6' );

		await expect( resultNames( page ) ).resolves.toEqual( [
			'Homestead 6P Weekender',
			'Basecamp 6P Family',
			'Expedition 6P Pro',
		] );

		await page.getByRole( 'button', { name: 'Reset' } ).click();

		// The control itself visually resets — not just the results — proving
		// context.answers was cleared programmatically rather than via the
		// user picking "Any" themselves.
		await expect( capacitySelect ).toHaveValue( '' );
		await expect( resultNames( page ) ).resolves.toEqual( [
			'Solo Skyline 1P Value',
			'TrailLite 2P Value',
			'Solo Skyline 1P',
		] );
	} );
} );
