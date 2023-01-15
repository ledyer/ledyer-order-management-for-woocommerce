<?php

\defined( 'ABSPATH' ) || die();

/**
 * Captures a Ledyer order.
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 */
	function capture_ledyer_order($order_id, $action = false, $api)
	{
		$options = get_option( 'lom_settings' );
		// if the capture on complete is not enabled in lom-settings
		if ( 'no' === $options['lom_auto_capture']) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Check if the order has been paid. get_date_paid() gets a built in woocommerce property
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		// Only do ledyer capture on orders that was placed with Ledyer Checkout or Ledyer payments
		// Not going to do this for non-LP and non-LCO orders.
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
			$order->add_order_note( 'Ledyer order ID is missing, Ledyer order could not be captured at this time.' );
			$order->set_status( 'on-hold' );
			$order->save();
			return;
		}

		// Fetch the ledyer order
		$ledyer_order = $api->get_order($ledyer_order_id);

		if ( is_wp_error( $ledyer_order ) ) {
			$order->add_order_note( 'Ledyer order could not be captured due to an error. Ordet set to On hold' );
			$order->set_status( 'on-hold' );
			$order->save();
			return;
		}

		switch ($ledyer_order['status'][0]) {
			case \LedyerOrderStatus::fullyCaptured:
				$first_captured = get_first_captured($ledyer_order);
				$captured_at = $first_captured['createdAt'];
				$formatted_capture_at = date("Y-m-d H:i:s", strtotime($captured_at));
				$capture_id = $first_captured['ledgerId'];

				$order->add_order_note( 'Ledyer order has already been captured on ' . $formatted_capture_at );
				update_post_meta( $order_id, '_wc_ledyer_capture_id', $capture_id );
				return;
			case \LedyerOrderStatus::cancelled:
				$order->add_order_note( 'Ledyer order failed to capture, the order has already been cancelled' );
				return;
		}

		$capture_ledyer_order_response = $api->capture_order($ledyer_order_id);
		
		if (!is_wp_error($capture_ledyer_order_response)) {
			$first_captured = get_first_captured($capture_ledyer_order_response);
			$capture_id = $first_captured['ledgerId'];

			$order->add_order_note( 'Ledyer order captured. Capture amount: ' . $order->get_formatted_order_total( '', false ) . '. Capture ID: ' . $capture_id );
			update_post_meta($order_id, '_wc_ledyer_capture_id', $capture_id, true);
			return;
		}

		/*
		 * Capture failed error handling
		 * 
		 * The suggested approach by Ledyer is to try again after some time.
		 * If that still fails, the merchant should inform the customer,
		 * and ask them to either "create a new subscription or add funds to their payment method if they wish to continue."
		 */

		$httpErrorCode = $capture_ledyer_order_response->get_error_code();
		$httpErrorMessage = $capture_ledyer_order_response->get_error_message();
		$order = wc_get_order( $order_id );

		if (isset($httpErrorCode)) {
			$order_error_note = null;

			switch ($httpErrorCode) {
				case 401:
					$order_error_note = 'Ledyer could not charge the customer. The capture was unauthorized, ' . $httpErrorMessage;
				case 403:
					$order_error_note = 'Ledyer could not charge the customer. Please try again later. If that still fails, the customer may have to create a new subscription or add funds to their payment method if they wish to continue. ' . $httpErrorMessage;
				case 404:
					$order_error_note = 'Ledyer could not charge the customer, ' . $httpErrorMessage;
				default:
					$order_error_note = 'Ledyer could not charge the customer, ' . $httpErrorMessage;
			}
		} else {
			$order_error_note = 'Ledyer could not charge the customer, an unhandled exception was encounted, ' . $httpErrorMessage;
		}
		
		$order->add_order_note( __( $order_error_note ) );
		$order->set_status( 'on-hold' );
		$order->save();
	}