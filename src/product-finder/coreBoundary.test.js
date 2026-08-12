/**
 * JS side of the same boundary CoreBoundaryTest.php enforces in PHP: the
 * pure product-matching/rule modules must never import a WordPress or
 * WooCommerce package directly, so they stay usable under plain Jest (as
 * they already are — see their own *.fixtures.test.js / .test.js files)
 * without the block editor or Interactivity runtime. See ARCHITECTURE.md.
 *
 * CORE_FILES is also the documentation of what's meant to stay pure: adding
 * a new core module means adding it here too.
 */
const fs = require( 'fs' );
const path = require( 'path' );

const CORE_FILES = [ 'matchEngine.js', 'rules.js', 'relaxationExplainer.js' ];
const FORBIDDEN_IMPORT_PATTERN = /from\s+['"]@(wordpress|woocommerce)\//;

describe.each( CORE_FILES )( '%s', ( filename ) => {
	it( 'never imports a WordPress or WooCommerce package', () => {
		const source = fs.readFileSync(
			path.join( __dirname, filename ),
			'utf8'
		);

		const violations = source
			.split( '\n' )
			.map( ( line, index ) => ( { line, number: index + 1 } ) )
			.filter( ( { line } ) => FORBIDDEN_IMPORT_PATTERN.test( line ) )
			.map(
				( { line, number } ) => `  line ${ number }: ${ line.trim() }`
			);

		expect( violations ).toEqual( [] );
	} );
} );
