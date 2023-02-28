<?php

\defined( 'ABSPATH' ) || die();

/**
 * Edit a Ledyer order.
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 */
function edit_ledyer_order($order_id, $action = false, $api, $syncType ) {
	$options = get_option( 'lom_settings' );
	if ( 'no' === $options['lom_auto_update']) {
		return;
	}

	$order = wc_get_order( $order_id );

	// Only support Ledyer orders
	$is_ledyer_order = order_placed_with_ledyer($order->get_payment_method());
	if (! $is_ledyer_order) {
		return;
	}

	if ( $order->has_status( array( 'completed', 'refunded', 'cancelled' ) ) ) {
		return;
	}

	$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);

	// Do nothing if we don't have Ledyer order ID.
	if ( ! $ledyer_order_id && ! get_post_meta( $order_id, '_transaction_id', true ) ) {
		$order->add_order_note( 'Ledyer order ID is missing, Ledyer order could not be updated at this time.' );
		return;
	}

	// Fetch the ledyer order
	$ledyer_order = $api->get_order($ledyer_order_id);

	if ( is_wp_error( $ledyer_order ) ) {
		$errmsg = 'Ledyer order could not be updated due to an error: ' . $ledyer_order->get_error_message();
		$order->add_order_note( $errmsg );
		return;
	}

	// For now we only allow editing for an exclusivly uncaptured order. 
	$editable = count($ledyer_order['status']) == 1 && in_array( LedyerOmOrderStatus::uncaptured, $ledyer_order['status']);
	if (!$editable) {
		$order->add_order_note( 'Ledyer order has been captured or cancelled, Ledyer order could not be updated at this time.' );
		return;
	} 

	if ("order" === $syncType) {
		$orderMapper = new \LedyerOm\OrderMapper($order);
		$data = $orderMapper->woo_to_ledyer_edit_order_lines();
		$response = $api->edit_order($ledyer_order_id, $data);
		if (!is_wp_error($response)) {
			$order->add_order_note( 'Ledyer order updated.' );
		} else {
			$errmsg = 'Ledyer order data could not be updated due to an error: ' . $response->get_error_message();
			$order->add_order_note( $errmsg );
		}
	} else if ("customer" === $syncType) {
		$customerMapper = new \LedyerOm\CustomerMapper($order);
		$data = $customerMapper->woo_to_ledyer_customer();
		$response = $api->edit_customer($ledyer_order_id, $data);
		if (!is_wp_error($response)) {
			$order->add_order_note( 'Ledyer customer updated.' );
		} else {
			$errmsg = 'Ledyer customer data could not be updated due to an error: ' . $response->get_error_message();
			$order->add_order_note( $errmsg );
		}
	}
}