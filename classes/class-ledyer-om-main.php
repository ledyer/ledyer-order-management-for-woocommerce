<?php
/**
 * Class file for Ledyer_Order_Management_For_WooCommerce class.
 *
 * @package LedyerOm
 * @since 1.0.0
 */

namespace LedyerOm;

// use Ledyer\Admin\Meta_Box;

\defined( 'ABSPATH' ) || die();

/**
 * Ledyer_Order_Management_For_WooCommerce class.
 *
 * Init class
 */
class Ledyer_Order_Management_For_WooCommerce {
	use Singleton;

	const VERSION = '1.0.0';
	const SLUG = 'ledyer-order-management-for-woocommerce';
	const SETTINGS = 'ledyer_order_management_for_woocommerce_settings';

	/**
	 * Summary of actions - called from class-ledyer-om-singleton.php
	 * @return void
	 */
	public function actions() {
		\add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
		\add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		// add_action( 'rest_api_init', function () {
		// 	register_rest_route( 'ledyer/v1', '/notifications/', array(
		// 		'methods'             => 'POST',
		// 		'callback'            => [ $this, 'handle_notification' ],
		// 		'permission_callback' => '__return_true'
		// 	) );
		// } );

		add_action(
			'woocommerce_checkout_fields',
			array(
				$this,
				'modify_checkout_fields',
			),
			20,
			1,
		);

		// add_action( 'schedule_process_notification', array( $this, 'process_notification' ), 10, 1 );
	}

	/**
	 * Adds plugin action link to Krokedil documentation for KOM.
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
	 * Return the proper link for the settings page of KOM.
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

		// AJAX::init();
		// Confirmation::instance();
		// Set class variables.
		// $this->credentials   = Credentials::instance();
		// $this->merchant_urls = new Merchant_URLs();
		// $this->api           = new API();

		// load_plugin_textdomain( 'ledyer-checkout-for-woocommerce', false, LOM_WC_PLUGIN_NAME . '/languages' );

		add_filter( 'plugin_action_links_' . plugin_basename( LOM_WC_MAIN_FILE ), array(
			$this,
			'plugin_action_links'
		));
	}

	/**
	 * Init meta box on admin hook.
	 */
	public function on_admin_init() {
		// new Meta_Box();
	}

	public function include_files() {
		include_once LOM_WC_PLUGIN_PATH . '/includes/lom-functions.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-settings.php';
	}
}
