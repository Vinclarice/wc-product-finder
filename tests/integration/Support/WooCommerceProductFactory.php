<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Integration\Support;

use WC_Product_Attribute;

trait WooCommerceProductFactory {

	private static function make_local_attribute( string $name, string $value ): WC_Product_Attribute {
		$attribute = new WC_Product_Attribute();
		$attribute->set_name( $name );
		$attribute->set_options( array( $value ) );
		$attribute->set_visible( true );
		$attribute->set_variation( false );
		return $attribute;
	}
}
