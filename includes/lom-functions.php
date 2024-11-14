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
