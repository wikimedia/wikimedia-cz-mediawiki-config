<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use Symfony\Component\Filesystem\Filesystem;
use WMCZ\Upgrade\Shell;

/**
 * Gives a test case a fresh temporary directory (removed after each test) and file helpers.
 */
trait TemporaryFilesTrait {

	private ?string $temporaryDirectory = null;

	protected function getTemporaryDirectory(): string {
		if ( $this->temporaryDirectory === null ) {
			$dir = sys_get_temp_dir() . '/wmcz-test-' . bin2hex( random_bytes( 8 ) );
			mkdir( $dir, 0700 );
			$this->temporaryDirectory = (string)realpath( $dir );
		}
		return $this->temporaryDirectory;
	}

	/**
	 * Write a file, creating parent directories as needed.
	 */
	protected function writeFile( string $path, string $content = '' ): void {
		( new Filesystem() )->dumpFile( $path, $content );
	}

	/**
	 * @param string $tarball Path of the .tar.gz to create
	 * @param array<string,string> $files Path inside the tarball => content
	 */
	protected function createTarball( string $tarball, array $files ): void {
		foreach ( $files as $path => $content ) {
			$this->writeFile( "$tarball.src/$path", $content );
		}
		$entries = array_diff( scandir( "$tarball.src" ) ?: [], [ '.', '..' ] );
		( new Shell() )->run( [ 'tar', '--create', '--gzip', '--file', $tarball, '--directory', "$tarball.src", '--', ...$entries ] );
		( new Filesystem() )->remove( "$tarball.src" );
	}

	#[After]
	protected function removeTemporaryDirectory(): void {
		if ( $this->temporaryDirectory !== null ) {
			( new Filesystem() )->remove( $this->temporaryDirectory );
			$this->temporaryDirectory = null;
		}
	}
}
