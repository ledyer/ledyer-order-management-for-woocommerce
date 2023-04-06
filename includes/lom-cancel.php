<?php

\defined( 'ABSPATH' ) || die();

	/**
	 * Cancel a Ledyer order.
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $action If this was triggered by an action.
	 * @param $api The lom api instance
	 */
	function lom_cancel_ledyer_order($order_id, $action = false, $api) {
		$options = get_option( 'lom_settings' );
		// If the cancel is not enabled in lom-settings
		if ( 'no' === $options['lom_auto_cancel']) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Only support Ledyer orders
		$is_ledyer_order = lom_order_placed_with_ledyer($order->get_payment_method());
		if (! $is_ledyer_order) {
			return;
		}

		// Do nothing if Ledyer order has already been cancelled
		if ( get_post_meta( $order_id, '_wc_ledyer_cancelled', true ) ) {
			$order->add_order_note( 'Ledyer order has already been cancelled.' );
			return;
		}

		$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);

		// Do nothing if we don't have Ledyer order ID.
		if ( ! $ledyer_order_id && ! get_post_meta( $order_id, '_transaction_id', true ) ) {
			$order->update_status( 'on-hold', 'Ledyer order ID is missing, Ledyer order could not be cancelled at this time.' );
			return;
		}

		// Fetch the ledyer order
		$ledyer_order = $api->get_order($ledyer_order_id);

		if ( is_wp_error( $ledyer_order ) ) {
			$errmsg = 'Ledyer order could not be cancelled due to an error: ' . $ledyer_order->get_error_message();
			$order->update_status( 'on-hold', $errmsg );
			return;
		}

		if ($ledyer_order['uncaptured'] == null) {
			$order->add_order_note( 'Ledyer order can not be cancelled because it has already been captured' );
			return;
		} else if ( in_array( LedyerOmOrderStatus::cancelled, $ledyer_order['status']) ) {
			$order->add_order_note( 'Ledyer order has already been cancelled' );
			return;
		}

		$response = $api->cancel_order($ledyer_order_id);
		
		if (!is_wp_error($response)) {
			$order->add_order_note( 'Ledyer order cancelled.' );
			update_post_meta( $order_id, '_wc_ledyer_cancelled', 'yes', true );
			return;
		}

		$errmsg = 'Ledyer order could not be cancelled due to an error: ' . $response->get_error_message();
		$order->update_status( 'on-hold', $errmsg);
	}