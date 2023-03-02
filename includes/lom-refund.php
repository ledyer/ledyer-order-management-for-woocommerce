<?php

\defined( 'ABSPATH' ) || die();

/**
 * Refunds a Ledyer order. Only supports fullRefund and refunds on an order that has been fullCaptured
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 */
	function refund_ledyer_order($result, $order_id, $amount, $reason, $api) {
		$order = wc_get_order( $order_id );

		// Only support Ledyer orders
		$is_ledyer_order = order_placed_with_ledyer($order->get_payment_method());
		if (! $is_ledyer_order) {
			return false;
		}

		// Do nothing if Ledyer order was not already captured via Woocommerce
		if ( ! get_post_meta( $order_id, '_wc_ledyer_capture_id', true ) ) {
			$order->add_order_note( 'Ledyer order has not been captured / not captured through Woocommerce and therefore cannot be refunded.' );
			return false;
		}

		$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);
		$ledyer_order = $api->get_order($ledyer_order_id);

		if ( is_wp_error( $ledyer_order ) ) {
			$errmsg = 'Ledyer order could not be refunded due to an error: ' . $ledyer_order->get_error_message();
			$order->add_order_note( $errmsg );
			return false;
		}
		
		$ledyer_payment_status_response = $api->get_payment_status( $ledyer_order_id );
		if ( is_wp_error( $ledyer_payment_status_response ) ) {
			$errmsg = $ledyer_payment_status_response->get_error_message();
			$order->add_order_note( 'Ledyer order could not be refunded, failed to get order payment status. ' . $errmsg );
			return false;
		}

		$ledyer_payment_status = $ledyer_payment_status_response['status'];
		if ( $ledyer_payment_status !== LedyerOmPaymentStatus::orderCaptured ) {
			$order->add_order_note( 'Ledyer order could not be refunded, Ledyer order is not fully captured');
			return false;
		}
		
		$ledyer_order_multiple_captures = count($ledyer_order['captured']) > 1;
		if ( $ledyer_order_multiple_captures ) {
			$order->add_order_note( 'Ledyer order could not be refunded, the Ledyer order has been captured multiple times, partial refunds are only supported in the Ledyer Merchant Portal');
			return false;
		}

		$captured_ledger_id = $ledyer_order['captured'][0]['ledgerId'];

		$refund_order = $order->get_refunds()[0];
		$orderMapper = new \LedyerOm\OrderMapper($refund_order);
		$data = $orderMapper->woo_to_ledyer_refund_order_lines();
		$response = $api->partial_refund_order($ledyer_order_id, $captured_ledger_id, $data);

		if (!is_wp_error($response)) {
			$order->add_order_note( wc_price( $amount, array( 'currency' => get_post_meta( $order_id, '_order_currency', true ) ) ) . ' refunded via Ledyer.' );
			// set the captured meta tag to false after a successful refund
			update_post_meta( $order_id, '_wc_ledyer_capture_id', false, true );
			// If all went well, return true to let woocommerce know that the refund was successful
			return true;
		}

		$errmsg = 'Ledyer order could not be refunded due to an error: ' . $response->get_error_message();
		$order->add_order_note( $errmsg);
		return false;
		
	}