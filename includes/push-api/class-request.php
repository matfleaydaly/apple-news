<?php
namespace Push_API;

require_once __DIR__ . '/class-mime-builder.php';

/**
 * An object capable of sending signed HTTP requests to the Push API.
 *
 * @since 0.0.0
 */
class Request {

	/**
	 * The URL the request will be sent to.
	 *
	 * @var string
	 * @since 0.0.0
	 */
	private $url;

	/**
	 * The method we'll use for the request. Assumes GET. Can be either POST or
	 * GET.
	 *
	 * @var string Can either be POST or GET. If it's invalid, assumes GET.
	 * @since 0.0.0
	 */
	private $verb;

	/**
	 * Helper class used to build the MIME parts of the request.
	 *
	 * @var MIME_Builder
	 * @since 0.0.0
	 */
	private $mime_builder;

	/**
	 * Whether or not we are debugging using a reverse proxy, like Charles.
	 *
	 * @var boolean
	 * @since 0.0.0
	 */
	private $debug;

	/**
	 * The signature used to authenticate the sent request.
	 *
	 * @var string
	 * @since 0.0.0
	 */
	private $signature;

	/**
	 * The content this request holds, in MIME format.
	 *
	 * @var string
	 * @since 0.0.0
	 */
	private $content;

	function __construct( $url, $verb = 'GET', $debug = false, $mime_builder = null ) {
		$this->url          = $url;
		$this->verb         = $verb;
		$this->mime_builder = $mime_builder ?: new MIME_Builder();
		$this->debug        = $debug;
		$this->signature    = null;
		$this->content      = null;
	}

	/**
	 * Given an article, builds the request's content.
	 *
	 * TODO: Should article be an Export_Content?
	 *
	 * @param string $article The JSON contents of the article
	 * @param array  $bundles The paths of the article's bundled files. Names
	 *                        must match the ones specified in the JSON spec.
	 *
	 * @since 0.0.0
	 */
	public function set_article( $article, $bundles = array() ) {
		$this->content = $this->mime_builder->add_json_string( 'my_article', 'article.json', $article );
		foreach ( $bundles as $bundle ) {
			$this->content .= $this->mime_builder->add_content_from_file( $bundle );
		}
		$this->content .= $this->mime_builder->close();
	}

	/**
	 * Authenticates the content we are sending, "signing" it with the
	 * credentials passed.
	 *
	 * @param Push_API/Credentials $credentials The credentials that will be used
	 *                                          to sign the request.
	 */
	public function authenticate( $credentials ) {
		if ( 'POST' == $this->verb && is_null( $this->content ) ) {
			throw new Request_Exception( 'POST requests must add content before signing it.' );
		}

		$current_date = date( 'c' );
		$request_info = $this->verb . $this->url . $current_date;

		if ( 'POST' == $this->verb ) {
			$content_type = 'multipart/form-data; boundary=' . $this->mime_builder->boundary();
			$request_info .= $content_type . $this->content;
		}

		$secret_key = base64_decode( $credentials->secret() );
		$hash       = hash_hmac( 'sha256', $request_info, $secret_key, true );
		$signature  = base64_encode( $hash );

		$this->signature = 'Authorization: HHMAC; key=' . $credentials->key() . '; signature=' . $signature . '; date=' . $current_date;
	}

	/**
	 * Send the request using CURL.
	 *
	 * @since 0.0.0
	 */
	public function send() {
		// TODO: Make a request object to wrap CURL requests
		// Set up CURL
		$curl = curl_init( $this->url );

		// If we want to debug using a reverse proxy, like Charles.
		if ( $this->debug ) {
			curl_setopt( $curl, CURLOPT_PROXY, '127.0.0.1' );
			curl_setopt( $curl, CURLOPT_PROXYPORT, 8888 );
		}

		// The HTTPS certificate does not seem to be validated, this is probably
		// becaues it's just a test endpoint for now. This should be removed once
		// the endoint is stable, or at least be able to toggle it on and off.
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, 0);
		// Not sure if this is required. Leave it off if possible.
		//curl_setopt( $curl, CURLOPT_INFILESIZE, strlen( $this->article ) );
		// Make curl_exec return the request result rather than just true.
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );

		// Check for request type
		if ( 'POST' == $this->verb ) {
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array(
				'Content-Length: ' . strlen( $this->content ),
				'Content-Type: multipart/form-data; boundary=' . $this->mime_builder->boundary(),
				$this->signature
			) );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $this->content );
		} else {
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array( $this->signature ) );
		}

		// CURL is ready. Execute!
		$response = curl_exec( $curl );
		if ( false === $response ) {
			$error = curl_error( $curl );
			curl_close( $curl );
			throw new Request_Exception( "CURL request failed: $error" );
		}
		curl_close($curl);

		$response = json_decode( $response );
		if ( property_exists( $response, 'errors' ) ) {
			$string_errors = '';
			foreach ( $response->errors as $error ) {
				$string_errors .= $error->code . "\n";
			}
			throw new Request_Exception( "There has been an error with your request:\n$string_errors" );
		}

		return $response;
	}

}

class Request_Exception extends \Exception {}
