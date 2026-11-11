<?php

declare( strict_types=1 );

namespace WMCZ\Upgrade;

/**
 * A released version of MediaWiki core.
 *
 * @see https://www.mediawiki.org/wiki/Download
 */
final class MediaWikiRelease {

	/** Keys release tarballs are signed with */
	public const KEYS_URL = 'https://www.mediawiki.org/keys/keys.txt';

	private const CORE_GIT_URL = 'https://gerrit.wikimedia.org/r/mediawiki/core.git';
	private const EXACT_REGEX = '/^(\d+)\.(\d+)\.\d+(?:-rc\.\d+)?$/';

	/** Release series, e.g. "1.46" */
	public readonly string $majorMinor;

	/** Release branch used by extensions and skins, e.g. "REL1_46" */
	public readonly string $branch;

	/**
	 * @param string $version Exact version, e.g. "1.46.2" or "1.47.0-rc.0"
	 */
	public function __construct( public readonly string $version ) {
		if ( !preg_match( self::EXACT_REGEX, $version, $m ) ) {
			throw new PrepareException( "Invalid MediaWiki version \"$version\"" );
		}
		$this->majorMinor = "$m[1].$m[2]";
		$this->branch = "REL$m[1]_$m[2]";
	}

	/**
	 * Whether resolving $input needs to look up releases (and needs git for that).
	 */
	public static function needsLookup( string $input ): bool {
		return !preg_match( self::EXACT_REGEX, $input );
	}

	/**
	 * Turn "1.46" into the latest 1.46.x release; exact versions are kept as they are.
	 */
	public static function resolve( string $input, Shell $shell ): self {
		if ( !self::needsLookup( $input ) ) {
			return new self( $input );
		}
		if ( !preg_match( '/^\d+\.\d+$/', $input ) ) {
			throw new PrepareException( "Invalid version \"$input\"; expected e.g. 1.46 or 1.46.2" );
		}

		$refs = $shell->run( [ 'git', 'ls-remote', '--tags', '--refs', self::CORE_GIT_URL, "refs/tags/$input.*" ] );
		preg_match_all( '!\trefs/tags/(' . preg_quote( $input, '!' ) . '\.\d+)$!m', $refs, $m );
		if ( !$m[1] ) {
			throw new PrepareException(
				"No $input.x release found. To prepare a release candidate, pass its exact version (e.g. $input.0-rc.0)."
			);
		}
		usort( $m[1], 'version_compare' );
		return new self( end( $m[1] ) );
	}

	public function getTarballUrl(): string {
		return "https://releases.wikimedia.org/mediawiki/$this->majorMinor/mediawiki-$this->version.tar.gz";
	}
}
