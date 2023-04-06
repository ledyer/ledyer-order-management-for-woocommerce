<?php
/**
 * Functions file for the plugin.
 *
 * @package LedyerOm
 */

\defined( 'ABSPATH' ) || die();

function lom_order_placed_with_ledyer($payment_method) {
	if ( in_array($payment_method, array('ledyer_payments', 'lco')) ) {
		return true;
	}

	return false;
}

function lom_get_first_captured($ledyer_order) {
	$captured = $ledyer_order['captured'];
	return $captured[0];
}
