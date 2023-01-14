<?php
/**
 * Class file for Ledyer_Order_Management_For_WooCommerce class.
 *
 * @package LedyerOm
 * @since 1.0.0
 */

namespace LedyerOm;


\defined( 'ABSPATH' ) || die();

class Ledyer_Order_Management_For_WooCommerce {
	use Singleton;

	public $credentials;
	public $parentSettings;
	public $api;

	const VERSION = '1.0.0';
	const SLUG = 'ledyer-order-management-for-woocommerce';
	const SETTINGS = 'ledyer_order_management_for_woocommerce_settings';

	/**
	 * Summary of actions - called from class-ledyer-om-singleton.php
	 * @return void
	 */
	public function actions() {
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );


		// Capture an order.
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_ledyer_order' ) );

		// Update an order TODO?
		// add_action( 'woocommerce_saved_order_items', array( $this, 'update_ledyer_order_items' ), 10, 2 );
	}

	/**
	 * Adds plugin action link to Ledyer documentation for LOM.
	 *
   * @param array $links Plugin action link before filtering.
	 *
	 * @return array Filtered links.
	 */
	public function plugin_action_links( $links ) {
		$setting_link = $this->get_setting_link();
		$plugin_links = array(
			'<a href="' . $setting_link . '">' . __( 'Settings', 'ledyer-order-management-for-woocommerce' ) . '</a>',
			'<a target="_blank" href="https://ledyer.com">Docs</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Return the proper link for the settings page of LOM.
	 *
	 * @return string The full settings page URL.
	 */
	protected function get_setting_link() {
		return admin_url( 'admin.php?page=lom-settings' );
	}

	public function on_plugins_loaded() {
		if ( ! defined( 'WC_VERSION' ) ) {
			return;
		}

		$this->include_files();
		$this->set_settings();

		$this->credentials      = Credentials::instance();
		$this->parentSettings   = ParentSettings::instance();
		$this->api              = new API();

		add_filter( 'plugin_action_links_' . plugin_basename( LOM_WC_MAIN_FILE ), array($this, 'plugin_action_links'));

		// Dummy request just as a POC of working api-calls
		$order = $this->api->get_order('or_2Jzwiy0aAXIvxRkFQQbQ11stizb');
	}


	/**
		 * Captures a Ledyer order.
		 *
		 * @param int  $order_id Order ID.
		 * @param bool $action If this was triggered by an action.
		 */
	public function capture_ledyer_order($order_id, $action = false)
	{
		$options = get_option( 'lom_settings' );
		// if the capture on complete is not enabled in lom-settings
		if ( 'no' === $options['lom_auto_capture']) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Check if the order has been paid. get_date_paid() gets a built in woocommerce property
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		// Only do ledyer capture on orders that was placed with Ledyer Checkout or Ledyer payments
		// Not going to do this for non-KP and non-LCO orders.
		$is_ledyer_order = order_placed_with_ledyer($order->get_payment_method());
		if (! $is_ledyer_order) {
			return;
		}

		// Do nothing if Ledyer order was already captured.
		if ( get_post_meta( $order_id, '_wc_ledyer_capture_id', true ) ) {
			$order->add_order_note( 'Ledyer order has already been captured.' );
			return;
		}

		$ledyer_order_id = get_post_meta($order_id, '_wc_ledyer_order_id', true);

		// Do nothing if we don't have Ledyer order ID.
		if ( ! $ledyer_order_id && ! get_post_meta( $order_id, '_transaction_id', true ) ) {
			$order->add_order_note( 'Ledyer order ID is missing, Ledyer order could not be captured at this time.' );
			$order->set_status( 'on-hold' );
			$order->save();
			return;
		}

		// Fetch the ledyer order
		$ledyer_order = $this->api->get_order($ledyer_order_id);

		if ( is_wp_error( $ledyer_order ) ) {
			$order->add_order_note( 'Ledyer order could not be captured due to an error. Ordet set to On hold' );
			$order->set_status( 'on-hold' );
			$order->save();
			return;
		}

		// Check if Ledyer order status is in fullyCaptured
		if (in_array('fullyCaptured', $ledyer_order['status'])) {
			$first_captured = get_first_captured($ledyer_order); 
			$captured_at = $first_captured['createdAt'];
			$formatted_capture_at = date("Y-m-d H:i:s", strtotime($captured_at));
			$capture_id = $first_captured['ledgerId'];

			$order->add_order_note( 'Ledyer order has already been captured on ' . $formatted_capture_at );
			update_post_meta( $order_id, '_wc_ledyer_capture_id', $capture_id );
			return;
		}

		// Check if Ledyer order has already been canceled.
		if (in_array('cancelled', $ledyer_order['status'])) {
			$order->add_order_note( 'Ledyer order failed to capture, the order has already been cancelled' );
			return;
		}


		$capture_ledyer_order_response = $this->api->capture_order($ledyer_order_id);
		
		if (!is_wp_error($capture_ledyer_order_response)) {
			$first_captured = get_first_captured($capture_ledyer_order_response);
			$capture_id = $first_captured['ledgerId'];

			$order->add_order_note( 'Ledyer order captured. Capture amount: ' . $order->get_formatted_order_total( '', false ) . '. Capture ID: ' . $capture_id );
			update_post_meta($order_id, '_wc_ledyer_capture_id', $capture_id, true);
			return;
		}

		/*
		 * Capture failed error handling
		 * 
		 * The suggested approach by Ledyer is to try again after some time.
		 * If that still fails, the merchant should inform the customer,
		 * and ask them to either "create a new subscription or add funds to their payment method if they wish to continue."
		 */

		$httpErrorCode = $capture_ledyer_order_response->get_error_data()['code'];

		if ( isset( $httpErrorCode ) && 403 === $httpErrorCode ) {
			$order = wc_get_order( $order_id );
			$order->add_order_note( __( 'Ledyer could not charge the customer. Please try again later. If that still fails, the customer may have to create a new subscription or add funds to their payment method if they wish to continue.', 'ledyer-order-management-for-woocommerce' ) );
		} else {
			$error_message = $capture_ledyer_order_response->get_error_message();

			// if ( ! is_array( $error_message ) && false !== strpos( $error_message, 'Captured amount is higher than the remaining authorized amount.' ) ) {
			// 	$error_message = str_replace( '. Capture not possible.', sprintf( ': %s %s.', $ledyer_order->remaining_authorized_amount / 100, $ledyer_order->purchase_currency ), $error_message );
			// }

			// translators: %s: Error message from ledyer.
			$order->add_order_note( sprintf( __( 'Could not capture Ledyer order. %s', 'ledyer-order-management-for-woocommerce' ), $error_message ) );

		}

		$order->set_status( 'on-hold' );
		$order->save();
	}

	public function update_ledyer_order_items($order_id, $items, $action = false)
	{
		$asdf = 'asdf';
	}


	public function include_files() {
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-functions.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-settings.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-credentials.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-parent-settings.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-logger.php';
		
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-api.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/class-ledyer-om-request.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-get-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-capture-order.php';
	}
}
