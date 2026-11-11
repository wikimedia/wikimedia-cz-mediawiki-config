<?php

declare( strict_types=1 );

namespace WMCZ\Upgrade;

/**
 * Downloads files and asks ExtensionDistributor about snapshots (using the PHP curl extension).
 */
class Downloader {

	/** @see https://foundation.wikimedia.org/wiki/Policy:Wikimedia_Foundation_User-Agent_Policy */
	public const USER_AGENT = 'WikimediaCZ-mediawiki-config/1.0 (MediaWiki upgrade helper; root@wikimedia.cz) php-curl';

	public const CURL_OPTIONS = [
		CURLOPT_USERAGENT => self::USER_AGENT,
		CURLOPT_FAILONERROR => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 10,
		// Never use plain HTTP, not even through a redirect
		CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
		CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
		CURLOPT_CONNECTTIMEOUT => 30,
	];

	private const API_URL = 'https://www.mediawiki.org/w/api.php';
	private const ATTEMPTS = 3;

	public function toFile( string $url, string $file ): void {
		$this->request( $url, $file );
	}

	public function toString( string $url ): string {
		return $this->request( $url, null );
	}

	/**
	 * Ask ExtensionDistributor which snapshots it offers.
	 *
	 * @see https://www.mediawiki.org/wiki/Extension:ExtensionDistributor
	 * @param string $type "extension" or "skin"
	 * @param string[] $names
	 * @return array<string,array<string,string>> name => [ branch => tarball URL ]; unknown names are left out
	 */
	public function getSnapshots( string $type, array $names ): array {
		$snapshots = [];
		foreach ( array_chunk( $names, 50 ) as $batch ) {
			$url = self::API_URL . '?' . http_build_query( [
				'action' => 'query',
				'list' => 'extdistbranches',
				( $type === 'extension' ? 'edbexts' : 'edbskins' ) => implode( '|', $batch ),
				'format' => 'json',
				'formatversion' => '2',
			] );
			$data = json_decode( $this->toString( $url ), true );
			if ( !is_array( $data ) ) {
				throw new PrepareException( "Unexpected response from $url" );
			}
			if ( isset( $data['error'] ) ) {
				throw new PrepareException( 'ExtensionDistributor API error: ' . ( $data['error']['info'] ?? 'unknown' ) );
			}
			$snapshots += $data['query']['extdistbranches'][Dependency::TYPES[$type]] ?? [];
		}
		// Keep only tarball URLs (the API also returns the repository URL as "source")
		return array_map( static fn ( array $branches ) => array_filter(
			$branches,
			static fn ( $url, $branch ) => $branch !== 'source' && is_string( $url ) && str_starts_with( $url, 'https://' ),
			ARRAY_FILTER_USE_BOTH
		), $snapshots );
	}

	/**
	 * Whether a failed request is worth retrying (like curl's --retry): timeouts,
	 * interrupted transfers and HTTP 408, 429 and 5xx.
	 *
	 * @param int $errno cURL error number
	 * @param int $status HTTP status code (0 if there was no response)
	 */
	public static function isTransientError( int $errno, int $status ): bool {
		return in_array( $errno, [ CURLE_OPERATION_TIMEDOUT, CURLE_PARTIAL_FILE, CURLE_RECV_ERROR ], true )
			|| in_array( $status, [ 408, 429, 500, 502, 503, 504 ], true );
	}

	/**
	 * @param string $url
	 * @param string|null $file File to save the response to, null to return it
	 * @return string The response, or '' when saved to $file
	 */
	private function request( string $url, ?string $file ): string {
		if ( !function_exists( 'curl_init' ) ) {
			throw new PrepareException( 'Downloading needs the PHP curl extension (php-curl)' );
		}
		for ( $attempt = 1; ; $attempt++ ) {
			$output = $file === null ? null : @fopen( $file, 'wb' );
			if ( $output === false ) {
				throw new PrepareException( "Unable to write $file" );
			}
			$curl = curl_init( $url );
			curl_setopt_array( $curl, self::CURL_OPTIONS + ( $output ? [ CURLOPT_FILE => $output ] : [ CURLOPT_RETURNTRANSFER => true ] ) );
			$response = curl_exec( $curl );
			$errno = curl_errno( $curl );
			$error = curl_error( $curl );
			$status = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
			if ( $output ) {
				fclose( $output );
			}

			if ( $errno === 0 ) {
				return $output ? '' : (string)$response;
			}
			if ( $attempt === self::ATTEMPTS || !self::isTransientError( $errno, $status ) ) {
				throw new PrepareException( "Download of $url failed: $error" );
			}
			sleep( $attempt );
		}
	}
}
