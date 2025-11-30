<?php
/**
 * Order mapper woo -> ledyer
 *
 * @package Ledyer
 */
namespace LedyerOm;

defined( 'ABSPATH' ) || exit();

class CustomerMapper {
	/**
	 * WooCommerce order.
	 *
	 * @var bool|\WC_Order|\WC_Order_Refund
	 */
	public $order;

	/**
	 * Customer mapper constructor.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function __construct( $order ) {
		$this->order = $order;
	}

	/**
	 * Map WooCommerce order customer data to Ledyer format.
	 *
	 * @return array
	 */
	public function woo_to_ledyer_customer() {
		return array(
			'billingAddress'  => $this->process_billing(),
			'shippingAddress' => $this->process_shipping(),
			'email'           => $this->order->get_billing_email(),
			'phone'           => $this->order->get_billing_phone(),
		);
	}

	/**
	 * Format the customer billing data for API consumption.
	 *
	 * @return array
	 */
	private function process_billing() {
		$attention_name = filter_input( INPUT_POST, '_billing_attention_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$care_of        = filter_input( INPUT_POST, '_billing_care_of', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		return array(
			'attentionName' => $attention_name ?? '',
			'careOf'        => $care_of ?? '',
			'city'          => $this->order->get_billing_city(),
			'companyName'   => $this->order->get_billing_company(),
			'contact'       => array(
				'email'     => $this->order->get_billing_email(),
				'firstName' => $this->order->get_billing_first_name(),
				'lastName'  => $this->order->get_billing_last_name(),
				'phone'     => $this->order->get_billing_phone(),
			),
			'country'       => $this->order->get_billing_country(),
			'postalCode'    => $this->order->get_billing_postcode(),
			'streetAddress' => $this->order->get_billing_address_1(),
		);
	}

	/**
	 * Format the customer shipping data for API consumption.
	 *
	 * @return array
	 */
	private function process_shipping() {
		$attention_name = filter_input( INPUT_POST, '_shipping_attention_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$care_of        = filter_input( INPUT_POST, '_shipping_care_of', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$shipping_email = filter_input( INPUT_POST, '_shipping_email', FILTER_SANITIZE_EMAIL );

		return array(
			'attentionName' => $attention_name ?? '',
			'careOf'        => $care_of ?? '',
			'city'          => $this->order->get_shipping_city(),
			'companyName'   => $this->order->get_shipping_company(),
			'contact'       => array(
				'email'     => $shipping_email ?? '',
				'firstName' => $this->order->get_shipping_first_name(),
				'lastName'  => $this->order->get_shipping_last_name(),
				'phone'     => $this->order->get_shipping_phone(),
			),
			'country'       => $this->order->get_shipping_country(),
			'postalCode'    => $this->order->get_shipping_postcode(),
			'streetAddress' => $this->order->get_shipping_address_1(),
		);
	}
}
