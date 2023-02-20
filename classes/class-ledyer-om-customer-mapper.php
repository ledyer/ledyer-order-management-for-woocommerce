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
	 * @var bool|WC_Order|WC_Order_Refund
	 */
	public $order;

	/**
	 * Customer mapper constructor.
	 *
	 * @param int	$order WooCommerce order
	 */
	public function __construct( $order ) {
		$this->order = $order;
	}

	public function woo_to_ledyer_customer() {
		return array(
			'billingAddress'			=> $this->process_billing(),
			'shippingAddress'			=> $this->process_shipping(),
		);
	}

	private function process_billing() {
		$attentionName = get_post_meta($this->order->get_id(), '_billing_attention_name', true);
		$careOf = get_post_meta($this->order->get_id(), '_billing_care_of', true);
		return array(
			'companyName'		=> $this->order->get_billing_company(),
			'streetAddress'		=> $this->order->get_billing_address_1(),
			'postalCode'		=> $this->order->get_billing_postcode(),
			'city' 				=> $this->order->get_billing_city(),
			'country'			=> $this->order->get_billing_country(),
			'attentionName'		=> !empty( $attention_name ) ? $attentionName : "",
			'careOf'			=> !empty( $careOf ) ? $careOf : "",
		);
	}

	private function process_shipping() {
		return array(
			'companyName'	=> $this->order->get_shipping_company(),
			'streetAddress'	=> $this->order->get_shipping_address_1(),
			'postalCode'	=> $this->order->get_shipping_postcode(),
			'city'			=> $this->order->get_shipping_city(),
			'contact' => $this->process_shipping_contact(),
		);
	}

	private function process_shipping_contact() {
		$shipping_email = get_post_meta($this->order->get_id(), '_shipping_email', true);
		return array(
			'firstName'	=> $this->order->get_shipping_first_name(),
			'lastName'	=> $this->order->get_shipping_last_name(),
			'email'		=> !empty( $shipping_email ) ? $shipping_email : "",
			'phone'		=> $this->order->get_shipping_phone(),
		);
	}

}
