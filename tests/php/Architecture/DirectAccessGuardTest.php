<?php

declare(strict_types=1);

namespace ProductFinder\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * WordPress.org's review guidelines expect a shipped PHP file not to do
 * anything useful when it's requested directly, rather than through
 * WordPress — the conventional `if ( ! defined( 'ABSPATH' ) ) { exit; }`
 * guard at the top of the file.
 *
 * That guideline can't be applied uniformly here, and the split is the
 * point of this test:
 *
 * - **Shell** files must have the guard. They only ever run inside
 *   WordPress, so ABSPATH is always defined for them.
 * - **Core** files (CoreBoundaryTest::CORE_FILES) must NOT have it. The
 *   whole reason they exist as a separate layer is that they run with no
 *   WordPress at all — phpunit.xml bootstraps `vendor/autoload.php` and
 *   nothing else, so ABSPATH is undefined and the guard would `exit` the
 *   PHPUnit process the moment Composer autoloaded one of them. Adding it
 *   "for consistency" would silently kill the fast test suite.
 *
 * Leaving core unguarded costs nothing in practice: those files declare a
 * class and execute no statements at file scope, so requesting one
 * directly produces no output and no side effects. The guard protects
 * against files that *do* something on include — which, in this plugin, is
 * `includes/Admin/settings-page.php` (a template) and the shell classes
 * that reach for WordPress state.
 *
 * Classification is derived, not hand-listed: anything under includes/ that
 * isn't in CORE_FILES is treated as shell, so a newly added shell file
 * fails this test until it's guarded rather than quietly shipping without.
 */
final class DirectAccessGuardTest extends TestCase {

	private const GUARD_NEEDLE = "defined( 'ABSPATH' )";

	/**
	 * @dataProvider shell_file_provider
	 */
	public function test_shell_file_exits_when_accessed_directly( string $relative_path ): void {
		$source = file_get_contents( self::includes_dir() . '/' . $relative_path );

		self::assertStringContainsString(
			self::GUARD_NEEDLE,
			$source,
			"{$relative_path} is a shell file, so it needs the ABSPATH direct-access guard."
		);
	}

	/**
	 * @dataProvider core_file_provider
	 */
	public function test_core_file_has_no_abspath_guard( string $relative_path ): void {
		$source = file_get_contents( self::includes_dir() . '/' . $relative_path );

		self::assertStringNotContainsString(
			self::GUARD_NEEDLE,
			$source,
			"{$relative_path} is a functional-core file and must load without WordPress. "
				. 'An ABSPATH guard here would exit this very test run — see this class\'s docblock.'
		);
	}

	public function shell_file_provider(): array {
		$shell = array_diff( self::all_php_files(), CoreBoundaryTest::CORE_FILES );

		// A guard against this test silently passing on an empty set if the
		// directory scan ever breaks.
		self::assertNotEmpty( $shell, 'Expected to find shell files under includes/.' );

		return array_map( static fn( string $file ) => array( $file ), array_values( $shell ) );
	}

	public function core_file_provider(): array {
		return array_map( static fn( string $file ) => array( $file ), CoreBoundaryTest::CORE_FILES );
	}

	/**
	 * @return string[] Paths relative to includes/, using forward slashes on
	 *                  every platform so they compare cleanly against
	 *                  CORE_FILES' literals.
	 */
	private static function all_php_files(): array {
		$directory = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( self::includes_dir(), \FilesystemIterator::SKIP_DOTS )
		);

		$files = array();
		foreach ( $directory as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$relative = substr( $file->getPathname(), strlen( self::includes_dir() ) + 1 );
			$files[]  = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
		}

		sort( $files );
		return $files;
	}

	private static function includes_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes';
	}
}
