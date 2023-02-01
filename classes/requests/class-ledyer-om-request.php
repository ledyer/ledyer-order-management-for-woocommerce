<?php
/**
 * Abstract Request
 *
 * @package LedyerOm\Requests
 */
namespace LedyerOm\Requests;

use LedyerOm\Credentials;
use LedyerOm\Logger;

defined( 'ABSPATH' ) || exit();

/**
 * Class Request
 *
 * @package LedyerOm\Requests
 */
abstract class Request {

	/**
	 * Request arguments
	 * @var array|mixed
	 */
	protected $arguments;
	/**
	 * Ledyer settings
	 * @var array
	 */
	protected $settings;
	/**
	 * Request method
	 * @var string
	 */
	protected $method = 'POST';
	/**
	 * Request endpoint
	 * @var string
	 */
	protected $url = '';
	/**
	 * Merchant Bearer token
	 * @var string
	 */
	private $access_token;
	/**
	 * Request entrypoint
	 * @var string
	 */
	protected $request_url;
	/*
	 * Requests Class constructor.
	 */
	public function __construct( $arguments = [] ) {
		$this->arguments    = $arguments;
		$this->access_token = $this->token();
		$this->set_request_url();
	}

	/**
	 * Sets request endpoint
	 * @return mixed
	 */
	abstract protected function set_request_url();

	/**
	 * Save merchant's bearer token in transient.
	 * Transient 'ledyer_token' expires in 3600s.
	 * @return mixed|string
	 */
	private function token() {

		if ( get_transient( 'ledyer_token' ) ) {
			return get_transient( 'ledyer_token' );
		}

		$api_auth_base = 'https://auth.live.ledyer.com/';

		$environment = ledyerOm()->parentSettings->get_test_environment();

		if ( $this->is_test() ) {
			switch ($environment) {
				case 'local':
					$api_auth_base = 'http://host.docker.internal:9001/';
					break;
				case 'development':
				case 'local-fe':
					$api_auth_base = 'https://auth.dev.ledyer.com/';
					break;
				default: 
					$api_auth_base = 'https://auth.sandbox.ledyer.com/';
					break;
			}
		}

		$client = new \WP_Http();
		$client_credentials = ledyerOm()->credentials->get_client_credentials();

		$headers = [
			'Authorization' => 'Basic ' . base64_encode( $client_credentials['client_id'] . ':' . $client_credentials['client_secret'] ),
		];

		$response = $client->post( $api_auth_base . 'oauth/token?grant_type=client_credentials', [
			'headers' => $headers,
			'timeout' => 60
		] );

		$body = $this->process_response( $response, [ 'grant_type' => 'client_credentials' ], $api_auth_base . 'oauth/token' );

		$is_wp_error = is_object( $body ) && false !== stripos( get_class( $body ), 'WP_Error' );

		if ( ! $is_wp_error && isset( $body['access_token'] ) ) {
			set_transient( 'ledyer_token', $body['access_token'], $body['expires_in'] );

			return get_transient( 'ledyer_token' );
		}

		return '';
	}

	/**
	 * Make request.
	 * @return mixed|\WP_Error
	 */
	public function request() {
		$url  = $this->get_request_url();
		$args = $this->get_request_args();
		$headers = [
			'Idempotency-Key' => wp_generate_uuid4(),
		];
		$args['headers'] = array_merge($args['headers'], $headers);
		return $this->do_request($url, $args);
	}

	/** internal retry helper with exponential backoff */
	protected function do_request($url, $args, $maxRetries = 4, $delay = 0.5, $exp = 2) {
		$response = wp_remote_request( $url, $args );
		$parsed = $this->process_response( $response, $args, $url );
		if ( is_wp_error( $parsed ) ) {
			$http_response_code = wp_remote_retrieve_response_code( $response );
			// retry connection, timeout errors etc + all http 500 and above
			$retry = is_string($http_response_code) || $http_response_code > 499;
			if ($retry && $maxRetries > 0) {
				usleep($delay * 1E6);
				return $this->do_request($url, $args, $maxRetries - 1, $delay * $exp, $exp);
			}
			return $parsed;
		}
		return $parsed;
	}

	/**
	 * Create request url.
	 * @return string
	 */
	protected function get_request_url() {
		$base = $this->request_url;
		$slug = trim( $this->url, '/' );

		return $base . $slug;
	}

	/**
	 * Create request args.
	 * @return array
	 */
	protected function get_request_args() {
		$request_args = [
			'headers' => $this->get_request_headers(),
			'method'  => $this->method,
			'timeout' => apply_filters( 'ledyer_request_timeout', 10 ),
		];

		if ( 'POST' === $this->method && $this->arguments['data'] ) {
			$request_args['body'] = json_encode( $this->arguments['data'] );
		}

		return $request_args;
	}

	/**
	 * Create request headers.
	 * @return array
	 */
	protected function get_request_headers() {
		return [
			'Authorization' => sprintf( 'Bearer %s', $this->token() ),
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Check if test env is enabled.
	 * @return bool
	 */
	protected function is_test() {
		return 'yes' === ledyerOm()->parentSettings->get_is_test_mode();
	}

	/**
	 * Process response. Return response body or error.
	 * Log errors.
	 * @param $response
	 * @param $request_args
	 * @param $request_url
	 *
	 * @return mixed|\WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		$code = wp_remote_retrieve_response_code( $response );

		$log = Logger::format_log( '', 'POST', 'Debugger', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );

		Logger::log( $log );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code < 200 || $response_code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			$errors        = json_decode( $response['body'], true );

			if ( ! empty( $errors ) && ! empty( $errors['errors'] ) ) {
				foreach ( $errors['errors'] as $error ) {
					$error_message .= ' ' . $error['message'];
				}
			}
			$return = new \WP_Error( $response_code, $error_message, $data );
		} else {
			$return = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return $return;
	}
}
