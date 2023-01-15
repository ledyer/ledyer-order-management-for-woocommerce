<?php
/**
 * API Class file.
 *
 * @package LedyerOm
 */
namespace LedyerOm;

\defined( 'ABSPATH' ) || die();

use LedyerOm\Requests\Order\Get_Order;
use LedyerOm\Requests\Order\Capture_Order;

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
	public function get_order( $order_id ) {
    return ( new Get_Order( array( 'orderId' => $order_id ) ) )->request();
	}
	
	public function capture_order( $order_id ) {
    return ( new Capture_Order( array( 'orderId' => $order_id ) ) )->request();
	}
}
