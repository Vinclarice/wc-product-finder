/**
 * Editor UI for the Product Finder block. The only merchant-facing control
 * here is which WooCommerce product category this instance searches —
 * question customization itself happens on the admin mapping/questions
 * screen (§13 of PRODUCT-FINDER-PROPOSAL.md, Phase 3), not here.
 *
 * No live preview of match results here (that would mean either a
 * ServerSideRender round-trip or duplicating the Interactivity API's local
 * matching logic into the editor) — just confirmation of which category is
 * selected, consistent with this project's "thin coverage for editor UI,
 * verified manually/via e2e rather than exhaustively" testing approach (§9).
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Notice, PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

import './editor.scss';

const CATEGORY_QUERY = { per_page: -1 };

// The only category guaranteed to already have sensible questions without
// visiting the admin screen — TentsTemplate's own defaults are written for
// it. Any other category could have its own saved custom questions (§13,
// Phase 3) or could still be using those tent-phrased defaults; the editor
// has no way to tell which without a live lookup this doesn't do (deferred
// — see PRODUCT-FINDER-PROPOSAL.md §13), so the notice below is worded to
// be accurate either way rather than assuming the worse case.
const TEMPLATE_CATEGORY = 'tents';

export default function Edit( { attributes, setAttributes } ) {
	const { productCategory } = attributes;

	const { categories, isLoading } = useSelect( ( select ) => {
		const { getEntityRecords, isResolving } = select( coreStore );
		return {
			categories: getEntityRecords( 'taxonomy', 'product_cat', CATEGORY_QUERY ),
			isLoading: isResolving( 'getEntityRecords', [
				'taxonomy',
				'product_cat',
				CATEGORY_QUERY,
			] ),
		};
	}, [] );

	const options = [
		{ label: __( '— Select a category —', 'product-finder' ), value: '' },
		...( categories ?? [] ).map( ( category ) => ( {
			label: category.name,
			value: category.slug,
		} ) ),
	];

	const selectedCategory = ( categories ?? [] ).find(
		( category ) => category.slug === productCategory
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Product Finder settings', 'product-finder' ) }>
					{ isLoading && ! categories ? (
						<Spinner />
					) : (
						<SelectControl
							label={ __( 'Category', 'product-finder' ) }
							value={ productCategory }
							options={ options }
							onChange={ ( value ) =>
								setAttributes( { productCategory: value } )
							}
							help={ __(
								'Which WooCommerce product category this finder searches.',
								'product-finder'
							) }
						/>
					) }
					{ productCategory && productCategory !== TEMPLATE_CATEGORY && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'The starter template\'s questions default to tent-phrased wording (e.g. "How many people will sleep in it?") unless you\'ve set up custom questions for this category on the Product Finder settings screen (WooCommerce → Product Finder).',
								'product-finder'
							) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<p>
					{ productCategory
						? sprintf(
								/* translators: %s: the selected category's name (or its slug, if the category couldn't be found) */
								__( 'Product Finder — showing products from “%s”.', 'product-finder' ),
								selectedCategory ? selectedCategory.name : productCategory
						  )
						: __(
								'Product Finder — choose a category in the block settings sidebar.',
								'product-finder'
						  ) }
				</p>
			</div>
		</>
	);
}
