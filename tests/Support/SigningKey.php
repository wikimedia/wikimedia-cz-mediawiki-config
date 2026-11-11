<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Support;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use WMCZ\Upgrade\PrepareException;
use WMCZ\Upgrade\Shell;

/**
 * A throwaway GPG key (in its own GnuPG home) for signing test fixtures.
 */
final class SigningKey {

	/**
	 * @param string $home GnuPG home (must not exist yet)
	 * @param string $uid E.g. "Test <test@example.org>"
	 */
	public function __construct( private readonly string $home, private readonly string $uid ) {
		mkdir( $home, 0700, true );
		$this->gpg( '--quick-generate-key', $uid, 'ed25519', 'sign', 'never' );
	}

	public static function skipIfUnavailable( TestCase $test ): void {
		$finder = new ExecutableFinder();
		if ( $finder->find( 'gpg' ) === null || $finder->find( 'gpgconf' ) === null ) {
			$test->markTestSkipped( 'GnuPG is not installed' );
		}
	}

	public function exportPublicKey( string $file ): void {
		$this->gpg( '--armor', '--output', $file, '--export', $this->uid );
	}

	public function sign( string $file, string $signatureFile ): void {
		$this->gpg( '--output', $signatureFile, '--detach-sign', $file );
	}

	/**
	 * Stop the GnuPG daemons started for this key's home.
	 */
	public function shutdown(): void {
		try {
			( new Shell() )->run( [ 'gpgconf', '--homedir', $this->home, '--kill', 'all' ] );
		} catch ( PrepareException ) {
			// Nothing to stop
		}
	}

	private function gpg( string ...$args ): void {
		( new Shell() )->run( [
			'gpg', '--homedir', $this->home, '--batch', '--no-tty', '--pinentry-mode', 'loopback', '--passphrase', '', ...$args,
		] );
	}
}
