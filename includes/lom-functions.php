<?php
/**
 * Functions file for the plugin.
 *
 * @package LedyerOm
 */

\defined( 'ABSPATH' ) || die();

function lom_order_placed_with_ledyer( $payment_method ) {
	if ( in_array( $payment_method, array( 'ledyer_payments', 'lco' ) ) ) {
		return true;
	}

	return false;
}

function lom_get_first_captured( $ledyer_order ) {
	$captured = $ledyer_order['captured'];
	return $captured[0];
}

/**
 * Get the WC order ID based on Ledyer order ID.
 *
 * @param string $ledyer_order_id  The merchant reference from dintero.
 * @return int The WC order ID or 0 if no match was found.
 */
function lom_get_order_id_by_ledyer_order_id( $ledyer_order_id ) {
	$key    = '_wc_ledyer_order_id';
	$orders = wc_get_orders(
		array(
			'meta_key'     => $key,
			'meta_value'   => $ledyer_order_id,
			'limit'        => 1,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'meta_compare' => '=',
		)
	);

	$order = reset( $orders );
	if ( empty( $order ) || $ledyer_order_id !== $order->get_meta( $key ) ) {
		return 0;
	}

	return $order->get_id() ?? 0;
}

/**
 * Ensure that a value is numeric. If the value is not numeric, it will attempt to convert it.
 * If the value is an empty value, it will be set to 0.
 * If the value cannot be converted to a numeric value, it will return the default value.
 *
 * @param mixed     $value The value to ensure is numeric.
 * @param float|int $default The default value to return if the value is not numeric and $throw_error is false. Default 0.
 *
 * @return float|int Returns the numeric value of the input, or the default value if the input is not numeric and cannot be converted.
 */
function ledyer_om_ensure_numeric( $value, $default = 0 ) {
	if ( is_numeric( $value ) ) {
		return floatval( $value );
	}

	// If the value is empty, return 0 instead of default to reflect that the value is not set.
	if ( empty( $value ) ) {
		return 0;
	}

	// Try to convert the value to a numeric value.
	$converted_value = floatval( $value );

	if ( is_numeric( $converted_value ) ) {
		return $converted_value;
	}

	return $default; // Return the default value if the value is still not numeric.
}
