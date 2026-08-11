<?php

declare(strict_types=1);

namespace ProductFinder\Query;

final class ProductQuery {

	/**
	 * @return \WC_Product[]
	 */
	public static function for_category( string $category_slug, int $limit = 3 ): array {
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
