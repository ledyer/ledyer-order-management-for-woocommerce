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
		\add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
		\add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		// TODO? Add hook to listen to notifications. Look in Ledyer checkout plugin for this snippet


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

		$this->credentials   = Credentials::instance();
		$this->parentSettings   = ParentSettings::instance();
		$this->api           = new API();

		add_filter( 'plugin_action_links_' . plugin_basename( LOM_WC_MAIN_FILE ), array(
			$this,
			'plugin_action_links'
		));

		// Dummy request just as a POC of working api-calls
		$order = $this->api->get_order('or_2Jzwiy0aAXIvxRkFQQbQ11stizb');
		$asdf = 'asdf';
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
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-credentials.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-parent-settings.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-logger.php';
		
		include_once LOM_WC_PLUGIN_PATH . '/classes/class-ledyer-om-api.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/class-ledyer-om-request.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-order.php';
		include_once LOM_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-om-request-get-order.php';
	}
}
