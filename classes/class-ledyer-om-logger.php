<?php
/**
 * Logging class file.
 *
 * @package LedyerOm
 */

namespace LedyerOm;

defined( 'ABSPATH' ) || exit();

/**
 * Logger class.
 *
 * Writes log entries to the WooCommerce log (source "ledyer-log", shared with the Ledyer gateway plugins).
 */
class Logger {
	/**
	 * The log source used for the WooCommerce logger.
	 */
	const LOG_SOURCE = 'ledyer-log';

	/**
	 * Readable labels for the log context actions.
	 *
	 * @var array
	 */
	const ACTION_LABELS = array(
		'capture'       => 'Capture',
		'refund'        => 'Refund',
		'cancel'        => 'Cancel',
		'edit-order'    => 'Edit order',
		'edit-customer' => 'Edit customer',
	);

	/**
	 * The logger instance.
	 *
	 * @var \WC_Logger_Interface|null
	 */
	private static $log;

	/**
	 * The current log context.
	 *
	 * @var array
	 */
	private static $context = array();

	/**
	 * Sets (merges) values into the current log context.
	 *
	 * Supported keys: action, ledyer_session_id, ledyer_order_id, wc_order_id, logging_enabled.
	 *
	 * @param array $context The context values to set.
	 *
	 * @return void
	 */
	public static function set_context( $context ) {
		foreach ( $context as $key => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}
			self::$context[ $key ] = is_bool( $value ) ? $value : (string) $value;
		}
	}

	/**
	 * Adds the trace IDs stored on a WooCommerce order to the current log context.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 *
	 * @return void
	 */
	public static function add_order_context( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$gateway_settings = new Gateway_Settings( $order->get_payment_method() );

		self::set_context(
			array(
				'wc_order_id'       => $order->get_id(),
				'ledyer_order_id'   => $order->get_meta( '_wc_ledyer_order_id' ),
				'ledyer_session_id' => $order->get_meta( '_wc_ledyer_session_id' ),
				'logging_enabled'   => wc_string_to_bool( $gateway_settings->is_logging_enabled() ),
			)
		);
	}

	/**
	 * Starts a new log context for an order management action on an order.
	 *
	 * @param string    $action The action (capture, refund, cancel, edit-order, edit-customer).
	 * @param \WC_Order $order  The WooCommerce order.
	 *
	 * @return void
	 */
	public static function start_order_context( $action, $order ) {
		self::clear_context();
		self::set_context( array( 'action' => $action ) );
		self::add_order_context( $order );
	}

	/**
	 * Clears the current log context.
	 *
	 * @return void
	 */
	public static function clear_context() {
		self::$context = array();
	}

	/**
	 * Gets the current log context.
	 *
	 * @return array
	 */
	public static function get_context() {
		return self::$context;
	}

	/**
	 * Logs an informational event.
	 *
	 * @param string $title A short, descriptive title of the event.
	 * @param array  $data  Optional extra data to log.
	 *
	 * @return void
	 */
	public static function info( $title, $data = array() ) {
		self::log_event( 'info', $title, $data );
	}

	/**
	 * Logs a warning event.
	 *
	 * @param string $title A short, descriptive title of the event.
	 * @param array  $data  Optional extra data to log.
	 *
	 * @return void
	 */
	public static function warning( $title, $data = array() ) {
		self::log_event( 'warning', $title, $data );
	}

	/**
	 * Logs an error event.
	 *
	 * @param string $title A short, descriptive title of the event.
	 * @param array  $data  Optional extra data to log.
	 *
	 * @return void
	 */
	public static function error( $title, $data = array() ) {
		self::log_event( 'error', $title, $data );
	}

	/**
	 * Logs an event with the given level.
	 *
	 * @param string    $level              The log level (debug, info, notice, warning, error etc).
	 * @param string    $title              A short, descriptive title of the event.
	 * @param array     $data               Optional extra data to log.
	 * @param bool|null $is_logging_enabled Whether logging is enabled. Defaults to the setting of the order's gateway, or the parent plugin.
	 *
	 * @return void
	 */
	public static function log_event( $level, $title, $data = array(), $is_logging_enabled = null ) {
		if ( ! self::is_logging_enabled( $is_logging_enabled ) ) {
			return;
		}

		if ( empty( self::$log ) ) {
			self::$log = wc_get_logger();
		}

		self::$log->log( $level, self::format_line( $title, $data ), array( 'source' => self::LOG_SOURCE ) );
	}

	/**
	 * Logs an event.
	 *
	 * Kept for backwards compatibility. Prefer info(), warning() or error().
	 *
	 * @param string|array $data The message string or data to log.
	 * @param bool|null    $is_logging_enabled Whether logging is enabled.
	 */
	public static function log( $data, $is_logging_enabled = null ) {
		if ( is_array( $data ) ) {
			$title = isset( $data['title'] ) ? $data['title'] : 'Log';
			unset( $data['title'] );
			self::log_event( 'debug', $title, self::format_data( $data ), $is_logging_enabled );
			return;
		}

		self::log_event( 'debug', (string) $data, array(), $is_logging_enabled );
	}

	/**
	 * Checks if logging is enabled.
	 *
	 * @param bool|string|null $is_logging_enabled An explicit setting, or null to use the context or parent plugin setting.
	 *
	 * @return bool
	 */
	private static function is_logging_enabled( $is_logging_enabled = null ) {
		if ( is_null( $is_logging_enabled ) ) {
			$is_logging_enabled = isset( self::$context['logging_enabled'] ) ? self::$context['logging_enabled'] : ledyerOm()->parentSettings->get_logger_enabled();
		}

		// The settings are stored as 'yes'/'no' strings.
		return wc_string_to_bool( $is_logging_enabled );
	}

	/**
	 * Formats a log line.
	 *
	 * @param string $title The title of the event.
	 * @param array  $data  The extra data to log.
	 *
	 * @return string
	 */
	public static function format_line( $title, $data = array() ) {
		$context = self::$context;
		$action  = isset( $context['action'] ) ? $context['action'] : '';
		$label   = isset( self::ACTION_LABELS[ $action ] ) ? 'Order Management: ' . self::ACTION_LABELS[ $action ] : 'Order Management';

		$parts = array( "[{$label}] {$title}" );

		$ids = array(
			'session'      => 'ledyer_session_id',
			'ledyer_order' => 'ledyer_order_id',
			'wc_order'     => 'wc_order_id',
		);
		foreach ( $ids as $name => $key ) {
			if ( ! empty( $context[ $key ] ) ) {
				$parts[] = "{$name}: {$context[ $key ]}";
			}
		}

		if ( ! empty( $data ) ) {
			$parts[] = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		return implode( ' | ', $parts );
	}

	/**
	 * Formats the log data to prevent json error. Decodes JSON encoded bodies.
	 *
	 * @param array $data The data containing the JSON-encoded body.
	 *
	 * @return array The decoded JSON data or the original data.
	 */
	public static function format_data( $data ) {
		if ( isset( $data['body'] ) && is_string( $data['body'] ) ) {
			$data['body'] = json_decode( $data['body'], true );
		}

		if ( isset( $data['request']['body'] ) && is_string( $data['request']['body'] ) ) {
			$data['request']['body'] = json_decode( $data['request']['body'], true );
		}

		return $data;
	}

	/**
	 * Logs an API request and its response.
	 *
	 * @param string          $title              The title of the request.
	 * @param string          $method             The HTTP method.
	 * @param string          $url                The request URL.
	 * @param array           $request_args       The request args.
	 * @param array|\WP_Error $response           The response from wp_remote_request.
	 * @param float           $duration           The request duration in seconds.
	 * @param bool|string     $is_logging_enabled Whether logging is enabled for the gateway.
	 * @param array           $extra              Extra data to log, such as the retry attempt.
	 *
	 * @return void
	 */
	public static function log_request( $title, $method, $url, $request_args, $response, $duration, $is_logging_enabled = null, $extra = array() ) {
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$log = self::format_log( '', $method, $title, $request_args, $body, $code );
		unset( $log['id'], $log['type'], $log['title'] );

		$log = array_merge(
			array(
				'method'      => $method,
				'url'         => $url,
				'code'        => $code,
				'duration_ms' => (int) round( $duration * 1000 ),
			),
			$extra,
			self::format_data( $log )
		);

		$is_error = is_wp_error( $response ) || $code < 200 || $code > 299;
		if ( is_wp_error( $response ) ) {
			$log['error'] = $response->get_error_message();
		}

		self::log_event( $is_error ? 'error' : 'info', "API: {$title}", $log, $is_logging_enabled );
	}

	/**
	 * Formats the log data to be logged.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 * @param string $method The method.
	 * @param string $title The title for the log.
	 * @param array  $request_args The request args.
	 * @param array  $response The response.
	 * @param string $code The status code.
	 *
	 * @return array
	 */
	public static function format_log( $ledyer_order_id, $method, $title, $request_args, $response, $code ) {
		// Unset the snippet to prevent issues in the response.
		if ( isset( $response['snippet'] ) ) {
			unset( $response['snippet'] );
		}

		// Mask the access token in the response.
		if ( isset( $response['access_token'] ) ) {
			$response['access_token'] = '[REDACTED]';
		}

		// Mask the authorization header.
		if ( isset( $request_args['headers']['Authorization'] ) ) {
			$replacement                              = strlen( $request_args['headers']['Authorization'] ) > 15 ? '[REDACTED]' : '[MISSING]';
			$request_args['headers']['Authorization'] = $replacement;
		}

		return array(
			'id'             => $ledyer_order_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => gmdate( 'Y-m-d H:i:s' ),
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
			'stack'          => self::get_stack(),
			'plugin_version' => Ledyer_Order_Management_For_WooCommerce::VERSION,
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( in_array( $data['function'], array( 'get_stack', 'format_log', 'log_request' ), true ) ) {
				continue;
			}
			if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
				if ( isset( $data['object'] ) ) {
					$priority   = $data['object']->current_priority();
					$name       = key( $data['object']->current() );
					$extra_data = $name . ' : ' . $priority;
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}

		return $stack;
	}
}
