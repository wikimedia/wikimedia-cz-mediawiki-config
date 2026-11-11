<?php

declare( strict_types=1 );

namespace WMCZ\Tests\Upgrade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use WMCZ\Upgrade\Downloader;
use WMCZ\Upgrade\PrepareException;

#[CoversClass( Downloader::class )]
#[RequiresPhpExtension( 'curl' )]
final class DownloaderTest extends TestCase {

	/** @var string[] URLs passed to toString() */
	private array $requested = [];

	public function testCurlOptions(): void {
		$this->assertSame( CURLPROTO_HTTPS, Downloader::CURL_OPTIONS[CURLOPT_PROTOCOLS], 'Never use plain HTTP' );
		$this->assertSame( CURLPROTO_HTTPS, Downloader::CURL_OPTIONS[CURLOPT_REDIR_PROTOCOLS], 'Not even through redirects' );
		$this->assertTrue( Downloader::CURL_OPTIONS[CURLOPT_FAILONERROR], 'HTTP errors fail the download' );
		$this->assertTrue( Downloader::CURL_OPTIONS[CURLOPT_FOLLOWLOCATION] );
		$this->assertSame( Downloader::USER_AGENT, Downloader::CURL_OPTIONS[CURLOPT_USERAGENT] );
		$this->assertStringContainsString( 'WikimediaCZ', Downloader::USER_AGENT );
		$this->assertStringContainsString( 'root@wikimedia.cz', Downloader::USER_AGENT );
	}

	public function testRefusesPlainHttp(): void {
		// Rejected by curl itself, without connecting anywhere (and without retrying)
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessageMatches( '!^Download of http://localhost/ failed: Protocol "http" (disabled|not supported)!' );
		( new Downloader() )->toString( 'http://localhost/' );
	}

	public function testUnwritableFile(): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'Unable to write /nonexistent/file.tar.gz' );
		( new Downloader() )->toFile( 'https://example.org/file.tar.gz', '/nonexistent/file.tar.gz' );
	}

	public static function provideErrors(): iterable {
		yield 'timeout' => [ CURLE_OPERATION_TIMEDOUT, 0, true ];
		yield 'interrupted transfer' => [ CURLE_PARTIAL_FILE, 200, true ];
		yield 'HTTP 503' => [ CURLE_HTTP_RETURNED_ERROR, 503, true ];
		yield 'HTTP 429' => [ CURLE_HTTP_RETURNED_ERROR, 429, true ];
		yield 'HTTP 404' => [ CURLE_HTTP_RETURNED_ERROR, 404, false ];
		yield 'unsupported protocol' => [ CURLE_UNSUPPORTED_PROTOCOL, 0, false ];
		yield 'unknown host' => [ CURLE_COULDNT_RESOLVE_HOST, 0, false ];
	}

	#[DataProvider( 'provideErrors' )]
	public function testIsTransientError( int $errno, int $status, bool $expected ): void {
		$this->assertSame( $expected, Downloader::isTransientError( $errno, $status ) );
	}

	/**
	 * @param string ...$responses What toString() returns, in order
	 */
	private function createDownloader( string ...$responses ): Downloader {
		$downloader = $this->getMockBuilder( Downloader::class )->onlyMethods( [ 'toString' ] )->getMock();
		$downloader->expects( $this->exactly( count( $responses ) ) )
			->method( 'toString' )
			->willReturnCallback( function ( string $url ) use ( &$responses ): string {
				$this->requested[] = $url;
				return (string)array_shift( $responses );
			} );
		return $downloader;
	}

	/**
	 * @return array<string,string>
	 */
	private static function getQuery( string $url ): array {
		parse_str( (string)parse_url( $url, PHP_URL_QUERY ), $query );
		return $query;
	}

	public function testGetSnapshots(): void {
		$downloader = $this->createDownloader( (string)json_encode( [ 'query' => [ 'extdistbranches' => [ 'extensions' => [
			'UserMerge' => [
				'REL1_46' => 'https://extdist.wmflabs.org/dist/extensions/UserMerge-REL1_46-2724d20.tar.gz',
				'master' => 'https://extdist.wmflabs.org/dist/extensions/UserMerge-master-78e3bdc.tar.gz',
				'insecure' => 'http://example.org/UserMerge.tar.gz',
				'source' => 'https://gerrit.wikimedia.org/r/mediawiki/extensions/UserMerge.git',
			],
		] ] ] ] ) );

		$this->assertSame( [ 'UserMerge' => [
			'REL1_46' => 'https://extdist.wmflabs.org/dist/extensions/UserMerge-REL1_46-2724d20.tar.gz',
			'master' => 'https://extdist.wmflabs.org/dist/extensions/UserMerge-master-78e3bdc.tar.gz',
		] ], $downloader->getSnapshots( 'extension', [ 'UserMerge', 'Bogus' ] ), 'Unknown names and non-tarballs are left out' );

		$this->assertStringStartsWith( 'https://www.mediawiki.org/w/api.php?', $this->requested[0] );
		$this->assertSame( [
			'action' => 'query',
			'list' => 'extdistbranches',
			'edbexts' => 'UserMerge|Bogus',
			'format' => 'json',
			'formatversion' => '2',
		], self::getQuery( $this->requested[0] ) );
	}

	public function testGetSnapshotsOfSkinsInBatches(): void {
		$names = array_map( static fn ( int $i ) => "Skin$i", range( 1, 120 ) );
		$response = static fn ( array $batch ) => (string)json_encode( [ 'query' => [ 'extdistbranches' => [ 'skins' =>
			array_fill_keys( $batch, [ 'REL1_46' => 'https://example.org/skin.tar.gz' ] ),
		] ] ] );
		$downloader = $this->createDownloader( ...array_map( $response, array_chunk( $names, 50 ) ) );

		$this->assertSame( $names, array_keys( $downloader->getSnapshots( 'skin', $names ) ) );
		$this->assertSame( [ 50, 50, 20 ], array_map(
			static fn ( string $url ) => count( explode( '|', self::getQuery( $url )['edbskins'] ) ),
			$this->requested
		) );
	}

	public function testGetSnapshotsApiError(): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'ExtensionDistributor API error: Something broke.' );
		$this->createDownloader( '{"error":{"code":"badvalue","info":"Something broke."}}' )
			->getSnapshots( 'extension', [ 'Cite' ] );
	}

	public function testGetSnapshotsUnexpectedResponse(): void {
		$this->expectException( PrepareException::class );
		$this->expectExceptionMessage( 'Unexpected response' );
		$this->createDownloader( '<html>Service unavailable</html>' )->getSnapshots( 'extension', [ 'Cite' ] );
	}
}
