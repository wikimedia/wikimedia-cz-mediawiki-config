<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Support;

use WMCZ\Upgrade\Downloader;
use WMCZ\Upgrade\PrepareException;

/**
 * Offline stand-in for the Downloader: serves local files and canned ExtensionDistributor data.
 */
class FakeDownloader extends Downloader {

	/** @var string[] URLs downloaded so far */
	public array $requested = [];

	/**
	 * @param array<string,string> $files URL => local file to serve for it
	 * @param array<string,array<string,array<string,string>>> $snapshots type => name => [ branch => URL ]
	 */
	public function __construct( public array $files = [], private readonly array $snapshots = [] ) {
	}

	public function toFile( string $url, string $file ): void {
		$this->requested[] = $url;
		if ( !isset( $this->files[$url] ) ) {
			throw new PrepareException( "Download of $url failed: The requested URL returned error: 404" );
		}
		copy( $this->files[$url], $file );
	}

	public function getSnapshots( string $type, array $names ): array {
		return array_intersect_key( $this->snapshots[$type] ?? [], array_flip( $names ) );
	}
}
