<?php
/**
 * Abstract Request
 *
 * @package LedyerOm\Requests
 */
namespace LedyerOm\Requests;

use LedyerOm\Gateway_Settings;
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
	 *
	 * @var array|mixed
	 */
	protected $arguments;
	/**
	 * Ledyer settings
	 *
	 * @var array
	 */
	protected $settings;
	/**
	 * Request method
	 *
	 * @var string
	 */
	protected $method = 'POST';
	/**
	 * Request endpoint
	 *
	 * @var string
	 */
	protected $url = '';
	/**
	 * Merchant Bearer token
	 *
	 * @var string
	 */
	private $access_token;
	/**
	 * Request entrypoint
	 *
	 * @var string
	 */
	protected $request_url;

	/**
	 * The log title to use for the debug log.
	 *
	 * @var string
	 */
	protected $log_title = 'Request';

	/**
	 * The WC order.
	 *
	 * @var WC_Order
	 */
	protected $order;

	/**
	 * Gateway settings.
	 *
	 * @var Gateway_Settings
	 */
	protected $gateway_settings;

	/**
	 * Requests Class constructor.
	 *
	 * @param array $arguments Request arguments.
	 * @param int   $order_id The WC order ID.
	 */
	public function __construct( $arguments, $order_id ) {
		$this->order = wc_get_order( $order_id );
		$this->load_settings( $this->order->get_payment_method() );

		$this->arguments    = $arguments;
		$this->access_token = $this->token();
		$this->set_request_url();
	}

	/**
	 * Load the gateway settings.
	 *
	 * @param string $gateway The gateway to get settings for. Either 'lco' or 'ledyer_payments'. Defaults to the latter.
	 */
	private function load_settings( $gateway ) {
		$this->gateway_settings = new Gateway_Settings( $gateway );
	}

	/**
	 * Sets request endpoint
	 *
	 * @return mixed
	 */
	abstract protected function set_request_url();

	/**
	 * Save merchant's bearer token in transient.
	 * Transient 'ledyer_token' expires in 3600s.
	 *
	 * @return mixed|string
	 */
	private function token() {
		if ( get_transient( 'ledyer_token' ) ) {
			return get_transient( 'ledyer_token' );
		}

		$api_auth_base = 'https://auth.live.ledyer.com/';

		$environment = $this->gateway_settings->is_test_environment();

		if ( $this->gateway_settings->is_test_mode() ) {
			switch ( $environment ) {
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

		$client             = new \WP_Http();
		$start              = microtime( true );
		$client_credentials = $this->gateway_settings->get_client_credentials();

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode( $client_credentials['client_id'] . ':' . $client_credentials['client_secret'] ),
		);

		$response = $client->post(
			$api_auth_base . 'oauth/token?grant_type=client_credentials',
			array(
				'headers' => $headers,
				'timeout' => 60,
			)
		);

		$this->maybe_add_order_log_context();
		$token_args = array(
			'headers' => $headers,
			'method'  => 'POST',
		);
		Logger::log_request( 'Get access token', 'POST', $api_auth_base . 'oauth/token', $token_args, $response, microtime( true ) - $start, $this->gateway_settings->is_logging_enabled() );

		$body = $this->process_response( $response, array( 'grant_type' => 'client_credentials' ), $api_auth_base . 'oauth/token' );

		$is_wp_error = is_object( $body ) && false !== stripos( get_class( $body ), 'WP_Error' );

		if ( ! $is_wp_error && isset( $body['access_token'] ) ) {
			set_transient( 'ledyer_token', $body['access_token'], $body['expires_in'] );

			return get_transient( 'ledyer_token' );
		}

		return '';
	}

	/**
	 * Make request.
	 *
	 * @return mixed|\WP_Error
	 */
	public function request() {
		$url             = $this->get_request_url();
		$args            = $this->get_request_args();
		$headers         = array(
			'Idempotency-Key' => wp_generate_uuid4(),
		);
		$args['headers'] = array_merge( $args['headers'], $headers );
		return $this->do_request( $url, $args );
	}

	/**
	 * Internal retry helper with exponential backoff.
	 *
	 * @param string $url         The request URL.
	 * @param array  $args        The request args.
	 * @param int    $max_retries The number of retries left.
	 * @param float  $delay       The delay before the next retry, in seconds.
	 * @param int    $exp         The backoff exponent.
	 *
	 * @return mixed|\WP_Error
	 */
	protected function do_request( $url, $args, $max_retries = 4, $delay = 0.5, $exp = 2 ) {
		$start    = microtime( true );
		$response = wp_remote_request( $url, $args );

		$this->maybe_add_order_log_context();
		// The first attempt has all 4 retries left.
		$extra = $max_retries < 4 ? array( 'retry_attempt' => 4 - $max_retries ) : array();
		Logger::log_request( $this->log_title, $this->method, $url, $args, $response, microtime( true ) - $start, $this->gateway_settings->is_logging_enabled(), $extra );

		$parsed = $this->process_response( $response, $args, $url );
		if ( is_wp_error( $parsed ) ) {
			$http_response_code = wp_remote_retrieve_response_code( $response );
			// retry connection, timeout errors etc + all http 500 and above.
			$retry = is_string( $http_response_code ) || $http_response_code > 499;
			if ( $retry && $max_retries > 0 ) {
				usleep( $delay * 1E6 );
				return $this->do_request( $url, $args, $max_retries - 1, $delay * $exp, $exp );
			}
			return $parsed;
		}
		return $parsed;
	}

	/**
	 * Create request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$base = $this->request_url;
		$slug = trim( $this->url, '/' );

		return $base . $slug;
	}

	/**
	 * Create request args.
	 *
	 * @return array
	 */
	protected function get_request_args() {
		$request_args = array(
			'headers' => $this->get_request_headers(),
			'method'  => $this->method,
			'timeout' => apply_filters( 'ledyer_request_timeout', 10 ),
		);

		if ( 'POST' === $this->method && $this->arguments['data'] ) {
			$request_args['body'] = json_encode( $this->arguments['data'] );
		}

		return $request_args;
	}

	/**
	 * Create request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Authorization' => sprintf( 'Bearer %s', $this->token() ),
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Check if test env is enabled.
	 *
	 * @return bool
	 */
	protected function is_test() {
		return $this->gateway_settings->is_test_mode();
	}

	/**
	 * Adds the order's trace IDs to the log context when the request is made outside of an order management action.
	 *
	 * @return void
	 */
	private function maybe_add_order_log_context() {
		if ( ! $this->order ) {
			return;
		}

		$context = Logger::get_context();
		if ( empty( $context['wc_order_id'] ) ) {
			Logger::add_order_context( $this->order );
		} elseif ( (string) $this->order->get_id() !== $context['wc_order_id'] ) {
			Logger::clear_context();
			Logger::add_order_context( $this->order );
		}
	}

	/**
	 * Process response. Return response body or error.
	 *
	 * @param $response
	 * @param $request_args
	 * @param $request_url
	 *
	 * @return mixed|\WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
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
			$return = new \WP_Error( $response_code, trim( $error_message ), $data );
		} else {
			$return = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return $return;
	}
}
