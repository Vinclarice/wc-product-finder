<?php

declare(strict_types=1);

namespace ProductFinder\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

final class ProductQuery {

	/**
	 * @return \WC_Product[]
	 */
	public static function for_category( string $category_slug, int $limit ): array {
		return wc_get_products(
			array(
				'category' => array( $category_slug ),
				'status'   => 'publish',
				'limit'    => $limit,
				'orderby'  => 'title',
				'order'    => 'ASC',
			)
		);
	}
}
