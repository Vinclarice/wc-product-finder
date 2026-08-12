/**
 * @wordpress/env's offline-detection check uses dns.resolve() (a raw DNS
 * protocol query), which fails with ECONNREFUSED on some networks/sandboxes
 * even though normal DNS resolution (dns.lookup, used by fetch/curl/got)
 * works fine. When that check misfires, wp-env silently skips downloading
 * WordPress/WooCommerce instead of erroring, leaving empty containers.
 *
 * This swaps dns.resolve() for dns.lookup() in the vendored package.
 * Runs automatically via the "postinstall" script since node_modules
 * changes don't survive npm install otherwise.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const target = path.join(
	__dirname,
	'..',
	'node_modules',
	'@wordpress',
	'env',
	'lib',
	'wordpress.js'
);

if ( ! fs.existsSync( target ) ) {
	// @wordpress/env isn't installed (e.g. a CI job that doesn't need it) — nothing to do.
	process.exit( 0 );
}

const original = fs.readFileSync( target, 'utf8' );
const broken = "dns.resolve( 'WordPress.org' )";
const fixed = "dns.lookup( 'WordPress.org' )";

if ( original.includes( fixed ) ) {
	process.exit( 0 ); // Already patched.
}

if ( ! original.includes( broken ) ) {
	console.warn(
		'[fix-wp-env-dns] Expected pattern not found in @wordpress/env/lib/wordpress.js — package may have changed, skipping patch.'
	);
	process.exit( 0 );
}

fs.writeFileSync( target, original.replace( broken, fixed ) );
console.log(
	'[fix-wp-env-dns] Patched @wordpress/env to use dns.lookup() instead of dns.resolve().'
);
