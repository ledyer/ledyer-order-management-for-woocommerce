<?php
/**
 * API Class file.
 *
 * @package LedyerOm
 */
namespace LedyerOm;

\defined( 'ABSPATH' ) || die();

use LedyerOm\Requests\Order\Payment_Status;
use LedyerOm\Requests\Order\Get_Order;
use LedyerOm\Requests\Order\Capture_Order;
use LedyerOm\Requests\Order\Refund_Order;
use LedyerOm\Requests\Order\Partial_Refund_Order;
use LedyerOm\Requests\Order\Cancel_Order;
use LedyerOm\Requests\Order\Edit_Order;
use LedyerOm\Requests\Order\Edit_Customer;

/**
 * API class.
 *
 * Class that has functions for the ledyer communication.
 */
class API {

	/**
	 * Get the payment status of an order.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 *
	 * @return mixed|\WP_Error
	 */
	public function get_payment_status( $ledyer_order_id ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Payment_Status( array( 'orderId' => $ledyer_order_id ), $order_id ) )->request();
	}

	/**
	 * Get the order details.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 *
	 * @return mixed|\WP_Error
	 */
	public function get_order( $ledyer_order_id ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Get_Order( array( 'orderId' => $ledyer_order_id ), $order_id ) )->request();
	}

	/**
	 * Capture an order.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 * @param array  $data The data to send.
	 *
	 * @return mixed|\WP_Error
	 */
	public function capture_order( $ledyer_order_id, $data ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Capture_Order(
			array(
				'orderId' => $ledyer_order_id,
				'data'    => $data,
			),
			$order_id
		) )->request();
	}


	/**
	 * Refund an order.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 * @param string $ledger_id The ledger id.
	 *
	 * @return mixed|\WP_Error
	 */
	public function refund_order( $ledyer_order_id, $ledger_id ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Refund_Order(
			array(
				'orderId'  => $ledyer_order_id,
				'ledgerId' => $ledger_id,
			),
			$order_id
		) )->request();
	}

	/**
	 * Partial refund an order.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 * @param string $ledger_id The ledger id.
	 * @param array  $data The data to send.
	 *
	 * @return mixed|\WP_Error
	 */
	public function partial_refund_order( $ledyer_order_id, $ledger_id, $data ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Partial_Refund_Order(
			array(
				'orderId'  => $ledyer_order_id,
				'ledgerId' => $ledger_id,
				'data'     => $data,
			),
			$order_id
		) )->request();
	}

	/**
	 * Cancel an order.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 *
	 * @return mixed|\WP_Error
	 */
	public function cancel_order( $ledyer_order_id ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Cancel_Order( array( 'orderId' => $ledyer_order_id ), $order_id ) )->request();
	}

	/**
	 * Edit an order.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 * @param array  $data The data to send.
	 *
	 * @return mixed|\WP_Error
	 */
	public function edit_order( $ledyer_order_id, $data ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Edit_Order(
			array(
				'orderId' => $ledyer_order_id,
				'data'    => $data,
			),
			$order_id
		) )->request();
	}

	/**
	 * Edit a customer.
	 *
	 * @param string $ledyer_order_id The Ledyer order id.
	 * @param array  $data The data to send.
	 *
	 * @return mixed|\WP_Error
	 */
	public function edit_customer( $ledyer_order_id, $data ) {
		$order_id = lom_get_order_id_by_ledyer_order_id( $ledyer_order_id );
		return ( new Edit_Customer(
			array(
				'orderId' => $ledyer_order_id,
				'data'    => $data,
			),
			$order_id
		) )->request();
	}
}
