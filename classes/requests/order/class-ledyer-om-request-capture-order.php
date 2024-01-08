<?php
/**
 * Capture Order Request
 *
 * @package LedyerOm\Requests\Order
 */
namespace LedyerOm\Requests\Order;

use LedyerOm\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Capture_Order
 *
 * @package LedyerOm\Requests\Order
 */
class Capture_Order extends Request_Order {
	/*
	 * Request method
	 */
	protected $method = 'POST';
	/*
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$capture_url = sprintf('v1/orders/%s', $this->arguments['orderId']) . '/capture';
		$this->url = $capture_url;

		parent::get_request_url();
	}
}
