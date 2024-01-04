<?php
/**
 * Edit Order Request
 *
 * @package LedyerOm\Requests\Order
 */
namespace LedyerOm\Requests\Order;

use LedyerOm\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Edit_Order
 *
 * @package LedyerOm\Requests\Order
 */
class Edit_Order extends Request_Order {
	/*
	 * Request method
	 */
	protected $method = 'POST';
	/*
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$edit_url = sprintf('v1/orders/%s', $this->arguments['orderId']);
		$this->url = $edit_url;

		parent::get_request_url();
	}
}
