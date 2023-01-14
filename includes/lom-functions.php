<?php
/**
 * Functions file for the plugin.
 *
 * @package LedyerOm
 */

\defined( 'ABSPATH' ) || die();

/**
 * Dummy
 *
 * @return boolean
 */
function lom_hello_world() {
	echo 'hello world from functions';

	return false;
}

function order_placed_with_ledyer($payment_method) {
	if ( in_array($payment_method, array('ledyer_payments', 'lco')) ) {
		return true;
	} else {
		return false;
	}
}

function get_first_captured($ledyer_order) {
	$captured = $ledyer_order['captured'];
	return $captured[0];
}