<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Upgrade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WMCZ\Upgrade\MediaWikiRelease;
use WMCZ\Upgrade\PrepareException;
use WMCZ\Upgrade\Shell;

#[CoversClass( MediaWikiRelease::class )]
final class MediaWikiReleaseTest extends TestCase {

	public static function provideExactVersions(): iterable {
		yield 'stable' => [ '1.46.2', '1.46', 'REL1_46' ];
		yield 'two-digit patch' => [ '1.43.11', '1.43', 'REL1_43' ];
		yield 'release candidate' => [ '1.47.0-rc.0', '1.47', 'REL1_47' ];
	}

	#[DataProvider( 'provideExactVersions' )]
	public function testExactVersion( string $version, string $majorMinor, string $branch ): void {
		$release = new MediaWikiRelease( $version );

		$this->assertSame( $majorMinor, $release->majorMinor );
		$this->assertSame( $branch, $release->branch );
		$this->assertSame(
			"https://releases.wikimedia.org/mediawiki/$majorMinor/mediawiki-$version.tar.gz", $release->getTarballUrl()
		);
		$this->assertFalse( MediaWikiRelease::needsLookup( $version ) );
		$this->assertSame( $version, MediaWikiRelease::resolve( $version, $this->createStub( Shell::class ) )->version );
	}

	public static function provideInvalidExactVersions(): iterable {
		return [ [ '1.46' ], [ '1.46.2-beta' ], [ '1.46.2 ' ], [ 'v1.46.2' ], [ '1.46.2/../..' ] ];
	}

	#[DataProvider( 'provideInvalidExactVersions' )]
	public function testInvalidExactVersion( string $version ): void {
		$this->expectException( PrepareException::class );
		new MediaWikiRelease( $version );
	}

	private function resolve( string $input, string $tags ): string {
		$shell = $this->createMock( Shell::class );
		$shell->expects( $this->once() )
			->method( 'run' )
			->with( [
				'git', 'ls-remote', '--tags', '--refs',
				'https://gerrit.wikimedia.org/r/mediawiki/core.git', "refs/tags/$input.*",
			] )
			->willReturn( $tags );
		return MediaWikiRelease::resolve( $input, $shell )->version;
	}

	public function testResolvePicksLatestRelease(): void {
		$tags = "a\trefs/tags/1.46.0\nb\trefs/tags/1.46.0-rc.0\nc\trefs/tags/1.46.9\nd\trefs/tags/1.46.10\ne\trefs/tags/1.46.2\n";
		$this->assertSame( '1.46.10', $this->resolve( '1.46', $tags ) );
	}

	public function testResolveIgnoresOtherBranches(): void {
		$this->assertSame( '1.4.5', $this->resolve( '1.4', "a\trefs/tags/1.4.5\nb\trefs/tags/1.46.0\n" ) );
	}

	public function testResolveWithoutStableRelease(): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'No 1.48.x release found' );
		$this->resolve( '1.48', "a\trefs/tags/1.48.0-rc.0\n" );
	}

	public function testResolveInvalidInput(): void {
		$shell = $this->createMock( Shell::class );
		$shell->expects( $this->never() )->method( 'run' );

		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'Invalid version "latest"' );
		MediaWikiRelease::resolve( 'latest', $shell );
	}
}
