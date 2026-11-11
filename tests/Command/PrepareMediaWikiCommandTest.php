<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use WMCZ\Command\PrepareMediaWikiCommand;
use WMCZ\Tests\Support\FakeDownloader;
use WMCZ\Tests\Support\SigningKey;
use WMCZ\Tests\Support\TemporaryFilesTrait;
use WMCZ\Upgrade\PrepareException;
use WMCZ\Upgrade\Shell;

/**
 * Runs the whole command offline: releases.wikimedia.org and ExtensionDistributor are
 * replaced by a FakeDownloader serving locally built tarballs.
 */
#[CoversClass( PrepareMediaWikiCommand::class )]
final class PrepareMediaWikiCommandTest extends TestCase {
	use TemporaryFilesTrait;

	private const CORE_URL = 'https://releases.wikimedia.org/mediawiki/1.46/mediawiki-1.46.2.tar.gz';
	private const EXTDIST_URL = 'https://extdist.wmflabs.org/dist';
	private const DEPENDENCIES = "skin Modern\nskin Vector\nextension Cite\nextension Interwiki\nextension UserMerge\n";

	private string $repoDir;
	private string $target;
	private FakeDownloader $downloader;
	private ?Shell $shell = null;
	private ?SigningKey $signingKey = null;
	private string|false $columns;

	protected function setUp(): void {
		// Do not let SymfonyStyle wrap long lines (paths)
		$this->columns = getenv( 'COLUMNS' );
		putenv( 'COLUMNS=500' );

		// Same layout as in production, so that the default --target is used
		$tmp = $this->getTemporaryDirectory();
		$this->repoDir = "$tmp/mediawiki-config";
		$this->target = "$tmp/mw";
		mkdir( $this->target );
		$this->writeFile( "$this->repoDir/wmcz-config/CommonSettings.php", '<?php' );
		$this->writeDependencies( self::DEPENDENCIES );

		// A MediaWiki release with Cite and Vector bundled, and ExtensionDistributor snapshots
		$this->createTarball( "$tmp/fixtures/core.tar.gz", [
			'mediawiki-1.46.2/index.php' => '<?php',
			'mediawiki-1.46.2/extensions/Cite/extension.json' => '{"name":"Cite"}',
			'mediawiki-1.46.2/skins/Vector/skin.json' => '{"name":"Vector"}',
		] );
		$files = [ self::CORE_URL => "$tmp/fixtures/core.tar.gz" ];
		$snapshots = [];
		foreach ( [
			'extension' => [
				'Cite' => [ 'REL1_46' => [ 'Cite/extension.json' => '{}' ], 'master' => [ 'Cite/extension.json' => '{}' ] ],
				'Interwiki' => [ 'REL1_46' => [ 'Interwiki/MOVED_TO_CORE' => 'Moved to core in 1.44.' ] ],
				'UserMerge' => [
					'REL1_46' => [ 'UserMerge/extension.json' => '{"requires":{"extensions":{"Cite":"*"}}}' ],
					'master' => [ 'UserMerge/extension.json' => '{}', 'UserMerge/MASTER' => '' ],
				],
			],
			'skin' => [
				'Modern' => [ 'REL1_46' => [ 'Modern/skin.json' => '{}' ] ],
				'Vector' => [ 'REL1_46' => [ 'Vector/skin.json' => '{}' ] ],
			],
		] as $type => $byName ) {
			foreach ( $byName as $name => $byBranch ) {
				foreach ( $byBranch as $branch => $contents ) {
					$url = self::EXTDIST_URL . "/{$type}s/$name-$branch-0123abc.tar.gz";
					$files[$url] = "$tmp/fixtures/" . basename( $url );
					$this->createTarball( $files[$url], $contents );
					$snapshots[$type][$name][$branch] = $url;
				}
			}
		}
		$this->downloader = new FakeDownloader( $files, $snapshots );
	}

	protected function tearDown(): void {
		$this->signingKey?->shutdown();
		putenv( $this->columns === false ? 'COLUMNS' : "COLUMNS=$this->columns" );
	}

	private function writeDependencies( string $content ): void {
		$this->writeFile( "$this->repoDir/dependencies.txt", $content );
	}

	/**
	 * @return array{0:int,1:string} Exit code and output (with whitespace collapsed)
	 */
	private function runCommand( array $input = [] ): array {
		$application = new Application();
		$application->addCommand( new PrepareMediaWikiCommand( $this->repoDir, $this->shell, $this->downloader ) );
		$tester = new CommandTester( $application->find( 'mediawiki:prepare' ) );
		$tester->execute( $input + [ 'version' => '1.46.2', '--skip-gpg' => true ], [ 'interactive' => false ] );
		return [ $tester->getStatusCode(), (string)preg_replace( '/\s+/', ' ', $tester->getDisplay() ) ];
	}

	/**
	 * @return string[] Everything in the target directory
	 */
	private function getTargetContents(): array {
		return array_values( array_diff( scandir( $this->target ) ?: [], [ '.', '..' ] ) );
	}

	public function testPreparesTree(): void {
		// An installation that is in use
		mkdir( "$this->target/1.45.0" );
		symlink( '1.45.0', "$this->target/current" );

		[ $status, $output ] = $this->runCommand();

		$this->assertSame( Command::SUCCESS, $status, $output );
		$tree = "$this->target/1.46.2";
		$this->assertFileExists( "$tree/index.php" );
		// Bundled with core: kept from the release tarball, not downloaded
		$this->assertStringEqualsFile( "$tree/extensions/Cite/extension.json", '{"name":"Cite"}' );
		$this->assertStringEqualsFile( "$tree/skins/Vector/skin.json", '{"name":"Vector"}' );
		// Not bundled: downloaded from ExtensionDistributor, from the release branch
		$this->assertSame( [
			self::CORE_URL,
			self::EXTDIST_URL . '/skins/Modern-REL1_46-0123abc.tar.gz',
			self::EXTDIST_URL . '/extensions/Interwiki-REL1_46-0123abc.tar.gz',
			self::EXTDIST_URL . '/extensions/UserMerge-REL1_46-0123abc.tar.gz',
		], $this->downloader->requested );
		$this->assertFileExists( "$tree/extensions/UserMerge/extension.json" );
		$this->assertFileDoesNotExist( "$tree/extensions/UserMerge/MASTER" );
		$this->assertFileExists( "$tree/skins/Modern/skin.json" );
		$this->assertFileExists( "$tree/extensions/Interwiki/MOVED_TO_CORE" );

		$this->assertSame( realpath( "$this->repoDir/wmcz-config/CommonSettings.php" ), readlink( "$tree/LocalSettings.php" ) );
		$manifest = (string)file_get_contents( "$tree/PREPARED.txt" );
		$this->assertStringStartsWith( 'MediaWiki 1.46.2, prepared by bin/console mediawiki:prepare on ', $manifest );
		$this->assertStringContainsString(
			self::CORE_URL . ' sha256:' . hash_file( 'sha256', $this->downloader->files[self::CORE_URL] ), $manifest
		);
		$this->assertMatchesRegularExpression( '/^extension +Cite +bundled$/m', $manifest );
		$this->assertMatchesRegularExpression(
			'!^extension +UserMerge +' . self::EXTDIST_URL . '/extensions/UserMerge-REL1_46-0123abc\.tar\.gz sha256:[0-9a-f]{64}$!m',
			$manifest
		);

		// Nothing else changed: no leftovers, "current" untouched
		$this->assertSame( [ '1.45.0', '1.46.2', 'current' ], $this->getTargetContents() );
		$this->assertSame( '1.45.0', readlink( "$this->target/current" ) );

		$this->assertStringContainsString( 'Skipping GPG signature verification', $output );
		$this->assertStringContainsString( 'extension Interwiki was moved to MediaWiki core', $output );
		$this->assertStringContainsString( "MediaWiki 1.46.2 is ready in $tree", $output );
		$this->assertStringContainsString( "ln -sfn 1.46.2 $this->target/current", $output );
	}

	public function testVerifiesSignature(): void {
		SigningKey::skipIfUnavailable( $this );
		$tmp = $this->getTemporaryDirectory();
		$this->signingKey = new SigningKey( "$tmp/gpg", 'Test Release Signer <signer@example.org>' );
		$this->signingKey->exportPublicKey( "$tmp/keys.txt" );
		$this->signingKey->sign( $this->downloader->files[self::CORE_URL], "$tmp/core.sig" );
		$this->downloader->files[self::CORE_URL . '.sig'] = "$tmp/core.sig";

		[ $status, $output ] = $this->runCommand( [ '--keys' => "$tmp/keys.txt", '--skip-gpg' => false ] );

		$this->assertSame( Command::SUCCESS, $status, $output );
		$this->assertStringContainsString( 'GPG signature OK (signed by Test Release Signer <signer@example.org>)', $output );
	}

	public function testBranchOverrides(): void {
		$this->writeDependencies( "extension UserMerge master\nextension Cite master\n" );

		[ $status, $output ] = $this->runCommand();

		$this->assertSame( Command::SUCCESS, $status, $output );
		$this->assertFileExists( "$this->target/1.46.2/extensions/UserMerge/MASTER" );
		$this->assertStringContainsString( 'extension UserMerge: downloading master snapshot', $output );
		// Bundled extensions are kept
		$this->assertStringEqualsFile( "$this->target/1.46.2/extensions/Cite/extension.json", '{"name":"Cite"}' );
		$this->assertStringContainsString(
			'extension Cite is bundled with core; branch "master" from dependencies.txt was ignored.', $output
		);
	}

	public static function provideEarlyFailures(): iterable {
		yield 'existing version' => [
			static fn ( self $test ) => $test->writeFile( "$test->target/1.46.2/LocalSettings.php", 'keep me' ),
			[],
			'1.46.2 already exists',
		];
		yield 'MediaWiki installation as target' => [
			static function ( self $test ): void {
				$test->writeFile( "$test->target/index.php" );
				mkdir( "$test->target/includes" );
			},
			[],
			'looks like a MediaWiki installation',
		];
		yield 'missing target' => [ null, [ '--target' => '/nonexistent' ], 'Target directory /nonexistent does not exist' ];
		yield 'invalid version' => [ null, [ 'version' => 'latest' ], 'Invalid version "latest"' ];
		yield 'unknown dependencies' => [
			static fn ( self $test ) => $test->writeDependencies( self::DEPENDENCIES . "extension Bogus\nskin vector\n" ),
			[],
			'ExtensionDistributor does not know: extension Bogus, skin vector',
		];
		// The fixtures only offer REL1_46 and master snapshots, like ExtensionDistributor
		// does for releases that are no longer supported
		yield 'unsupported release' => [
			null,
			[ 'version' => '1.44.6' ],
			'ExtensionDistributor offers no REL1_44 snapshots for any of the extensions and skins in '
				. '%s. MediaWiki 1.44 is probably no longer supported (ExtensionDistributor only keeps branches '
				. 'of supported releases). Branches it offers: master, REL1_46.',
		];
	}

	#[DataProvider( 'provideEarlyFailures' )]
	public function testFailsBeforeDownloading( ?callable $setUp, array $input, string $expected ): void {
		if ( $setUp ) {
			$setUp( $this );
		}
		$before = $this->getTargetContents();

		[ $status, $output ] = $this->runCommand( $input );

		$this->assertSame( Command::FAILURE, $status );
		$this->assertStringContainsString( sprintf( $expected, "$this->repoDir/dependencies.txt" ), $output );
		$this->assertSame( [], $this->downloader->requested, 'Nothing is downloaded' );
		$this->assertSame( $before, $this->getTargetContents(), 'The target is not changed' );
	}

	public function testChecksRequiredCommands(): void {
		$this->shell = $this->createMock( Shell::class );
		$this->shell->expects( $this->once() )
			->method( 'requireCommands' )
			->with( [ 'tar', 'gpg', 'git' ] )
			->willThrowException( new PrepareException( 'Required commands not found: gpg' ) );

		[ $status, $output ] = $this->runCommand( [ 'version' => '1.46', '--skip-gpg' => false ] );

		$this->assertSame( Command::FAILURE, $status );
		$this->assertStringContainsString( 'Required commands not found: gpg', $output );
		$this->assertSame( [], $this->downloader->requested );
	}

	public function testReleaseBranchCheckRespectsBranchOverrides(): void {
		// Nothing uses the release branch, so its absence does not matter
		$this->writeDependencies( 'extension UserMerge master' );

		[ $status ] = $this->runCommand( [ 'version' => '1.44.6' ] );

		// Gets as far as downloading core (which the fixtures do not have for 1.44.6)
		$this->assertSame( Command::FAILURE, $status );
		$this->assertSame( [ 'https://releases.wikimedia.org/mediawiki/1.44/mediawiki-1.44.6.tar.gz' ], $this->downloader->requested );
	}

	public function testUnavailableBranch(): void {
		$this->writeDependencies( str_replace( 'extension UserMerge', 'extension UserMerge REL1_10', self::DEPENDENCIES ) );

		[ $status, $output ] = $this->runCommand();
		$this->assertSame( Command::FAILURE, $status );
		$this->assertStringContainsString( 'extension UserMerge has no REL1_10 snapshot (available: REL1_46, master)', $output );
		$this->assertSame( [], $this->getTargetContents(), 'Staging directory is cleaned up' );

		[ $status, $output ] = $this->runCommand( [ '--keep-staging' => true ] );
		$this->assertSame( Command::FAILURE, $status );
		$this->assertStringContainsString( 'Staging directory kept at', $output );
		$staging = $this->getTargetContents();
		$this->assertCount( 1, $staging );
		$this->assertStringStartsWith( '.staging-1.46.2-', $staging[0] );

		// A later run points out the leftover
		$this->writeDependencies( self::DEPENDENCIES );
		[ $status, $output ] = $this->runCommand();
		$this->assertSame( Command::SUCCESS, $status, $output );
		$this->assertStringContainsString( "$staging[0] is left over from an interrupted run", $output );
	}

	public function testDownloadFailureCleansUp(): void {
		unset( $this->downloader->files[self::CORE_URL] );

		[ $status, $output ] = $this->runCommand();

		$this->assertSame( Command::FAILURE, $status );
		$this->assertStringContainsString( 'returned error: 404', $output );
		$this->assertSame( [], $this->getTargetContents() );
	}

	public function testUnwritableTarget(): void {
		chmod( $this->target, 0555 );
		try {
			if ( is_writable( $this->target ) ) {
				$this->markTestSkipped( 'File permissions are not enforced (running as root?)' );
			}
			[ $status, $output ] = $this->runCommand();
		} finally {
			chmod( $this->target, 0755 );
		}

		$this->assertSame( Command::FAILURE, $status );
		$this->assertStringContainsString( 'Unable to create staging directory', $output );
	}
}
