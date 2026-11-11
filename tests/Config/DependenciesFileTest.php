<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Config;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WMCZ\Upgrade\Dependency;

/**
 * Keeps dependencies.txt in sync with what the configuration loads.
 */
#[CoversNothing]
final class DependenciesFileTest extends TestCase {

	/**
	 * Find extensions and skins loaded by PHP code, ignoring comments and extensions loaded
	 * from an explicit path (wfLoadExtension( 'Foo', '/path/extension.json' )), which live
	 * outside the MediaWiki tree.
	 *
	 * @return string[] "<type> <name>" entries
	 */
	private static function findLoaded( string $php ): array {
		$code = implode( '', array_map(
			static fn ( $token ) => is_array( $token ) ? ( in_array( $token[0], [ T_COMMENT, T_DOC_COMMENT ] ) ? '' : $token[1] ) : $token,
			token_get_all( $php )
		) );
		preg_match_all( '/\bwfLoad(Extension|Skin)(?:\(\s*([\'"][^\'"]+[\'"])\s*\)|s\(\s*\[([^\]]*)\]\s*\))/', $code, $calls, PREG_SET_ORDER );
		$loaded = [];
		foreach ( $calls as $call ) {
			preg_match_all( '/[\'"]([^\'"]+)[\'"]/', $call[2] . ( $call[3] ?? '' ), $names );
			foreach ( $names[1] as $name ) {
				$loaded[] = strtolower( $call[1] ) . " $name";
			}
		}
		return array_values( array_unique( $loaded ) );
	}

	public static function provideFindLoaded(): iterable {
		yield 'single' => [ "<?php wfLoadExtension( 'Cite' ); wfLoadSkin(\"Vector\");", [ 'extension Cite', 'skin Vector' ] ];
		yield 'arrays' => [
			"<?php wfLoadSkins( [ 'Vector', 'MonoBook' ] ); wfLoadExtensions( [ 'Cite' ] );",
			[ 'skin Vector', 'skin MonoBook', 'extension Cite' ],
		];
		yield 'conditional' => [ "<?php if ( \$x ) {\n\twfLoadExtension( 'OAuth' );\n}", [ 'extension OAuth' ] ];
		yield 'comments' => [ "<?php\n//wfLoadExtension( 'A' );\n# wfLoadExtension( 'B' );\n/* wfLoadExtension( 'C' ); */", [] ];
		yield 'explicit path' => [ "<?php wfLoadExtension( 'Parsoid', \"\$dir/extension.json\" );", [] ];
	}

	#[DataProvider( 'provideFindLoaded' )]
	public function testFindLoaded( string $php, array $expected ): void {
		$this->assertSame( $expected, self::findLoaded( $php ) );
	}

	public function testEverythingLoadedIsListed(): void {
		$dependencies = Dependency::readList( __DIR__ . '/../../dependencies.txt' );
		$loaded = [];
		foreach ( glob( __DIR__ . '/../../wmcz-config/*.php' ) ?: [] as $file ) {
			array_push( $loaded, ...self::findLoaded( (string)file_get_contents( $file ) ) );
		}

		$this->assertNotEmpty( $loaded, 'Sanity check: the configuration loads something' );
		$this->assertSame( [], array_values( array_diff( $loaded, array_keys( $dependencies ) ) ),
			'Loaded by wmcz-config/, but missing from dependencies.txt' );
	}
}
