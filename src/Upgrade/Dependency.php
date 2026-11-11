<?php

declare( strict_types=1 );

namespace WMCZ\Upgrade;

/**
 * An extension or skin the configuration needs.
 */
final class Dependency {

	/** Type => directory holding that type in MediaWiki core */
	public const TYPES = [ 'extension' => 'extensions', 'skin' => 'skins' ];

	private const NAME_REGEX = '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/';

	/**
	 * @param string $type "extension" or "skin"
	 * @param string $name As used by wfLoadExtension() / wfLoadSkin()
	 * @param string|null $branch ExtensionDistributor branch to use instead of the release branch
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $name,
		public readonly ?string $branch = null
	) {
	}

	/**
	 * Read dependencies.txt: one "<type> <name> [<branch>]" entry per line, "#" starts a comment.
	 *
	 * @return array<string,self> Keyed by "<type> <name>"
	 */
	public static function readList( string $file ): array {
		$lines = @file( $file, FILE_IGNORE_NEW_LINES );
		if ( $lines === false ) {
			throw new PrepareException( "Unable to read $file" );
		}

		$list = [];
		foreach ( $lines as $i => $line ) {
			$line = trim( (string)preg_replace( '/#.*/', '', $line ) );
			if ( $line === '' ) {
				continue;
			}
			$error = match ( true ) {
				!preg_match( '/^(\S+)\s+(\S+)(?:\s+(\S+))?$/', $line, $m ) =>
					"expected \"<type> <name> [<branch>]\", got \"$line\"",
				!isset( self::TYPES[$m[1]] ) => "unknown type \"$m[1]\" (expected \"extension\" or \"skin\")",
				!preg_match( self::NAME_REGEX, $m[2] ) => "invalid name \"$m[2]\"",
				isset( $m[3] ) && !preg_match( self::NAME_REGEX, $m[3] ) => "invalid branch \"$m[3]\"",
				isset( $list["$m[1] $m[2]"] ) => "$m[1] $m[2] is listed more than once",
				default => null,
			};
			if ( $error !== null ) {
				throw new PrepareException( "$file:" . ( $i + 1 ) . ": $error" );
			}
			$list["$m[1] $m[2]"] = new self( $m[1], $m[2], $m[3] ?? null );
		}

		if ( !$list ) {
			throw new PrepareException( "$file does not list any extensions or skins" );
		}
		return $list;
	}

	/**
	 * @return string Path relative to the MediaWiki root, e.g. "extensions/Cite"
	 */
	public function getPath(): string {
		return self::TYPES[$this->type] . "/$this->name";
	}

	public function __toString(): string {
		return "$this->type $this->name";
	}
}
