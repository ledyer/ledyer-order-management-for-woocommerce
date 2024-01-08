<?php
/**
 * Cancel Order Request
 *
 * @package LedyerOm\Requests\Order
 */
namespace LedyerOm\Requests\Order;

use LedyerOm\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Cancel_Order
 *
 * @package LedyerOm\Requests\Order
 */
class Cancel_Order extends Request_Order {
	/*
	 * Request method
	 */
	protected $method = 'DELETE';
	/*
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$cancel_url = sprintf('v1/orders/%s', $this->arguments['orderId']);
		$this->url = $cancel_url;

		parent::get_request_url();
	}
}
