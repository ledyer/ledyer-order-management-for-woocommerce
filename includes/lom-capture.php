<?php

\defined( 'ABSPATH' ) || die();

/**
 * Captures a Ledyer order for a trusted WordPress integration.
 *
 * @param int $order_id WooCommerce order ID.
 * @return array|WP_Error Capture result or error.
 */
function ledyer_om_capture_order( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || ! lom_order_placed_with_ledyer( $order->get_payment_method() ) ) {
		return new WP_Error( 'ledyer_invalid_order', 'The order is not a Ledyer order.' );
	}

	$ledyer_order_id = $order->get_meta( '_wc_ledyer_order_id', true );
	if ( ! $ledyer_order_id ) {
		return new WP_Error( 'ledyer_order_id_missing', 'The Ledyer order ID is missing.' );
	}

	$api            = ledyerOm()->api;
	$payment_status = $api->get_payment_status( $ledyer_order_id );
	if ( is_wp_error( $payment_status ) ) {
		return $payment_status;
	}

	$status = $payment_status['status'] ?? LedyerOmPaymentStatus::unknown;
	if ( LedyerOmPaymentStatus::orderCaptured === $status ) {
		$ledyer_order = $api->get_order( $ledyer_order_id );
		if ( ! is_wp_error( $ledyer_order ) && in_array( LedyerOmOrderStatus::fullyCaptured, $ledyer_order['status'] ?? array(), true ) ) {
			$first_captured = lom_get_first_captured( $ledyer_order );
			$capture_id     = $first_captured['ledgerId'] ?? '';
			if ( $capture_id ) {
				$order->add_order_note( 'Ledyer order has already been captured.' );
				$order->update_meta_data( '_wc_ledyer_capture_id', $capture_id );
				$order->save();
			}
		}

		return array(
			'result'         => 'already_captured',
			'payment_status' => $payment_status,
		);
	}

	if ( LedyerOmPaymentStatus::paymentConfirmed !== $status ) {
		$message = sprintf( 'The Ledyer order cannot be captured because its payment status is "%s".', $status );
		if ( ! empty( $payment_status['note'] ) ) {
			$message .= ' ' . $payment_status['note'];
		}
		return new WP_Error( 'ledyer_capture_unavailable', $message, $payment_status );
	}

	return lom_attempt_ledyer_order_capture( $order, $ledyer_order_id, $api );
}

/**
 * Captures a Ledyer order.
 *
 * @param int                      $order_id Order ID.
 * @param bool                     $action If this was triggered by an action.
 * @param $api The lom api instance
 */
function lom_capture_ledyer_order( $order_id, $api, $action = false ) {
	$options                         = get_option( 'lom_settings', array() );
	$auto_capture                    = $options['lom_auto_capture'] ?? 'yes';
	$lom_status_mapping_ledyer_error = $options['lom_status_mapping_ledyer_error'] ?? 'wc-on-hold';

	// If the capture on complete is not enabled in lom-settings.
	if ( 'no' === $auto_capture ) {
		return;
	}

	$order = wc_get_order( $order_id );

	// Check if the order has been paid.
	if ( empty( $order->get_date_paid() ) ) {
		return;
	}

	// Only support Ledyer orders
	$is_ledyer_order = lom_order_placed_with_ledyer( $order->get_payment_method() );
	if ( ! $is_ledyer_order ) {
		return;
	}

	// Do nothing if Ledyer order was already captured according to the woo-order
	if ( $order->get_meta( '_wc_ledyer_capture_id', true ) ) {
		$order->add_order_note( 'Ledyer order has already been captured.' );
		$order->save();
		return;
	}

	$ledyer_order_id = $order->get_meta( '_wc_ledyer_order_id', true );

	// Do nothing if we don't have Ledyer order ID.
	if ( $ledyer_order_id && empty( $order->get_transaction_id() ) ) {
		$errmsg = 'Ledyer order ID is missing, Ledyer order could not be captured at this time.';
		if ( 'none' !== $lom_status_mapping_ledyer_error ) {
			$order->update_status( $lom_status_mapping_ledyer_error, $errmsg );
		} else {
			$order->add_order_note( $errmsg );
		}
		$order->save();
		return;
	}

	// Fetch the ledyer order
	$ledyer_order = $api->get_order( $ledyer_order_id );

	if ( is_wp_error( $ledyer_order ) ) {
		$errmsg = 'Ledyer order could not be captured due to an error: ' . $ledyer_order->get_error_message();
		if ( 'none' !== $lom_status_mapping_ledyer_error ) {
			$order->update_status( $lom_status_mapping_ledyer_error, $errmsg );
		} else {
			$order->add_order_note( $errmsg );
		}
		$order->save();
		return;
	}

	if ( in_array( LedyerOmOrderStatus::fullyCaptured, $ledyer_order['status'], true ) ) {
		$first_captured       = lom_get_first_captured( $ledyer_order );
		$captured_at          = $first_captured['createdAt'];
		$formatted_capture_at = date( 'Y-m-d H:i:s', strtotime( $captured_at ) );
		$capture_id           = $first_captured['ledgerId'];

		$order->add_order_note( 'Ledyer order has already been captured on ' . $formatted_capture_at );
		$order->update_meta_data( '_wc_ledyer_capture_id', $capture_id );
		$order->save();
		return;
	} elseif ( in_array( LedyerOmOrderStatus::cancelled, $ledyer_order['status'], true ) ) {
		$order->add_order_note( 'Ledyer order failed to capture, the order has already been cancelled' );
		$order->save();
		return;
	}

	// Check if the order is ready for capture or not. If its not, then either update the order status or print a notice and return.
	if ( ! $order->get_meta( '_ledyer_ready_for_capture', true ) && ! lom_ledyer_order_can_be_captured( $ledyer_order ) ) {
		$errmsg = __( 'Ledyer order is not ready for capture.', 'ledyer-order-management-for-woocommerce' );
		if ( 'none' !== $lom_status_mapping_ledyer_error ) {
			$order->update_status( $lom_status_mapping_ledyer_error, $errmsg );
		} else {
			$order->add_order_note( $errmsg );
		}
		$order->update_meta_data( '_ledyer_waiting_on_ready_for_capture', true );
		$order->save();
		return;
	}

	$response = lom_attempt_ledyer_order_capture( $order, $ledyer_order_id, $api );

	if ( ! is_wp_error( $response ) ) {
		return;
	}

	$errmsg = 'Ledyer order could not be captured due to an error: ' . $response->get_error_message();
	if ( 'none' !== $lom_status_mapping_ledyer_error ) {
		$order->update_status( $lom_status_mapping_ledyer_error, $errmsg );
	} else {
		$order->add_order_note( $errmsg );
	}
	$order->save();
}

/**
 * Performs a full Ledyer capture and saves its capture ID.
 *
 * @param WC_Order $order WooCommerce order.
 * @param string   $ledyer_order_id Ledyer order ID.
 * @param object   $api Ledyer Order Management API instance.
 * @return array|WP_Error Capture result or error.
 */
function lom_attempt_ledyer_order_capture( $order, $ledyer_order_id, $api ) {
	$order_mapper = new \LedyerOm\OrderMapper( $order );
	$data         = $order_mapper->woo_to_ledyer_capture_order_lines();
	$response     = $api->capture_order( $ledyer_order_id, $data );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$capture_id = $response['captured'][0]['ledgerId'] ?? '';
	if ( ! $capture_id ) {
		return new WP_Error( 'ledyer_invalid_capture_response', 'Ledyer returned no capture ID.', $response );
	}

	$order->add_order_note( 'Ledyer order captured. Capture amount: ' . $order->get_formatted_order_total( '', false ) . '. Capture ID: ' . $capture_id );
	$order->update_meta_data( '_wc_ledyer_capture_id', $capture_id );
	$order->save();

	return array(
		'result'       => 'captured',
		'capture_id'   => $capture_id,
		'ledyer_order' => $response,
	);
}

/**
 * Test to see if the order from Ledyer can be captured or not.
 *
 * @param array $ledyer_order The Ledyer order.
 * @return bool True if the order can be captured, false otherwise.
 */
function lom_ledyer_order_can_be_captured( $ledyer_order ) {
	$uncaptured_data = $ledyer_order['uncaptured'] ?? null;

	if ( empty( $uncaptured_data ) ) {
		return false;
	}

	// If the capture type exists in the 'availableActions', then the order can still be captured.
	$can_be_captured = false;
	foreach ( $uncaptured_data['availableActions'] as $action ) {
		if ( $action['type'] === 'capture' ) {
			$can_be_captured = true;
			break;
		}
	}

	return ! empty( $can_be_captured );
}
