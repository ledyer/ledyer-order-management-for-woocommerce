<?php
/**
 * Get Order Request
 *
 * @package LedyerOm\Requests\Order
 */
namespace LedyerOm\Requests\Order;

use LedyerOm\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Get_Order
 *
 * @package LedyerOm\Requests\Order
 */
class Get_Order extends Request_Order {
	/*
	 * Request method
	 */
	protected $method = 'GET';
	/*
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$this->url = sprintf( 'v1/orders/%s', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
