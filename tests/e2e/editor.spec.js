/**
 * Verifies the block editor's category control (src/product-finder/edit.js)
 * against the real block editor — not a component-level unit test, per this
 * project's stated approach to editor/admin UI (§9 of
 * PRODUCT-FINDER-PROPOSAL.md: thin coverage, checked manually/via e2e rather
 * than exhaustively unit-tested). Separate spec file from
 * product-finder.spec.js since this is editor behavior, not front-end
 * critical-path behavior.
 *
 * Authentication is handled automatically by
 * @wordpress/e2e-test-utils-playwright's worker-scoped requestUtils fixture
 * (default admin/password, matching wp-env's own defaults) against this
 * config's baseURL — no custom login code needed.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Product Finder - editor category control', () => {
	test( 'the category select is populated with real WooCommerce categories and defaults to the block.json default', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost( { postType: 'page' } );
		await editor.insertBlock( { name: 'product-finder/product-finder' } );
		await editor.openDocumentSettingsSidebar();

		const categorySelect = page.getByLabel( 'Category', { exact: true } );
		await expect( categorySelect ).toBeVisible();

		// block.json declares "default": "tents", and the seeded "Tents"
		// category exists — confirms the control reflects a real attribute
		// value, not just an empty/placeholder state.
		await expect( categorySelect ).toHaveValue( 'tents' );

		const optionLabels = await categorySelect.locator( 'option' ).allTextContents();
		expect( optionLabels ).toContain( 'Tents' );
		// Every WooCommerce site has this default category — confirms the
		// list is genuinely fetched from the site's real taxonomy terms via
		// core-data/REST, not a hardcoded single option.
		expect( optionLabels ).toContain( 'Uncategorized' );

		// The canvas preview should reflect the currently-selected category.
		await expect(
			editor.canvas.getByText( 'Product Finder — showing products from', { exact: false } )
		).toBeVisible();

		// No mismatch warning for the one category this template's copy
		// actually matches.
		await expect(
			page.getByText( 'written for tents', { exact: false } )
		).toBeHidden();
	} );

	test( 'changing the category updates the attribute and the canvas preview', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost( { postType: 'page' } );
		await editor.insertBlock( { name: 'product-finder/product-finder' } );
		await editor.openDocumentSettingsSidebar();

		const categorySelect = page.getByLabel( 'Category', { exact: true } );
		await categorySelect.selectOption( { label: 'Uncategorized' } );

		await expect( categorySelect ).toHaveValue( 'uncategorized' );
		await expect(
			editor.canvas.getByText( 'Uncategorized', { exact: false } )
		).toBeVisible();

		const blocks = await editor.getBlocks();
		expect( blocks[ 0 ].attributes.productCategory ).toBe( 'uncategorized' );

		// Picking any category other than "tents" surfaces the mismatch
		// warning — this template's questions are tent-phrased regardless.
		// Scoped to the Notice itself, not a plain text search: Gutenberg's
		// a11y-speak live region echoes the same text for screen readers,
		// so an unscoped locator matches twice.
		await expect(
			page
				.locator( '.components-notice' )
				.getByText( 'written for tents', { exact: false } )
		).toBeVisible();
	} );
} );
