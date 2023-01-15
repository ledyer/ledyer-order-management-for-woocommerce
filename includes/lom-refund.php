<?php

\defined( 'ABSPATH' ) || die();

/**
 * Refunds a Ledyer order. Only supports fullRefund and refunds on an order that has been fullCaptured
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 */
	function refund_ledyer_order($result, $order_id, $amount, $reason, $api)
	{
		$order = wc_get_order( $order_id );

		// Only do ledyer capture on orders that was placed with Ledyer Checkout or Ledyer payments
		// Not going to do this for non-LP and non-LCO orders.
		$is_ledyer_order = order_placed_with_ledyer($order->get_payment_method());
		if (! $is_ledyer_order) {
			return false;
		}

		// Do nothing if Ledyer order was not already captured via Woocommerce
		if ( ! get_post_meta( $order_id, '_wc_ledyer_capture_id', true ) ) {
			$order->add_order_note( 'Ledyer order has not been captured / not captured through Woocommerce and therefore not be refunded.' );
			return false;
		}

		$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);
		$ledyer_order = $api->get_order($ledyer_order_id);

		if ( is_wp_error( $ledyer_order ) ) {
			$get_ledyer_order_error_message = $ledyer_order->get_error_message();
			$order->add_order_note( 'Ledyer order could not be refunded due to an error when fetching the order. ' . $get_ledyer_order_error_message );
			return;
		}
		
		$ledyer_payment_status_response = $api->get_payment_status( $ledyer_order_id );
		if ( is_wp_error( $ledyer_payment_status_response ) ) {
			$get_ledyer_order_error_message = $ledyer_order->get_error_message();
			$order->add_order_note( 'Ledyer order could not be refunded, failed to get order payment status. ' . $get_ledyer_order_error_message );
			return false;
		}

		$ledyer_payment_status = $ledyer_payment_status_response['status'];
		$refund_full_order_amount = ledyer_om_ensure_refund_full_order_amount($amount, $order, $ledyer_order);
		$ledyer_order_multiple_captures = count($ledyer_order['captured']) > 1;

		// We can be sure that the order was fullyCaptured since we already checked for the _wc_ledyer_capture_id meta tag
		if ( $ledyer_payment_status !== LedyerOmPaymentStatus::orderCaptured ) {
			$order->add_order_note( 'Ledyer order could not be refunded, Ledyer payment status is not in orderCaptured');
			return false;
		}
		
		// We can be sure that the order was fullyCaptured since we already checked for the _wc_ledyer_capture_id meta tag
		if ( ! $refund_full_order_amount ) {
			$order->add_order_note( 'Ledyer order could not be refunded, the set amount does not match the full order amount');
			return false;
		}
		
		// We can be sure that the order was fullyCaptured since we already checked for the _wc_ledyer_capture_id meta tag
		if ( $ledyer_order_multiple_captures ) {
			$order->add_order_note( 'Ledyer order could not be refunded, the Ledyer order has been captured multiple times, partial refunds are only supported in the Ledyer Merchant Portal');
			return false;
		}

		$captured_ledger_id = $ledyer_order['captured'][0]['ledgerId'];
		$refund_response = $api->refund_order($ledyer_order_id, $captured_ledger_id);
		if ( is_wp_error( $refund_response ) ) {
			$ledyer_refund_error_message = $ledyer_order->get_error_message();
			$order->add_order_note( 'Ledyer order could not be refunded, the refund request failed. ' . $ledyer_refund_error_message );
			return false;
		}

		$order->add_order_note( wc_price( $amount, array( 'currency' => get_post_meta( $order_id, '_order_currency', true ) ) ) . ' refunded via Ledyer.' );

		// set the captured meta tag to false after a successful refund
		update_post_meta( $order_id, '_wc_ledyer_capture_id', false, true );

		// If all went well, return true to let woocommerce know that the refund was successful
		return true;
	}