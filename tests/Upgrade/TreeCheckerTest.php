<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Upgrade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WMCZ\Tests\Support\TemporaryFilesTrait;
use WMCZ\Upgrade\Dependency;
use WMCZ\Upgrade\TreeChecker;

#[CoversClass( TreeChecker::class )]
final class TreeCheckerTest extends TestCase {
	use TemporaryFilesTrait;

	/**
	 * @param array<string,string> $files Path in the tree => content (arrays are JSON-encoded)
	 * @param string ...$entries "<type> <name>"
	 * @return string[]
	 */
	private function check( array $files, string ...$entries ): array {
		$root = $this->getTemporaryDirectory();
		foreach ( $files as $path => $content ) {
			$this->writeFile( "$root/$path", is_array( $content ) ? (string)json_encode( $content ) : $content );
		}
		$dependencies = [];
		foreach ( $entries as $entry ) {
			$dependencies[$entry] = new Dependency( ...explode( ' ', $entry ) );
		}
		return TreeChecker::check( $root, $dependencies );
	}

	public function testHealthyTree(): void {
		$this->assertSame( [], $this->check( [
			'extensions/Foo/extension.json' => [ 'requires' => [ 'MediaWiki' => '>= 1.46.0', 'extensions' => [ 'Bar' => '*' ] ] ],
			'extensions/Bar/extension.json' => [],
			'skins/Vector/skin.json' => [],
			// Snapshots ship their Composer dependencies
			'extensions/Widgets/extension.json' => [],
			'extensions/Widgets/composer.json' => [ 'require' => [ 'smarty/smarty' => '^5' ] ],
			'extensions/Widgets/vendor/autoload.php' => '<?php',
		], 'extension Foo', 'extension Bar', 'skin Vector', 'extension Widgets' ) );
	}

	public function testProblems(): void {
		$this->assertSame( [
			'extension Interwiki was moved to MediaWiki core and is only a stub now; '
				. 'stop loading it in CommonSettings.php and remove it from dependencies.txt.',
			'extension NoManifest has no extension.json; loading it will fail.',
			'skin NoManifest has no skin.json; loading it will fail.',
			'extension Broken: unable to parse ' . $this->getTemporaryDirectory() . '/extensions/Broken/extension.json',
			'extension Foo requires extension Bundled, which is not listed in dependencies.txt '
				. '(it is bundled with core, but needs to be loaded too).',
			'extension Foo requires extension Elsewhere, which is not listed in dependencies.txt.',
			'extension Foo requires skin Vector, which is not listed in dependencies.txt.',
			'extension Foo needs Composer packages that are not in the tree (smarty/smarty); add it to '
				. 'composer.local.json and run "composer update --no-dev" in the tree.',
		], $this->check( [
			'extensions/Interwiki/MOVED_TO_CORE' => 'Moved.',
			'extensions/NoManifest/README' => '',
			'extensions/Broken/extension.json' => '{ broken',
			'extensions/Foo/extension.json' => [ 'requires' => [
				'extensions' => [ 'Bundled' => '*', 'Elsewhere' => '*' ],
				'skins' => [ 'Vector' => '*' ],
			] ],
			'extensions/Foo/composer.json' => [ 'require' => [
				'php' => '>=8.2', 'ext-json' => '*', 'composer/installers' => '*',
				'smarty/smarty' => '^5', 'wikimedia/at-ease' => '^3',
			] ],
			// Already in core's vendor/
			'vendor/wikimedia/at-ease/composer.json' => '{}',
			'extensions/Bundled/extension.json' => [],
		], 'extension Interwiki', 'extension NoManifest', 'skin NoManifest', 'extension Broken', 'extension Foo' ) );
	}
}
