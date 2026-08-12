/**
 * The no-JavaScript fallback (build order step 8), which readme.txt
 * advertises as a headline feature: "Fully functional with JavaScript
 * disabled: a real <form method="get"> submits to the same page and the
 * server renders the same results."
 *
 * That claim had no automated coverage at all until this file — every other
 * e2e spec runs with JavaScript on, which exercises the Interactivity API
 * path and never touches the <noscript> branch or the server's own
 * $_GET -> RuleBuilder -> MatchEngine rendering. It's also the exact code
 * path that once shipped a fatal error (render.php calling the admin-only
 * submit_button()), so it's worth holding down.
 *
 * Playwright's `javaScriptEnabled: false` is a real browser-level switch,
 * not a simulation: the page's own scripts never run, and the browser
 * parses <noscript> content into the DOM the way it would for a visitor
 * with scripting off. Note that page.evaluate() is unavailable under it, so
 * everything here reads the DOM through locators rather than in-page JS.
 */
const { test, expect } = require( '@playwright/test' );

const FINDER_PAGE = '/find-your-tent/';
const CAPACITY_LABEL = 'How many people will sleep in it?';

// Everything is scoped to the block: the rest of the suite runs as a
// logged-in admin, and the admin bar carries its own submit-bearing search
// form that a bare `form input[type=submit]` also matches.
const FINDER = '[data-wp-interactive="product-finder"]';

// Seeded by scripts/seed-tents.php; the block renders at most 3 results.
const RESULT_LIMIT = 3;

/**
 * The capacity shown on each rendered result card, as numbers. Read from
 * the visible spec list rather than the embedded state, so this asserts
 * what a scriptless visitor actually sees.
 *
 * @param {import('@playwright/test').Page} page The page under test.
 * @return {Promise<number[]>} One capacity per rendered result.
 */
const renderedCapacities = async ( page ) => {
	const specs = await page
		.locator( '.product-finder__result .product-finder__specs li' )
		.allTextContents();

	return specs
		.map( ( text ) => text.replace( /\s+/g, ' ' ).trim() )
		.filter( ( text ) => text.startsWith( 'Capacity:' ) )
		.map( ( text ) => parseInt( text.replace( /\D+/g, '' ), 10 ) );
};

test.describe( 'Product Finder - without JavaScript', () => {
	// A scriptless shopper is also an ordinary logged-out visitor; starting
	// from a blank storage state keeps the admin bar out of the page.
	test.use( {
		javaScriptEnabled: false,
		storageState: { cookies: [], origins: [] },
	} );

	test( 'server-renders a full result set on first load', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		await expect( page.locator( '.product-finder__result' ) ).toHaveCount(
			RESULT_LIMIT
		);
		// The questions are still real form controls, not JS-driven widgets.
		await expect( page.getByLabel( CAPACITY_LABEL ) ).toBeVisible();
	} );

	test( 'exposes a submit button that only scriptless visitors ever see', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		// Inside <noscript>, so this is present precisely because scripting
		// is off — with JS on, view.js updates results on change instead.
		await expect(
			page.locator( `${ FINDER } form input[type="submit"]` )
		).toBeVisible();
	} );

	test( 'submitting the form filters results server-side, via a shareable URL', async ( {
		page,
	} ) => {
		await page.goto( FINDER_PAGE );

		const before = await renderedCapacities( page );
		expect( before.some( ( capacity ) => capacity < 4 ) ).toBe( true );

		await page.getByLabel( CAPACITY_LABEL ).selectOption( '4' );
		// This click is a full page navigation, not a local state update, so
		// it needs the config's navigation budget rather than its (shorter)
		// action budget — see the Docker port-forwarding note in
		// playwright.config.js. Left at the action default it fails
		// intermittently, and only when the whole file runs.
		await page
			.locator( `${ FINDER } form input[type="submit"]` )
			.click( { timeout: 45_000 } );
		await page.waitForURL( /product_finder/ );

		// The answer survives in the query string, which is what makes a
		// filtered view bookmarkable and shareable either way (render.php).
		expect( page.url() ).toContain( 'capacity' );
		expect( page.url() ).toContain( '4' );

		const after = await renderedCapacities( page );
		expect( after.length ).toBeGreaterThan( 0 );
		// The hard filter is capacity >= 4, applied by the server this time.
		expect( after.every( ( capacity ) => capacity >= 4 ) ).toBe( true );
	} );

	test( 'the reset link returns to the unfiltered view', async ( {
		page,
	} ) => {
		await page.goto( `${ FINDER_PAGE }?product_finder[tents][capacity]=6` );

		expect(
			( await renderedCapacities( page ) ).every(
				( capacity ) => capacity >= 6
			)
		).toBe( true );

		// The anchor, not the always-present <button> the JS path uses —
		// both carry the same class and label.
		await page.locator( 'a.product-finder__reset' ).click();

		await expect( page.locator( '.product-finder__result' ) ).toHaveCount(
			RESULT_LIMIT
		);
		expect( page.url() ).not.toContain( 'product_finder' );
	} );
} );
