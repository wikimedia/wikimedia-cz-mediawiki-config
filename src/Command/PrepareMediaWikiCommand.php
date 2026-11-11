<?php

declare( strict_types=1 );

namespace WMCZ\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use WMCZ\Upgrade\Dependency;
use WMCZ\Upgrade\Downloader;
use WMCZ\Upgrade\MediaWikiRelease;
use WMCZ\Upgrade\PrepareException;
use WMCZ\Upgrade\Shell;
use WMCZ\Upgrade\TreeChecker;

#[AsCommand(
	name: self::NAME,
	description: 'Prepare a fresh MediaWiki tree (core, extensions and skins) for an upgrade',
	help: <<<'HELP'
		Downloads MediaWiki core (the official, GPG-signed release tarball) and every
		extension and skin listed in dependencies.txt that is not bundled with core
		(from ExtensionDistributor), and assembles them in <comment><target>/<version></comment>, with
		LocalSettings.php symlinked to this repository's CommonSettings.php.

		The tree is built in a staging directory and only moved into place once
		everything succeeded. The command never modifies an existing version directory,
		never touches the "current" symlink and never runs update.php; switching to the
		prepared version is left to the administrator.

		Requires the PHP curl extension, tar, gpg and (only when <comment>version</comment> is "X.Y") git.

		  <info>php %command.full_name% 1.46</info>
		HELP
)]
class PrepareMediaWikiCommand {

	private const NAME = 'mediawiki:prepare';

	private readonly Shell $shell;
	private readonly Downloader $downloader;
	private readonly Filesystem $filesystem;
	private SymfonyStyle $io;

	/** @var string[] Lines of PREPARED.txt */
	private array $manifest = [];

	/** @var string[] */
	private array $warnings = [];

	/**
	 * @param string $repoDir Root of the mediawiki-config repository
	 */
	public function __construct( private readonly string $repoDir, ?Shell $shell = null, ?Downloader $downloader = null ) {
		$this->shell = $shell ?? new Shell();
		$this->downloader = $downloader ?? new Downloader();
		$this->filesystem = new Filesystem();
	}

	public function __invoke(
		SymfonyStyle $io,
		#[Argument( 'MediaWiki version: "1.46" for the latest 1.46.x release, or an exact version '
			. 'such as "1.46.2" or "1.47.0-rc.0"' )]
		string $version,
		#[Option( 'Directory holding the version directories [default: "mw" next to this repository]' )]
		?string $target = null,
		#[Option( 'List of extensions and skins to install [default: dependencies.txt in this repository]' )]
		?string $deps = null,
		#[Option( 'Armored GPG keys to verify core with [default: download ' . MediaWikiRelease::KEYS_URL . ']' )]
		?string $keys = null,
		#[Option( 'Do not verify the signature of the core tarball' )]
		bool $skipGpg = false,
		#[Option( 'Keep the staging directory if something fails' )]
		bool $keepStaging = false,
	): int {
		$this->io = $io;
		$this->manifest = $this->warnings = [];
		try {
			$this->prepare(
				$version,
				$target ?? dirname( $this->repoDir ) . '/mw',
				$deps ?? "$this->repoDir/dependencies.txt",
				$skipGpg ? false : $keys,
				$keepStaging
			);
			return Command::SUCCESS;
		} catch ( PrepareException | IOExceptionInterface $e ) {
			// Expected failures; no need for the exception class, file and line
			$io->error( $e->getMessage() );
			return Command::FAILURE;
		}
	}

	/**
	 * @param string|false|null $keysFile GPG keys to verify core with, null to download them,
	 *   false to skip verification
	 */
	private function prepare(
		string $versionInput, string $target, string $depsFile, string|false|null $keysFile, bool $keepStaging
	): void {
		$commonSettings = realpath( "$this->repoDir/wmcz-config/CommonSettings.php" );
		if ( $commonSettings === false ) {
			throw new PrepareException( 'Unable to locate wmcz-config/CommonSettings.php' );
		}
		if ( is_string( $keysFile ) && !is_readable( $keysFile ) ) {
			throw new PrepareException( "Unable to read $keysFile" );
		}
		$this->shell->requireCommands( [
			'tar',
			...( $keysFile === false ? [] : [ 'gpg' ] ),
			...( MediaWikiRelease::needsLookup( $versionInput ) ? [ 'git' ] : [] ),
		] );
		$target = $this->checkTarget( $target );

		// Check everything that can be checked before downloading core
		$release = MediaWikiRelease::resolve( $versionInput, $this->shell );
		$finalDir = "$target/$release->version";
		if ( file_exists( $finalDir ) || is_link( $finalDir ) ) {
			throw new PrepareException( "$finalDir already exists; remove it first to prepare it again." );
		}
		$this->io->title( "Preparing MediaWiki $release->version" );
		$this->io->text( "Target: $finalDir" );
		$dependencies = Dependency::readList( $depsFile );
		$snapshots = $this->getSnapshots( $release, $dependencies, $depsFile );

		// Random suffix: never reuse a staging directory left over by an earlier run
		$staging = "$target/.staging-$release->version-" . bin2hex( random_bytes( 4 ) );
		if ( !@mkdir( $staging ) ) {
			throw new PrepareException( "Unable to create staging directory $staging" );
		}
		$tree = "$staging/tree";
		$success = false;
		try {
			$this->manifest = [
				"MediaWiki $release->version, prepared by bin/console " . self::NAME . ' on ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'',
			];
			$this->installCore( $release, $staging, $keysFile );
			$this->installDependencies( $dependencies, $snapshots, $release->branch, $staging, $depsFile );
			array_push( $this->warnings, ...TreeChecker::check( $tree, $dependencies ) );

			if ( file_exists( "$tree/LocalSettings.php" ) || !symlink( $commonSettings, "$tree/LocalSettings.php" ) ) {
				throw new PrepareException( "Unable to create $tree/LocalSettings.php" );
			}
			$this->filesystem->dumpFile( "$tree/PREPARED.txt", implode( "\n", $this->manifest ) . "\n" );
			$this->filesystem->rename( $tree, $finalDir );
			$success = true;
		} finally {
			if ( $success || !$keepStaging ) {
				$this->filesystem->remove( $staging );
			} else {
				$this->io->note( "Staging directory kept at $staging" );
			}
		}

		$this->io->success( "MediaWiki $release->version is ready in $finalDir" );
		if ( $this->warnings ) {
			$this->io->warning( [ 'Review before switching:', ...$this->warnings ] );
		}
		$this->io->text( [
			'Nothing has been switched yet. To upgrade:',
			"  1. Point $target/current at $release->version "
				. "(e.g. <info>ln -sfn $release->version $target/current</info>).",
			'  2. Run <info>php maintenance/run.php update --wiki=<dbname></info> for every wiki in dblists/all.dblist.',
		] );
	}

	/**
	 * @return string Normalized path of the target directory
	 */
	private function checkTarget( string $target ): string {
		if ( !is_dir( $target ) ) {
			throw new PrepareException( "Target directory $target does not exist" );
		}
		$target = (string)realpath( $target );
		if ( is_dir( "$target/includes" ) && file_exists( "$target/index.php" ) ) {
			throw new PrepareException(
				"$target looks like a MediaWiki installation, not a directory of versions.\n"
				. "Move the existing installation into a version subdirectory first, for example:\n"
				. "  mkdir $target.new && mv $target $target.new/<old version> && mv $target.new $target\n"
				. "  ln -s <old version> $target/current\n"
				. "and point the web server at $target/current."
			);
		}
		foreach ( glob( "$target/.staging-*" ) ?: [] as $stale ) {
			$this->io->note( "$stale is left over from an interrupted run; remove it when no run is in progress." );
		}
		return $target;
	}

	/**
	 * Ask ExtensionDistributor about all dependencies, so that typos and unsupported
	 * releases are caught before downloading core.
	 *
	 * @param MediaWikiRelease $release
	 * @param array<string,Dependency> $dependencies
	 * @param string $depsFile
	 * @return array<string,array<string,array<string,string>>> type => name => [ branch => URL ]
	 */
	private function getSnapshots( MediaWikiRelease $release, array $dependencies, string $depsFile ): array {
		$this->io->text( 'Querying ExtensionDistributor for ' . count( $dependencies ) . ' extensions and skins...' );
		$snapshots = [];
		foreach ( array_keys( Dependency::TYPES ) as $type ) {
			$names = array_column( array_filter( $dependencies, static fn ( $d ) => $d->type === $type ), 'name' );
			$snapshots[$type] = $names ? $this->downloader->getSnapshots( $type, $names ) : [];
		}

		$unknown = array_filter( $dependencies, static fn ( $d ) => !isset( $snapshots[$d->type][$d->name] ) );
		if ( $unknown ) {
			throw new PrepareException( 'ExtensionDistributor does not know: ' . implode( ', ', $unknown )
				. " (check the spelling in $depsFile; names are case-sensitive)" );
		}

		// ExtensionDistributor only keeps branches of supported releases. Individual missing snapshots
		// are reported once core is extracted, as bundled dependencies do not need one.
		$onReleaseBranch = array_filter( $dependencies, static fn ( $d ) => $d->branch === null );
		$offered = array_filter( $onReleaseBranch, static fn ( $d ) => isset( $snapshots[$d->type][$d->name][$release->branch] ) );
		if ( $onReleaseBranch && !$offered ) {
			$branches = [];
			foreach ( $snapshots as $byName ) {
				foreach ( $byName as $byBranch ) {
					$branches += array_flip( array_keys( $byBranch ) );
				}
			}
			$branches = array_keys( $branches );
			sort( $branches, SORT_NATURAL | SORT_FLAG_CASE );
			throw new PrepareException(
				"ExtensionDistributor offers no $release->branch snapshots for any of the extensions and skins in "
				. "$depsFile. MediaWiki $release->majorMinor is probably no longer supported (ExtensionDistributor "
				. 'only keeps branches of supported releases). Branches it offers: ' . implode( ', ', $branches ) . '.'
			);
		}
		return $snapshots;
	}

	/**
	 * @param MediaWikiRelease $release
	 * @param string $staging
	 * @param string|false|null $keysFile See prepare()
	 */
	private function installCore( MediaWikiRelease $release, string $staging, string|false|null $keysFile ): void {
		$url = $release->getTarballUrl();
		$this->io->text( "Downloading $url" );
		$this->install( $url, $staging, "$staging/tree", 'core', 'MediaWiki', function ( string $tarball ) use ( $url, $staging, $keysFile ) {
			if ( $keysFile === false ) {
				$this->io->caution( 'Skipping GPG signature verification of MediaWiki core' );
				return;
			}
			$this->downloader->toFile( "$url.sig", "$tarball.sig" );
			if ( $keysFile === null ) {
				$keysFile = "$staging/keys.txt";
				$this->downloader->toFile( MediaWikiRelease::KEYS_URL, $keysFile );
			}
			$signer = $this->shell->verifySignature( $tarball, "$tarball.sig", $keysFile, "$staging/gnupg" );
			$this->io->text( "GPG signature OK (signed by $signer)" );
		} );
	}

	/**
	 * Download the dependencies that are not bundled with core.
	 *
	 * @param array<string,Dependency> $dependencies
	 * @param array<string,array<string,array<string,string>>> $snapshots From getSnapshots()
	 * @param string $releaseBranch
	 * @param string $staging
	 * @param string $depsFile
	 */
	private function installDependencies(
		array $dependencies, array $snapshots, string $releaseBranch, string $staging, string $depsFile
	): void {
		$unavailable = [];
		foreach ( $dependencies as $dependency ) {
			$destination = "$staging/tree/" . $dependency->getPath();
			if ( file_exists( $destination ) ) {
				$this->io->text( "  $dependency: bundled with core" );
				$this->record( $dependency->type, $dependency->name, 'bundled' );
				if ( $dependency->branch !== null ) {
					$this->warnings[] = "$dependency is bundled with core; branch \"$dependency->branch\" "
						. 'from dependencies.txt was ignored.';
				}
				continue;
			}

			$branch = $dependency->branch ?? $releaseBranch;
			$branches = $snapshots[$dependency->type][$dependency->name];
			if ( !isset( $branches[$branch] ) ) {
				$unavailable[] = "$dependency has no $branch snapshot (available: " . implode( ', ', array_keys( $branches ) ) . ')';
				continue;
			}
			$this->io->text( "  $dependency: downloading $branch snapshot" );
			$this->install( $branches[$branch], $staging, $destination, $dependency->type, $dependency->name );
		}

		if ( $unavailable ) {
			throw new PrepareException( "Some extensions or skins are not available from ExtensionDistributor:\n  "
				. implode( "\n  ", $unavailable ) . "\nSet a different branch for them in $depsFile, or remove them." );
		}
	}

	/**
	 * Download a tarball, extract it to $destination and record it in PREPARED.txt.
	 *
	 * @param string $url
	 * @param string $staging Where to download to
	 * @param string $destination
	 * @param string $type For PREPARED.txt
	 * @param string $name For PREPARED.txt
	 * @param callable|null $verify Called with the downloaded file before extracting it
	 */
	private function install(
		string $url, string $staging, string $destination, string $type, string $name, ?callable $verify = null
	): void {
		$tarball = "$staging/" . basename( (string)parse_url( $url, PHP_URL_PATH ) );
		$this->downloader->toFile( $url, $tarball );
		if ( $verify ) {
			$verify( $tarball );
		}
		$this->shell->extractTarball( $tarball, $destination );
		$this->record( $type, $name, "$url sha256:" . hash_file( 'sha256', $tarball ) );
		$this->filesystem->remove( $tarball );
	}

	private function record( string $type, string $name, string $source ): void {
		$this->manifest[] = sprintf( '%-10s %-28s %s', $type, $name, $source );
	}
}
