/**
 * Editor UI for the Product Finder block. The only merchant-facing control
 * here is which WooCommerce product category this instance searches — real
 * question customization stays server-side/hardcoded per TentsTemplate for
 * now (§13 of PRODUCT-FINDER-PROPOSAL.md notes that boundary explicitly).
 *
 * No live preview of match results here (that would mean either a
 * ServerSideRender round-trip or duplicating the Interactivity API's local
 * matching logic into the editor) — just confirmation of which category is
 * selected, consistent with this project's "thin coverage for editor UI,
 * verified manually/via e2e rather than exhaustively" testing approach (§9).
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

import './editor.scss';

const CATEGORY_QUERY = { per_page: -1 };

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
