<?php

declare( strict_types=1 );

namespace WMCZ\Upgrade;

/**
 * Sanity checks of a prepared MediaWiki tree.
 */
final class TreeChecker {

	/**
	 * @param string $root MediaWiki root directory
	 * @param array<string,Dependency> $dependencies As returned by Dependency::readList()
	 * @return string[] Problems found, as human-readable warnings
	 */
	public static function check( string $root, array $dependencies ): array {
		$warnings = [];
		foreach ( $dependencies as $dependency ) {
			$dir = "$root/" . $dependency->getPath();
			$manifestFile = "$dir/$dependency->type.json";
			$manifest = json_decode( (string)@file_get_contents( $manifestFile ), true );
			if ( file_exists( "$dir/MOVED_TO_CORE" ) ) {
				$warnings[] = "$dependency was moved to MediaWiki core and is only a stub now; "
					. 'stop loading it in CommonSettings.php and remove it from dependencies.txt.';
				continue;
			} elseif ( !file_exists( $manifestFile ) ) {
				$warnings[] = "$dependency has no $dependency->type.json; loading it will fail.";
				continue;
			} elseif ( !is_array( $manifest ) ) {
				$warnings[] = "$dependency: unable to parse $manifestFile";
				continue;
			}

			// Required extensions and skins need to be loaded too
			foreach ( Dependency::TYPES as $type => $typeDir ) {
				$required = $manifest['requires'][$typeDir] ?? [];
				foreach ( array_keys( is_array( $required ) ? $required : [] ) as $name ) {
					if ( !isset( $dependencies["$type $name"] ) ) {
						$warnings[] = "$dependency requires $type $name, which is not listed in dependencies.txt"
							. ( is_dir( "$root/$typeDir/$name" ) ? ' (it is bundled with core, but needs to be loaded too)' : '' )
							. '.';
					}
				}
			}

			// ExtensionDistributor snapshots ship their Composer dependencies in their own
			// vendor/, bundled extensions have theirs in core's vendor/
			if ( file_exists( "$dir/composer.json" ) && !file_exists( "$dir/vendor/autoload.php" ) ) {
				$required = json_decode( (string)file_get_contents( "$dir/composer.json" ), true )['require'] ?? [];
				$missing = array_filter(
					array_map( 'strval', array_keys( is_array( $required ) ? $required : [] ) ),
					static fn ( string $package ) => !preg_match( '!^(php|ext-.*|composer/installers)$!', $package )
						&& !is_dir( "$root/vendor/$package" )
				);
				if ( $missing ) {
					$warnings[] = "$dependency needs Composer packages that are not in the tree (" . implode( ', ', $missing )
						. '); add it to composer.local.json and run "composer update --no-dev" in the tree.';
				}
			}
		}
		return $warnings;
	}
}
