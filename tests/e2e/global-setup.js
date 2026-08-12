/* eslint-disable no-console -- Playwright surfaces globalSetup's console
   output as the suite's own startup log; without it a slow seed or a failed
   login looks like the run simply hanging. */

/**
 * One-time setup for the e2e suite:
 *
 * 1. Makes sure the page the zero-match test depends on exists (a Product
 *    Finder block pointed at a category with no products) before any spec
 *    runs. Delegates the actual creation to scripts/seed-e2e-pages.php via
 *    `wp eval-file`, run inside the wp-env `cli` container - see that file
 *    for why it's a PHP script rather than a `wp post create` shell
 *    one-liner (JSON-attribute quoting).
 *
 * 2. Logs in as the wp-env default admin and persists the session to disk
 *    (the same STORAGE_STATE_PATH that @wordpress/e2e-test-utils-playwright's
 *    worker-scoped `requestUtils` fixture reads from). That fixture only
 *    *reads* an existing storage-state file — it never performs a login
 *    itself — so without this step every test's browser context starts
 *    unauthenticated. playwright.config.js's `use.storageState` points at
 *    the same path so the browser (not just `requestUtils`'s API request
 *    context) picks up the session cookies.
 *
 * Both steps are idempotent, like scripts/seed-tents.php: safe to run on
 * every suite start.
 */
const { execSync } = require( 'child_process' );
const path = require( 'path' );
const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );

const PROJECT_ROOT = path.resolve( __dirname, '..', '..' );
const WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
const STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH ||
	path.join( PROJECT_ROOT, 'artifacts/storage-states/admin.json' );

module.exports = async function globalSetup() {
	console.log( '[global-setup] Seeding e2e test pages via wp-cli...' );
	try {
		const output = execSync(
			'npx wp-env run cli wp eval-file wp-content/plugins/product-finder/scripts/seed-e2e-pages.php',
			{
				cwd: PROJECT_ROOT,
				encoding: 'utf8',
				timeout: 120_000,
				// Harmless outside git-bash/MSYS shells; prevents the same
				// absolute-Unix-path mangling documented in package.json's
				// other `wp-env run` scripts if this ever runs under one.
				env: { ...process.env, MSYS_NO_PATHCONV: '1' },
			}
		);
		console.log( output.trim() );
	} catch ( error ) {
		console.error(
			'[global-setup] Failed to seed e2e test pages. Is wp-env running (`npx wp-env start`)?'
		);
		throw error;
	}

	console.log( '[global-setup] Logging in as wp-env default admin...' );
	// RequestUtils.setup() builds its API request context with Playwright's
	// 30s default timeout, which this sandbox's Docker port-forwarding
	// overhead (independently confirmed at ~14s per request; see the timing
	// note in playwright.config.js) can blow through once wp-login.php's
	// redirect chain is followed. Build the context ourselves with a longer
	// timeout instead of going through RequestUtils.setup().
	const requestContext = await request.newContext( {
		baseURL: WP_BASE_URL,
		timeout: 60_000,
	} );
	const requestUtils = new RequestUtils( requestContext, {
		baseURL: WP_BASE_URL,
		storageStatePath: STORAGE_STATE_PATH,
	} );
	await requestUtils.login();
	await requestUtils.request.storageState( { path: STORAGE_STATE_PATH } );
	await requestContext.dispose();
};
