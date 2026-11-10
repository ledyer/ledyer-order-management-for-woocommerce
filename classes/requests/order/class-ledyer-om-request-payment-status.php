<?php
/**
 * Order Payment Status Request
 *
 * @package LedyerOm\Requests\Order
 */
namespace LedyerOm\Requests\Order;

use LedyerOm\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Payment_Status
 *
 * @package LedyerOm\Requests\Order
 */
class Payment_Status extends Request_Order {
	/*
	 * Request method
	 */
	protected $method = 'GET';

	/**
	 * The log title to use for the debug log.
	 *
	 * @var string
	 */
	protected $log_title = 'Get payment status';
	/*
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$get_payment_status_url = sprintf( 'v1/orders/%s/paymentstatus', $this->arguments['orderId'] );
		$this->url = $get_payment_status_url;

		parent::get_request_url();
	}
}
