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
 * Log error messages
 */
class Logger {
	/**
	 * The WC_Logger instance.
	 *
	 * @var \WC_Logger
	 */
	private static $log;

	/**
	 * Logs an event.
	 *
	 * @param string $data The data string.
	 * @param bool   $is_logging_enabled Whether logging is enabled.
	 */
	public static function log( $data, $is_logging_enabled = null ) {
		$is_logging_enabled = is_null( $is_logging_enabled ) ? wc_string_to_bool( ledyerOm()->parentSettings->get_logger_enabled() ) : $is_logging_enabled;

		if ( $is_logging_enabled ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new \WC_Logger();
			}
			$context = array( 'source' => 'ledyer-log' );
			self::$log->log( 'debug', stripcslashes( wp_json_encode( $message ) ), $context );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param array $data The data containing the JSON-encoded body.
	 *
	 * @return array The decoded JSON data or the original data.
	 */
	public static function format_data( $data ) {
		if ( isset( $data['body'] ) ) {
			$data['body'] = json_decode( $data['body'], true );
		}

		return $data;
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
		// Unset the snippet to prevent issues in the request body.
		if ( isset( $request_args['body'] ) ) {
			$request_body = json_decode( $request_args['body'], true );
		}

		return array(
			'id'             => $ledyer_order_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'response'       => array(
				'body' => $request_body ?? $response,
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
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ), true ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
					if ( isset( $data['object'] ) ) {
						$priority   = $data['object']->current_priority();
						$name       = key( $data['object']->current() );
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}

		return $stack;
	}
}
