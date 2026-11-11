<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Upgrade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WMCZ\Tests\Support\SigningKey;
use WMCZ\Tests\Support\TemporaryFilesTrait;
use WMCZ\Upgrade\PrepareException;
use WMCZ\Upgrade\Shell;

#[CoversClass( Shell::class )]
final class ShellTest extends TestCase {
	use TemporaryFilesTrait;

	/** @var SigningKey[] */
	private array $keys = [];

	protected function tearDown(): void {
		foreach ( $this->keys as $key ) {
			$key->shutdown();
		}
	}

	public function testRun(): void {
		$this->assertSame( "hello\n", ( new Shell() )->run( [ 'echo', 'hello' ] ) );

		$argument = '$HOME; echo injected `id` "quoted"';
		$this->assertSame( $argument, ( new Shell() )->run( [ 'printf', '%s', $argument ] ), 'No shell in between' );
	}

	public function testRunFailure(): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessageMatches( '/exit code 3: .*\nit broke$/' );
		( new Shell() )->run( [ 'sh', '-c', 'echo "it broke" >&2; exit 3' ] );
	}

	public function testRequireCommands(): void {
		( new Shell() )->requireCommands( [ 'sh', 'echo' ] );

		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'Required commands not found: wmcz-no-such-command' );
		( new Shell() )->requireCommands( [ 'sh', 'wmcz-no-such-command' ] );
	}

	public function testExtractTarball(): void {
		$tmp = $this->getTemporaryDirectory();
		$this->createTarball( "$tmp/foo.tar.gz", [ 'Foo-REL1_46/extension.json' => '{}', 'Foo-REL1_46/i18n/en.json' => '{}' ] );

		( new Shell() )->extractTarball( "$tmp/foo.tar.gz", "$tmp/extensions/Foo" );

		$this->assertFileExists( "$tmp/extensions/Foo/extension.json" );
		$this->assertFileExists( "$tmp/extensions/Foo/i18n/en.json" );
		$this->assertSame( [ 'Foo' ], array_values( array_diff( scandir( "$tmp/extensions" ), [ '.', '..' ] ) ) );
	}

	public static function provideBadTarballs(): iterable {
		yield 'multiple top-level entries' => [ [ 'Foo/extension.json' => '{}', 'Bar/extension.json' => '{}' ] ];
		yield 'top-level file' => [ [ 'extension.json' => '{}' ] ];
		yield 'not a tarball' => [ null ];
	}

	#[DataProvider( 'provideBadTarballs' )]
	public function testExtractBadTarball( ?array $files ): void {
		$tmp = $this->getTemporaryDirectory();
		if ( $files === null ) {
			$this->writeFile( "$tmp/foo.tar.gz", '<html>404 Not Found</html>' );
		} else {
			$this->createTarball( "$tmp/foo.tar.gz", $files );
		}

		$this->expectException( PrepareException::class );
		( new Shell() )->extractTarball( "$tmp/foo.tar.gz", "$tmp/extensions/Foo" );
	}

	/**
	 * @param callable $tamper Called with the trusted key, the signed file and its signature;
	 *   returns the key to trust
	 */
	private function verify( callable $tamper ): string {
		SigningKey::skipIfUnavailable( $this );
		$tmp = $this->getTemporaryDirectory();
		$this->keys[] = $signer = new SigningKey( "$tmp/signer", 'Test Release Signer <signer@example.org>' );
		$this->writeFile( "$tmp/release.tar.gz", 'release' );
		$signer->sign( "$tmp/release.tar.gz", "$tmp/release.tar.gz.sig" );

		$tamper( $signer, "$tmp/release.tar.gz", "$tmp/release.tar.gz.sig" )->exportPublicKey( "$tmp/keys.txt" );
		return ( new Shell() )->verifySignature( "$tmp/release.tar.gz", "$tmp/release.tar.gz.sig", "$tmp/keys.txt", "$tmp/gnupg" );
	}

	public function testVerifySignature(): void {
		$this->assertSame( 'Test Release Signer <signer@example.org>', $this->verify( static fn ( $signer ) => $signer ) );
		$this->assertSame( 0700, fileperms( $this->getTemporaryDirectory() . '/gnupg' ) & 0777 );
	}

	public static function provideBadSignatures(): iterable {
		yield 'tampered file' => [ static function ( SigningKey $signer, string $file ) {
			file_put_contents( $file, 'evil' );
			return $signer;
		} ];
		yield 'garbage signature' => [ static function ( SigningKey $signer, string $file, string $signature ) {
			file_put_contents( $signature, 'not a signature' );
			return $signer;
		} ];
		yield 'untrusted key' => [ static fn ( SigningKey $signer, string $file ) =>
			new SigningKey( dirname( $file ) . '/stranger', 'Someone Else <someone@example.org>' ) ];
	}

	#[DataProvider( 'provideBadSignatures' )]
	public function testVerifyBadSignature( callable $tamper ): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'FAILED' );
		$this->verify( function ( ...$args ) use ( $tamper ) {
			return $this->keys[] = $tamper( ...$args );
		} );
	}
}
