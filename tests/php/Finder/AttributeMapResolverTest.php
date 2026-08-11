<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Finder;

use PHPUnit\Framework\TestCase;
use ProductFinder\Finder\AttributeMapResolver;

final class AttributeMapResolverTest extends TestCase {

	private const TEMPLATE_MAP = array(
		'capacity' => array(
			'slug' => 'capacity',
			'type' => 'int',
		),
		'use_type' => array(
			'slug' => 'use-type',
			'type' => 'string',
		),
	);

	public function test_no_overrides_returns_the_template_defaults_unchanged(): void {
		$result = AttributeMapResolver::resolve( self::TEMPLATE_MAP, array() );

		$this->assertSame( self::TEMPLATE_MAP, $result );
	}

	public function test_an_override_replaces_only_the_slug_and_keeps_the_template_type(): void {
		$result = AttributeMapResolver::resolve(
			self::TEMPLATE_MAP,
			array( 'capacity' => 'tent-capacity' )
		);

		$this->assertSame(
			array(
				'slug' => 'tent-capacity',
				'type' => 'int',
			),
			$result['capacity']
		);
		// Untouched attribute stays exactly as the template defined it.
		$this->assertSame( self::TEMPLATE_MAP['use_type'], $result['use_type'] );
	}

	public function test_an_override_for_an_attribute_the_template_does_not_define_is_ignored(): void {
		$result = AttributeMapResolver::resolve(
			self::TEMPLATE_MAP,
			array( 'not_a_real_attribute' => 'whatever' )
		);

		$this->assertSame( self::TEMPLATE_MAP, $result );
	}
}
