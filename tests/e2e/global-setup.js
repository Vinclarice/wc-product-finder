/**
 * One-time setup for the e2e suite: makes sure the page the zero-match test
 * depends on exists (a Product Finder block pointed at a category with no
 * products) before any spec runs. Delegates the actual creation to
 * scripts/seed-e2e-pages.php via `wp eval-file`, run inside the wp-env `cli`
 * container - see that file for why it's a PHP script rather than a
 * `wp post create` shell one-liner (JSON-attribute quoting).
 *
 * Idempotent, like scripts/seed-tents.php: safe to run on every suite start.
 */
const { execSync } = require( 'child_process' );
const path = require( 'path' );

const PROJECT_ROOT = path.resolve( __dirname, '..', '..' );

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
};
