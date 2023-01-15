<?php

\defined( 'ABSPATH' ) || die();

/**
 * Captures a Ledyer order.
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 */
	function cancel_ledyer_order($order_id, $action = false, $api)
	{
		$options = get_option( 'lom_settings' );
		// if the capture on complete is not enabled in lom-settings
		if ( 'no' === $options['lom_auto_cancel']) {
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

		// Do nothing if Ledyer order has already been cancelled
		if ( get_post_meta( $order_id, '_wc_ledyer_cancelled', true ) ) {
			$order->add_order_note( 'Ledyer order has already been cancelled.' );
			return;
		}

		$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);

		// Do nothing if we don't have Ledyer order ID.
		if ( ! $ledyer_order_id && ! get_post_meta( $order_id, '_transaction_id', true ) ) {
			$order->add_order_note( 'Ledyer order ID is missing, Ledyer order could not be cancelled at this time.' );
			$order->set_status( 'on-hold' );
			$order->save();
			return;
		}

		// Fetch the ledyer order
		$ledyer_order = $api->get_order($ledyer_order_id);

		if ( is_wp_error( $ledyer_order ) ) {
      $httpErrorCode = $ledyer_order->get_error_code();
		  $httpErrorMessage = $ledyer_order->get_error_message();

			$order->add_order_note( 'Ledyer order could not be cancelled due to an error (' . $httpErrorCode . ', ' . $httpErrorMessage . ')' );
			return;
		}

    if ($ledyer_order['uncaptured'] == null) {
      $order->add_order_note( 'Ledyer order can not be cancelled because it has already been captured' );
      return;
    } else if ( in_array($ledyer_order['status'], array(\LedyerOrderStatus::cancelled)) ) {
      $order->add_order_note( 'Ledyer order has already been cancelled' );
      return;
    } else if ( 'advanceInvoice' == $ledyer_order['paymentMethod']['type'] && in_array($ledyer_order['status'], array(\LedyerOrderStatus::unacknowledged)) ) {
      $order->add_order_note( 'Ledyer order of type Advanced invoice has already been acknowledged by Ledyer and can not be cancelled' );
      return;
    }

		$cancel_ledyer_order_response = $api->cancel_order($ledyer_order_id);
		
		if (!is_wp_error($cancel_ledyer_order_response)) {
			$order->add_order_note( 'Ledyer order cancelled.' );
      update_post_meta( $order_id, '_wc_ledyer_cancelled', 'yes', true );
			return;
		} else {

    }

		/*
		 * Capture failed error handling
		 * 
		 * The suggested approach by Ledyer is to try again after some time.
		 * If that still fails, the merchant should inform the customer,
		 * and ask them to either "create a new subscription or add funds to their payment method if they wish to continue."
		 */

		$httpErrorCode = $cancel_ledyer_order_response->get_error_code();
		$httpErrorMessage = $cancel_ledyer_order_response->get_error_message();
		$order = wc_get_order( $order_id );

		if (isset($httpErrorCode)) {
			$order_error_note = null;

			switch ($httpErrorCode) {
				case 401:
					$order_error_note = 'Ledyer could not cancel the order. The cancel was unauthorized, ' . $httpErrorMessage;
				case 403:
					$order_error_note = 'Ledyer could not cancel the order. Please try again later. If that still fails, login to the Ledyer merchant admin and look at the order there (' . $$ledyer_order_id . '), ' . $httpErrorMessage;
				case 404:
					$order_error_note = 'Ledyer could not cancel the order, ' . $httpErrorMessage;
				default:
					$order_error_note = 'Ledyer could not cancel the order, ' . $httpErrorMessage;
			}
		} else {
			$order_error_note = 'Ledyer could not cancel the order, an unhandled exception was encountered, ' . $httpErrorMessage;
		}
		
		$order->add_order_note( __( $order_error_note ) );
		$order->set_status( 'on-hold' );
		$order->save();
	}