const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * A small number of critical-path e2e scenarios against a real wp-env
 * instance (§9 of PRODUCT-FINDER-PROPOSAL.md): answer questions -> see
 * filtered results update, and the zero-match fallback explaining itself.
 * Deliberately not exhaustive markup coverage — see tests/e2e/product-finder.spec.js.
 *
 * Requires wp-env running (`npx wp-env start`) and the plugin built
 * (`npm run build`) before the suite is run. WP_BASE_URL defaults to the
 * dev site wp-env maps to the host; override it to point at a different
 * environment (e.g. a CI-provisioned one) without editing this file.
 *
 * NOTE on timing: reaching the wp-env container's mapped port from this
 * sandbox has been observed to take up to ~15s per navigation (confirmed
 * empirically, not a fixed/known constant) — almost certainly Docker
 * Desktop port-forwarding overhead specific to this environment, not
 * anything about the plugin itself. Timeouts below are set generously to
 * absorb that; a normal machine should complete far faster than these caps.
 */

const WP_BASE_URL = process.env.WP_BASE_URL || 'http://localhost:8888';
// Matches the default STORAGE_STATE_PATH computation in
// @wordpress/e2e-test-utils-playwright's worker-scoped `requestUtils`
// fixture, and the path global-setup.js logs in and saves to. Setting it
// here is what makes the *browser* context (not just requestUtils's API
// request context) start each test already authenticated.
const STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH ||
	path.join( __dirname, '..', '..', 'artifacts/storage-states/admin.json' );

module.exports = defineConfig( {
	testDir: __dirname,
	testMatch: '**/*.spec.js',
	globalSetup: require.resolve( './global-setup.js' ),
	timeout: 90_000,
	expect: {
		timeout: 15_000,
	},
	// The suite shares one wp-env instance's seeded content (24 tents, a
	// couple of fixed pages) - keep runs serial rather than racing workers
	// against the same data.
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [ [ 'list' ] ],
	use: {
		baseURL: WP_BASE_URL,
		storageState: STORAGE_STATE_PATH,
		actionTimeout: 15_000,
		navigationTimeout: 45_000,
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
