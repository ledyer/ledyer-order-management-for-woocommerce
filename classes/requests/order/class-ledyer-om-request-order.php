<?php
/**
 * Abstract Request_Order
 *
 * @package LedyerOm\Requests\Order
 */
namespace LedyerOm\Requests\Order;

use LedyerOm\Requests\Request;

defined( 'ABSPATH' ) || exit();

/**
 * Class Request_Order
 *
 * @package LedyerOm\Requests\Order
 */
abstract class Request_Order extends Request {
	/*
	 * Set request url for all Request_Order child classes
	 */
	protected function set_request_url(): void {

		$this->request_url = 'https://api.live.ledyer.com/';
		$environment = ledyerOm()->parentSettings->get_test_environment();

		if ( parent::is_test() ) {

			switch ($environment) {
				case 'local':
					$this->request_url = 'http://host.docker.internal:8000/';
					break;
				case 'development':
				case 'local-fe':
					$this->request_url = 'https://api.dev.ledyer.com/';
					break;
				default:
					$this->request_url = 'https://api.sandbox.ledyer.com/';
					break;
			}
		}
		$this->set_url();
	}
	/*
	 * Set entrypoint in all Request_Order child classes
	 */
	abstract protected function set_url(): void;
}
