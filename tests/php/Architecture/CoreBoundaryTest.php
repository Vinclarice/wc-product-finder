<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Enforces the one architectural rule this plugin actually depends on: the
 * pure "functional core" (product-matching/question logic) must never call
 * WordPress or WooCommerce directly. That's what lets it run under plain
 * PHPUnit with no WP bootstrap, what will make it property-testable, and
 * what keeps a future contributor (or premium add-on) from accidentally
 * coupling core logic to a specific WP/WC version. See ARCHITECTURE.md.
 *
 * A token scan via PHP's built-in token_get_all(), not a dependency-analysis
 * tool like Deptrac: there is exactly one boundary to enforce today, and
 * this needs no new dependency to do it. Tokenizing (rather than a regex
 * over the raw source) means comments and string literals — which several
 * of these files' docblocks deliberately reference WP concepts in, e.g.
 * "$_GET" — are never mistaken for real calls.
 *
 * CORE_FILES is also the documentation of what's meant to stay pure: adding
 * a new core module means adding it here too.
 */
final class CoreBoundaryTest extends TestCase {

	private const CORE_FILES = array(
		'Engine/MatchEngine.php',
		'Finder/RuleBuilder.php',
		'Finder/RelaxationExplainer.php',
		'Finder/QuestionSetResolver.php',
		'Finder/AttributeMapResolver.php',
		'Attributes/AttributeCompleteness.php',
	);

	// Identifiers that signal a call out to WordPress/WooCommerce. A name
	// list, not "anything that isn't a known-safe function" — i18n helpers
	// (__(), _n(), _x()...) don't match any of these and are deliberately
	// allowed: translation is a presentation concern, not a dependency on
	// WordPress's behavior or data, and RelaxationExplainer needs it.
	private const FORBIDDEN_PREFIXES = array( 'wp_', 'WC_', 'WP_' );
	private const FORBIDDEN_NAMES    = array(
		'get_option',
		'update_option',
		'add_option',
		'delete_option',
		'add_action',
		'add_filter',
		'apply_filters',
		'do_action',
		'current_user_can',
		'check_admin_referer',
		'sanitize_text_field',
		'sanitize_key',
		'sanitize_title',
	);

	/**
	 * @dataProvider core_file_provider
	 */
	public function test_core_file_never_calls_wordpress_or_woocommerce_directly( string $relative_path ): void {
		$path = dirname( __DIR__, 3 ) . '/includes/' . $relative_path;
		self::assertFileExists( $path, "Expected core file to exist: {$relative_path}" );

		$violations = self::find_violations( file_get_contents( $path ) );

		self::assertSame(
			array(),
			$violations,
			sprintf(
				"%s calls WordPress/WooCommerce directly, which breaks the functional-core boundary:\n%s",
				$relative_path,
				implode( "\n", $violations )
			)
		);
	}

	public function core_file_provider(): array {
		return array_map( static fn( string $file ) => array( $file ), self::CORE_FILES );
	}

	private static function find_violations( string $source ): array {
		$violations = array();
		$tokens     = token_get_all( $source );
		$count      = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
				continue;
			}

			$name = $token[1];
			if ( ! self::is_forbidden_name( $name ) ) {
				continue;
			}

			// Only flag it as an actual call/static-access — a bare
			// identifier isn't enough (avoids false positives on, say, a
			// class constant or variable that happens to share a name).
			$next = self::next_significant_token( $tokens, $i + 1 );
			if ( null === $next || ! in_array( $next[1], array( '(', '::' ), true ) ) {
				continue;
			}

			$violations[] = sprintf( '  line %d: %s', $token[2], $name );
		}

		return $violations;
	}

	private static function is_forbidden_name( string $name ): bool {
		if ( in_array( $name, self::FORBIDDEN_NAMES, true ) ) {
			return true;
		}

		foreach ( self::FORBIDDEN_PREFIXES as $prefix ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array{0: int|string, 1?: string, 2?: int}|null
	 */
	private static function next_significant_token( array $tokens, int $start ): ?array {
		$count = count( $tokens );
		for ( $i = $start; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			// Normalize a bare-string token (e.g. '(' or '::') to the same
			// shape as an array token so callers only ever index [1].
			return is_array( $token ) ? $token : array( $token, $token );
		}
		return null;
	}
}
