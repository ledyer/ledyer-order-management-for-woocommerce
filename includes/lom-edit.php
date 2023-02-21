<?php

\defined( 'ABSPATH' ) || die();

/**
 * Validate edit Ledyer order.
 *
 * @param $order The woo order (must contain changes array)
 * @param bool $action If this was triggered by an action.
 * @param string $syncType order or customer
 */
function validate_edit_ledyer_order($order, $action = false, $syncType ) {
	$options = get_option( 'lom_settings' );
	if ( 'no' === $options['lom_auto_update']) {
		return;
	}
	
	if ( ! allow_editing($order) ) {
		return;
	}

	if ("customer" === $syncType) {
		validate_customer_field($order, 'billing_company', 0, 100);
		validate_customer_field($order, 'billing_address_1', 0, 100);
		validate_customer_field($order, 'billing_address_2', 0, 100);
		validate_customer_field($order, 'billing_postcode', 0, 10);
		validate_customer_field($order, 'billing_city', 0, 50);
		validate_customer_field($order, 'billing_country', 0, 50);
		// TODO careof
		// TODO attention
		
		validate_customer_field($order, 'shipping_company', 0, 100);
		validate_customer_field($order, 'shipping_address_1', 0, 100);
		validate_customer_field($order, 'shipping_address_2', 0, 100);
		validate_customer_field($order, 'shipping_postcode', 0, 10);
		validate_customer_field($order, 'shipping_city', 0, 50);
		validate_customer_field($order, 'shipping_country', 0, 50);
		validate_customer_field($order, 'shipping_first_name', 0, 200);
		validate_customer_field($order, 'shipping_last_name', 0, 200);
		validate_customer_field($order, 'shipping_phone', 9, 15);
		// TODO contact email
	}
}
	

/**
 * Edit a Ledyer order.
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 * @param string s$syncType order or customer
 */
function edit_ledyer_order($order_id, $action = false, $api, $syncType ) {
	$options = get_option( 'lom_settings' );
	if ( 'no' === $options['lom_auto_update']) {
		return;
	}

	$order = wc_get_order( $order_id );

	if ( ! allow_editing($order) ) {
		return;
	}

	$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);

	if ( ! $ledyer_order_id && ! get_post_meta( $order_id, '_transaction_id', true ) ) {
		$order->add_order_note( 'Ledyer order ID is missing, Ledyer order could not be updated at this time.' );
		return false;
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

function validate_customer_field($order, $fieldName, $min, $max) {
	$changes = $order->get_changes();
	$field = $changes[$fieldName];
	$valid = validate_field_length($field, $min, $max);
	if (!$valid) {
		$order->add_order_note( 'Ledyer customer data could not be updated. Invalid ' . $fieldName);
		wp_safe_redirect( wp_get_referer() );
		exit;
	}
}

function validate_field_length($str, $min, $max){
	if (!$str) {
		return true;
	}
	$len = strlen($str);
	return !($len < $min || $len > $max);
}

function allow_editing($order) {
	$is_ledyer_order = order_placed_with_ledyer($order->get_payment_method());
	if (! $is_ledyer_order) {
		return false;
	}

	if ( ! in_array( $order->get_status(), apply_filters( 'lom_allowed_update_statuses', array( 'on-hold' ) ), true ) ) {
		return false;
	}
	
	return true;
}