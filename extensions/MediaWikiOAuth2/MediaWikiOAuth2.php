<?php

namespace Mwstake\OAuth2;

use League\OAuth2\Client\Provider\GenericProvider;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerAwareTrait;
use WSOAuth\AuthenticationProvider\AuthProvider;

/**
 * OAuth 2.0 authentication provider for mediawiki.org / Wikimedia central OAuth
 * (Extension:OAuth REST endpoints on meta.wikimedia.org).
 *
 * WSOAuth's built-in MediaWikiAuth only supports OAuth 1.0a; this provider adds
 * OAuth 2.0 so a wiki can delegate login to a mediawiki.org OAuth 2.0 consumer
 * through the same Special:PluggableAuthLogin return endpoint.
 */
class MediaWikiOAuth2 extends AuthProvider {

	/**
	 * Central OAuth 2.0 REST endpoints (Wikimedia central wiki is meta.wikimedia.org).
	 */
	private const URL_AUTHORIZE = 'https://meta.wikimedia.org/w/rest.php/oauth2/authorize';
	private const URL_ACCESS_TOKEN = 'https://meta.wikimedia.org/w/rest.php/oauth2/access_token';
	private const URL_RESOURCE_OWNER = 'https://meta.wikimedia.org/w/rest.php/oauth2/resource/profile';

	private const SCOPE = 'mwoauth-authonly';

	/**
	 * Wikimedia requires a descriptive User-Agent (robot policy); requests without one are rejected.
	 * @see https://phabricator.wikimedia.org/T400119
	 */
	private const USER_AGENT = 'WSOAuth-MediaWikiOAuth2/1.0 (https://meta.wikiapiary.com; admin@wikiapiary.com)';

	/**
	 * @var GenericProvider
	 */
	private $provider;

	/**
	 * @var string
	 */
	private $clientId;

	/**
	 * @var string
	 */
	private $clientSecret;

	/**
	 * @var string|null
	 */
	private $redirectUri;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		string $clientId,
		string $clientSecret,
		?string $authUri,
		?string $redirectUri,
		array $extensionData = []
	) {
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->redirectUri = $redirectUri;

		$this->provider = new GenericProvider( [
			'clientId' => $clientId,
			'clientSecret' => $clientSecret,
			'redirectUri' => $redirectUri,
			'urlAuthorize' => self::URL_AUTHORIZE,
			'urlAccessToken' => self::URL_ACCESS_TOKEN,
			'urlResourceOwnerDetails' => self::URL_RESOURCE_OWNER,
			'scope' => self::SCOPE,
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function login( ?string &$key, ?string &$secret, ?string &$authUrl ): bool {
		$authUrl = $this->provider->getAuthorizationUrl( [
			'scope' => [ self::SCOPE ],
		] );

		// State is the CSRF token; WSOAuth persists it in the session across the redirect.
		$secret = $this->provider->getState();

		return true;
	}

	/**
	 * Perform an HTTP request with curl and return [body, info].
	 */
	private function http( string $method, string $url, array $fields = [], array $headers = [] ) {
		$ch = curl_init( $url );
		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_USERAGENT => self::USER_AGENT,
			CURLOPT_HTTPHEADER => $headers,
		];
		if ( $method === 'POST' ) {
			$opts[CURLOPT_POST] = true;
			$opts[CURLOPT_POSTFIELDS] = http_build_query( $fields );
		}
		curl_setopt_array( $ch, $opts );
		$body = curl_exec( $ch );
		$info = curl_getinfo( $ch );
		$errno = curl_errno( $ch );
		$err = curl_error( $ch );
		curl_close( $ch );
		return [ $body, $info, $errno, $err ];
	}

	/**
	 * @inheritDoc
	 */
	public function getUser( string $key, string $secret, &$errorMessage ) {
		if ( !isset( $_GET['code'] ) ) {
			return false;
		}

		if ( !isset( $_GET['state'] ) || $_GET['state'] !== $secret ) {
			$errorMessage = 'OAuth 2.0 state mismatch.';
			return false;
		}

		$log = function ( $m ) {
			file_put_contents( '/tmp/oauth2_debug.log', date( 'c' ) . ' ' . $m . "\n", FILE_APPEND );
		};

		try {
			$code = $_GET['code'];

			// 1) Exchange authorization code for an access token.
			list( $body, $info, $errno, $err ) = $this->http( 'POST', self::URL_ACCESS_TOKEN, [
				'grant_type' => 'authorization_code',
				'code' => $code,
				'redirect_uri' => $this->redirectUri,
			], [
				'Authorization: Basic ' . base64_encode( $this->clientId . ':' . $this->clientSecret ),
				'Accept: application/json',
			] );
			$log( "TOKEN http=" . $info['http_code'] . " errno=$errno err=$err ctype=" . $info['content_type'] . " body=" . substr( $body, 0, 2000 ) );

			if ( $errno !== 0 ) {
				$errorMessage = 'curl token error: ' . $err;
				return false;
			}

			$token = json_decode( $body, true );
			if ( !is_array( $token ) || empty( $token['access_token'] ) ) {
				$errorMessage = 'No access_token in response: ' . substr( $body, 0, 500 );
				return false;
			}

			// 2) Fetch the user profile.
			list( $pbody, $pinfo, $perrno, $perr ) = $this->http( 'GET', self::URL_RESOURCE_OWNER, [], [
				'Authorization: Bearer ' . $token['access_token'],
				'Accept: application/json',
			] );
			$log( "PROFILE http=" . $pinfo['http_code'] . " errno=$perrno err=$perr ctype=" . $pinfo['content_type'] . " body=" . substr( $pbody, 0, 2000 ) );

			if ( $perrno !== 0 ) {
				$errorMessage = 'curl profile error: ' . $perr;
				return false;
			}

			$data = json_decode( $pbody, true );
			if ( !is_array( $data ) ) {
				$errorMessage = 'Profile was not JSON: ' . substr( $pbody, 0, 500 );
				return false;
			}

			$name = $data['username'] ?? $data['name'] ?? null;
			if ( $name === null || $name === '' ) {
				$errorMessage = 'Profile missing username: ' . substr( $pbody, 0, 500 );
				return false;
			}

			return [
				'name' => $name,
				'realname' => $data['realname'] ?? null,
				'email' => $data['email'] ?? null,
			];
		} catch ( \Exception $e ) {
			$log( 'EXCEPTION ' . $e->getMessage() );
			$errorMessage = 'OAuth 2.0 authentication failed: ' . $e->getMessage();
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function logout( UserIdentity &$user ): void {
	}

	/**
	 * @inheritDoc
	 */
	public function saveExtraAttributes( int $id ): void {
	}
}
