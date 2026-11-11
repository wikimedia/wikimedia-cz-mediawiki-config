<?php

declare( strict_types=1 );

namespace WMCZ\Upgrade;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs local tools (directly, without a shell in between).
 */
class Shell {

	/**
	 * @param string[] $command
	 * @return string Standard output
	 */
	public function run( array $command ): string {
		$process = new Process( $command, timeout: null );
		$process->run();
		if ( !$process->isSuccessful() ) {
			$stderr = trim( $process->getErrorOutput() );
			throw new PrepareException( sprintf(
				'Command failed with exit code %d: %s%s',
				$process->getExitCode(),
				$process->getCommandLine(),
				$stderr === '' ? '' : "\n$stderr"
			) );
		}
		return $process->getOutput();
	}

	/**
	 * @param string[] $commands
	 */
	public function requireCommands( array $commands ): void {
		$finder = new ExecutableFinder();
		$missing = array_filter( $commands, static fn ( string $command ) => $finder->find( $command ) === null );
		if ( $missing ) {
			throw new PrepareException( 'Required commands not found: ' . implode( ', ', $missing ) );
		}
	}

	/**
	 * Extract a tarball with a single top-level directory (as MediaWiki releases and
	 * ExtensionDistributor snapshots are) to $destination, which must not exist yet.
	 */
	public function extractTarball( string $tarball, string $destination ): void {
		$filesystem = new Filesystem();
		$scratch = "$destination.extract";
		$filesystem->mkdir( $scratch );
		$this->run( [ 'tar', '--extract', '--gzip', '--no-same-owner', '--file', $tarball, '--directory', $scratch ] );

		$entries = array_diff( scandir( $scratch ) ?: [], [ '.', '..' ] );
		$topLevel = $scratch . '/' . reset( $entries );
		if ( count( $entries ) !== 1 || !is_dir( $topLevel ) || is_link( $topLevel ) ) {
			throw new PrepareException( "Unexpected layout of $tarball: expected a single top-level directory" );
		}
		$filesystem->rename( $topLevel, $destination );
		$filesystem->remove( $scratch );
	}

	/**
	 * Verify a detached signature, trusting exactly the keys in $keysFile. A new GnuPG home
	 * is used, so the user's own keyring is neither used nor modified.
	 *
	 * @return string Who made the signature
	 */
	public function verifySignature( string $file, string $signatureFile, string $keysFile, string $gnupgHome ): string {
		if ( !mkdir( $gnupgHome, 0700 ) ) {
			throw new PrepareException( "Unable to create $gnupgHome" );
		}
		$gpg = [ 'gpg', '--homedir', $gnupgHome, '--batch', '--no-tty' ];
		try {
			$this->run( [ ...$gpg, '--import', $keysFile ] );
			// The keyring only contains the keys we chose to trust
			$status = $this->run( [ ...$gpg, '--trust-model', 'always', '--status-fd', '1', '--verify', $signatureFile, $file ] );
		} catch ( PrepareException $e ) {
			$status = $e->getMessage();
		} finally {
			// GnuPG >= 2.1 may have started daemons for the new home; stop them
			try {
				$this->run( [ 'gpgconf', '--homedir', $gnupgHome, '--kill', 'all' ] );
			} catch ( PrepareException ) {
			}
		}

		if ( !preg_match( '/^\[GNUPG:\] VALIDSIG /m', $status ) || !preg_match( '/^\[GNUPG:\] GOODSIG \S+ (.*)$/m', $status, $m ) ) {
			throw new PrepareException( "GPG signature verification of $file FAILED\n$status" );
		}
		return $m[1];
	}
}
