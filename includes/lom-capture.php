<?php

\defined( 'ABSPATH' ) || die();

/**
 * Captures a Ledyer order.
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 */
	function capture_ledyer_order($order_id, $action = false, $api) {
		$options = get_option( 'lom_settings' );
		// If the capture on complete is not enabled in lom-settings
		if ( 'no' === $options['lom_auto_capture']) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		// Only support Ledyer orders
		$is_ledyer_order = order_placed_with_ledyer($order->get_payment_method());
		if (! $is_ledyer_order) {
			return;
		}

		// Do nothing if Ledyer order was already captured according to the woo-order
		if ( get_post_meta( $order_id, '_wc_ledyer_capture_id', true ) ) {
			$order->add_order_note( 'Ledyer order has already been captured.' );
			return;
		}

		$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);

		// Do nothing if we don't have Ledyer order ID.
		if ( ! $ledyer_order_id && ! get_post_meta( $order_id, '_transaction_id', true ) ) {
			$order->update_status( 'on-hold', 'Ledyer order ID is missing, Ledyer order could not be captured at this time.' );
			return;
		}

		// Fetch the ledyer order
		$ledyer_order = $api->get_order($ledyer_order_id);

		if ( is_wp_error( $ledyer_order ) ) {
			$errmsg = 'Ledyer order could not be captured due to an error: ' . $ledyer_order->get_error_message();
			$order->update_status( 'on-hold', $errmsg );
		}

		if (in_array( LedyerOmOrderStatus::fullyCaptured, $ledyer_order['status'])) {
			$first_captured = get_first_captured($ledyer_order);
			$captured_at = $first_captured['createdAt'];
			$formatted_capture_at = date("Y-m-d H:i:s", strtotime($captured_at));
			$capture_id = $first_captured['ledgerId'];

			$order->add_order_note( 'Ledyer order has already been captured on ' . $formatted_capture_at );
			update_post_meta( $order_id, '_wc_ledyer_capture_id', $capture_id );
			return;
		} else if (in_array( LedyerOmOrderStatus::cancelled, $ledyer_order['status'] )) {
			$order->add_order_note( 'Ledyer order failed to capture, the order has already been cancelled' );
			return;
		}

		$response = $api->capture_order($ledyer_order_id);
		
		if (!is_wp_error($response)) {
			$first_captured = get_first_captured($response);
			$capture_id = $first_captured['ledgerId'];

			$order->add_order_note( 'Ledyer order captured. Capture amount: ' . $order->get_formatted_order_total( '', false ) . '. Capture ID: ' . $capture_id );
			update_post_meta($order_id, '_wc_ledyer_capture_id', $capture_id, true);
			return;
		}

		$errmsg = 'Ledyer order could not be captured due to an error: ' . $response->get_error_message();
		$order->update_status( 'on-hold', $errmsg);
	}