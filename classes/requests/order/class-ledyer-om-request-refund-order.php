<?php
/**
 * Refund Order Request
 *
 * @package LedyerOm\Requests\Order
 */
namespace LedyerOm\Requests\Order;

use LedyerOm\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Refund_Order
 *
 * @package LedyerOm\Requests\Order
 */
class Refund_Order extends Request_Order {
	/*
	 * Request method
	 */
	protected $method = 'POST';
	/*
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$refund_url = sprintf('v1/orders/%s', $this->arguments['orderId']) . sprintf('/refund/%s', $this->arguments['ledgerId']);
		$this->url = $refund_url;

		parent::get_request_url();
	}
}
