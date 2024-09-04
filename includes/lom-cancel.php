<?php

\defined( 'ABSPATH' ) || die();

	/**
	 * Cancel a Ledyer order.
	 *
	 * @param int                      $order_id Order ID.
	 * @param bool                     $action If this was triggered by an action.
	 * @param $api The lom api instance
	 */
function lom_cancel_ledyer_order( $order_id, $api, $action = false ) {
	$options = get_option( 'lom_settings' );

	// If the cancel is not enabled in lom-settings
	if ( 'no' === $options['lom_auto_cancel'] ) {
		return;
	}

	$order = wc_get_order( $order_id );

	// Only support Ledyer orders
	$is_ledyer_order = lom_order_placed_with_ledyer( $order->get_payment_method() );
	if ( ! $is_ledyer_order ) {
		return;
	}

	// Do nothing if Ledyer order has already been cancelled
	if ( $order->get_meta( '_wc_ledyer_cancelled', true ) ) {
		$order->add_order_note( 'Ledyer order has already been cancelled.' );
		$order->save();
		return;
	}

	$ledyer_order_id = $order->get_meta( '_wc_ledyer_order_id', true );

	// Do nothing if we don't have Ledyer order ID.
	if ( ! $ledyer_order_id && ! $order->get_meta( '_transaction_id', true ) ) {
		$errmsg = 'Ledyer order ID is missing, Ledyer order could not be cancelled at this time.';
		$order->add_order_note( $errmsg );
		$order->save();
		return;
	}

	// Fetch the ledyer order
	$ledyer_order = $api->get_order( $ledyer_order_id );

	if ( is_wp_error( $ledyer_order ) ) {
		$errmsg = 'Ledyer order could not be cancelled due to an error: ' . $ledyer_order->get_error_message();
		$order->add_order_note( $errmsg );
		$order->save();
		return;
	}

	if ( $ledyer_order['uncaptured'] == null ) {
		$order->add_order_note( 'Ledyer order can not be cancelled because it has already been captured' );
		$order->save();
		return;
	} elseif ( in_array( LedyerOmOrderStatus::cancelled, $ledyer_order['status'] ) ) {
		$order->add_order_note( 'Ledyer order has already been cancelled' );
		$order->save();
		return;
	}

	$response = $api->cancel_order( $ledyer_order_id );

	if ( ! is_wp_error( $response ) ) {
		$order->add_order_note( 'Ledyer order cancelled.' );
		$order->update_meta_data( '_wc_ledyer_cancelled', 'yes' );
		$order->save();
		return;
	}

	$errmsg = 'Ledyer order could not be cancelled due to an error: ' . $response->get_error_message();
	$order->add_order_note( $errmsg );
	$order->save();
}
