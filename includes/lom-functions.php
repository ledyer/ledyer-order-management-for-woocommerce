<?php
/**
 * Functions file for the plugin.
 *
 * @package LedyerOm
 */

\defined( 'ABSPATH' ) || die();

function order_placed_with_ledyer($payment_method) {
	if ( in_array($payment_method, array('ledyer_payments', 'lco')) ) {
		return true;
	}

	return false;
}

function get_first_captured($ledyer_order) {
	$captured = $ledyer_order['captured'];
	return $captured[0];
}

// We only accept full refunds, so the refunded amount must be the same as the woo-orders total amount
function ledyer_om_ensure_refund_full_order_amount($amount, $order, $ledyer_order) {
	return $order->get_total() == $amount;
}