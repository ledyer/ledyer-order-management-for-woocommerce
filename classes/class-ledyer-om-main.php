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

	const VERSION  = '1.5.2';
	const SLUG     = 'ledyer-order-management-for-woocommerce';
	const SETTINGS = 'ledyer_order_management_for_woocommerce_settings';

	/**
	 * Summary of actions - called from class-ledyer-om-singleton.php
	 *
	 * @return void
	 */
	public function actions() {
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );

		// Add refunds support to Ledyer Payments and Ledyer Checkout gateways.
		add_action( 'wc_ledyer_payments_supports', array( $this, 'add_gateway_support' ) );
		add_action( 'lco_wc_supports', array( $this, 'add_gateway_support' ) );

		// Capture an order -> lom-capture.php
		add_action(
			'woocommerce_order_status_completed',
			function ( $order_id, $action = false ) {
				lom_capture_ledyer_order( $order_id, $this->api, $action );
			}
		);

		// Listen to refund from Ledyer Checkout for Woocommerce, then call lom_refund_ledyer_order -> lom-refund.php
		add_filter(
			'wc_ledyer_checkout_process_refund',
			function ( $result, $order_id, $amount, $reason ) {
				return lom_refund_ledyer_order( $order_id, $amount, $this->api );
			},
			10,
			4
		);

		// Listen to refund from Ledyer Payments for Woocommerce, then call lom_refund_ledyer_order -> lom-refund.php
		add_filter(
			'wc_ledyer_payments_process_refund',
			function ( $result, $order_id, $amount, $reason ) {
				return lom_refund_ledyer_order( $order_id, $amount, $this->api );
			},
			10,
			4
		);

		// Cancel an order -> lom-cancel.php
		add_action(
			'woocommerce_order_status_cancelled',
			function ( $order_id, $action = false ) {
				lom_cancel_ledyer_order( $order_id, $this->api, $action );
			}
		);

		// Sync order items and totals
		add_action(
			'woocommerce_saved_order_items',
			function ( $order_id, $action = false ) {
				lom_edit_ledyer_order( $order_id, $this->api, 'order', $action );
			}
		);

		// Sync customer details such as shipping and billing
		add_action(
			'woocommerce_process_shop_order_meta',
			function ( $order_id, $action = false ) {

				if ( ! is_admin() ) {
					return;
				}
				lom_edit_ledyer_order( $order_id, $this->api, 'customer', $action );
			},
			55,
			1 // Priority higher than 50, since that is the priority we use to save the fields after validation is done. We don't want to sync with Ledyer if local Woo validation of the fields fail.
		);
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

		require_once LOM_WC_PLUGIN_PATH . '/vendor/autoload.php';

		$this->include_files();
		$this->set_settings();

		$this->credentials    = Credentials::instance();
		$this->parentSettings = ParentSettings::instance();
		$this->api            = new API();

		add_filter( 'plugin_action_links_' . plugin_basename( LOM_WC_MAIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add refunds support to Ledyer Payments gateway.
	 *
	 * @param array $features Supported features.
	 *
	 * @return array $features Supported features.
	 */
	public function add_gateway_support( $features ) {
		$features[] = 'refunds';

		return $features;
	}

	public function include_files() {
		// includes
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-functions.php';
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-types.php';
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-capture.php';
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-refund.php';
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-cancel.php';
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-edit.php';

		// classes
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-settings.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-credentials.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-parent-settings.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-logger.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-order-mapper.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-customer-mapper.php';

		// api
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-api.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/class-ledyer-om-request.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-order.php';

		// api endpoints
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-payment-status.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-get-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-capture-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-refund-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-partial-refund-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-cancel-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-edit-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-edit-customer.php';
	}
}
