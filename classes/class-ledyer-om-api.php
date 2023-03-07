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
	 * @param $order_id
	 *
	 * @return mixed|\WP_Error
	 */
	public function get_payment_status( $order_id ) {
		return ( new Payment_Status( array( 'orderId' => $order_id ) ) )->request();
	}

	public function get_order( $order_id ) {
		return ( new Get_Order( array( 'orderId' => $order_id ) ) )->request();
	}
	
	public function capture_order( $order_id, $data ) {
		return ( new Capture_Order( array( 'orderId' => $order_id, 'data' => $data ) ) )->request();
	}

	public function refund_order( $order_id, $ledger_id ) {
		return ( new Refund_Order( array( 'orderId' => $order_id, 'ledgerId' => $ledger_id) ) )->request();
	}

	public function partial_refund_order( $order_id, $ledger_id, $data ) {
		return ( new Partial_Refund_Order( array( 'orderId' => $order_id, 'ledgerId' => $ledger_id, 'data' => $data) ) )->request();
	}

	public function cancel_order( $order_id ) {
		return ( new Cancel_Order( array( 'orderId' => $order_id ) ) )->request();
	}

	public function edit_order( $order_id, $data ) {
		return ( new Edit_Order( array('orderId' => $order_id, 'data' => $data ) ) )->request();
	}

	public function edit_customer( $order_id, $data ) {
		return ( new Edit_Customer( array('orderId' => $order_id, 'data' => $data ) ) )->request();
	}
}
