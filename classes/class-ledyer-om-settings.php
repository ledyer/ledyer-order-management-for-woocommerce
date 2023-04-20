<?php
/**
 * Class for the LOM settings.
 *
 * @package LedyerOm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to add settings to the Ledyer Add-ons page.
 */
class Ledyer_Order_Management_For_WooCommerce_Settings {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		// WP Settings API.
		add_action( 'admin_menu', array( $this, 'add_menu' ), 101 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// ledyer add-on.
		add_filter( 'ledyer_addons_settings_pages', array( $this, 'register_settings_page' ), 1 );
		add_action( 'ledyer_addons_settings_tab', array( $this, 'redirect_to_settings_page' ), 999999 );
	}

	/**
	 * Redirect to the settings page for LOM
	 *
	 * @return void
	 */
	public function redirect_to_settings_page() {
		global $wp;
		$query_args = array(
			'page' => 'lom-settings',
		);
		$url        = add_query_arg( $query_args, $wp->request );
		header( 'Location: ' . $url );
		wp_die();
	}

	/**
	 * Adds the submenue to the checkout addons page.
	 *
	 * @return void
	 */
	public function add_menu() {
		$submenu = add_submenu_page(
			'checkout-addons',
			__(
				'Ledyer Order Management',
				'ledyer-order-management-for-woocommerce'
			),
			__(
				'Ledyer Order Management',
				'ledyer-order-management-for-woocommerce'
			),
			'manage_woocommerce',
			'lom-settings',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Generates the HTML for the page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$this->add_page_tabs();
		$this->get_settings_links();
		?>
		<form action="options.php" method="post">
			<?php settings_fields( 'lom-settings' ); ?>
			<?php do_settings_sections( 'lom-settings' ); ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Registers settings for WordPress.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting( 'lom-settings', 'lom_settings' );

		add_settings_section(
			'lom_settings_section',
			'Ledyer order management',
			array( $this, 'lom_settings_section_callback' ),
			'lom-settings'
		);

		add_settings_field(
			'lom_auto_capture',
			'On order completion',
			array( $this, 'field_auto_capture_render' ),
			'lom-settings',
			'lom_settings_section'
		);
		add_settings_field(
			'lom_auto_cancel',
			'On order cancel',
			array( $this, 'field_auto_cancel_render' ),
			'lom-settings',
			'lom_settings_section'
		);
		add_settings_field(
			'lom_auto_update',
			'On order update',
			array( $this, 'field_auto_update_render' ),
			'lom-settings',
			'lom_settings_section'
		);		
		add_settings_field(
			'lom_status_error_ledyer_woo',
			'On fail set status',
			array( $this, 'field_status_error_ledyer_woo_render' ),
			'lom-settings',
			'lom_settings_section'
		);
	}

	/**
	 * Empty function for now.
	 *
	 * @return void
	 */
	public function lom_settings_section_callback() {
		// Empty for now.
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_auto_capture_render() {
		$options = get_option( 'lom_settings' );
		$val     = ( isset( $options['lom_auto_capture'] ) ) ? $options['lom_auto_capture'] : 'yes';
		?>
		<input type="hidden" name="lom_settings[lom_auto_capture]" value="no" />
		<label for="lom_settings[lom_auto_capture]" >
			<input type='checkbox' name='lom_settings[lom_auto_capture]' value='yes' <?php checked( $val, 'yes' ); ?>>
			<?php esc_html_e( 'Capture the Ledyer order automatically when WooCommerce order is marked complete.', 'ledyer-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_auto_cancel_render() {
		$options = get_option( 'lom_settings' );
		$val     = ( isset( $options['lom_auto_cancel'] ) ) ? $options['lom_auto_cancel'] : 'yes';
		?>
		<input type="hidden" name="lom_settings[lom_auto_cancel]" value="no" />
		<label for="lom_settings[lom_auto_cancel]" >
		<input type='checkbox' name='lom_settings[lom_auto_cancel]' value='yes' <?php checked( $val, 'yes' ); ?>>
		<?php esc_html_e( 'Cancel the Ledyer order automatically when WooCommerce order is marked canceled.', 'ledyer-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	/**
	 * HTML For the input field.
	 *
	 * @return void
	 */
	public function field_auto_update_render() {
		$options = get_option( 'lom_settings' );
		$val     = ( isset( $options['lom_auto_update'] ) ) ? $options['lom_auto_update'] : 'yes';
		?>
		<input type="hidden" name="lom_settings[lom_auto_update]" value="no" />
		<label for="lom_settings[lom_auto_update]" >
		<input type='checkbox' name='lom_settings[lom_auto_update]' value='yes' <?php checked( $val, 'yes' ); ?>>
		<?php esc_html_e( 'Update Ledyer order automatically when WooCommerce order is updated.', 'ledyer-order-management-for-woocommerce' ); ?>
		</label>
		<?php
	}

	function field_status_error_ledyer_woo_render() {
		$options = get_option( 'lom_settings' );
		$current_value = ( isset( $options['lom_status_mapping_ledyer_error'] ) ) ? $options['lom_status_mapping_ledyer_error'] : 'wc-on-hold';
		$order_statuses = wc_get_order_statuses();
		$order_statuses['none'] = '-';
		?>
		<select name="lom_settings[lom_status_mapping_ledyer_error]">
		<?php
		foreach ($order_statuses as $value => $label) :
			$selected_attr = selected($current_value, $value, false);
			?>
			<option value="<?php echo esc_attr($value); ?>" <?php echo $selected_attr; ?>><?php echo esc_html($label); ?></option>
			<?php
		endforeach;
		?>
		</select>
		<?php
	}
	
	/**
	 * Adds order management to the settings pages.
	 *
	 * @param array $pages List of the different pages.
	 * @return array
	 */
	public function register_settings_page( $pages ) {
		$pages['lom-settings'] = 'Ledyer Order Management';
		return $pages;
	}


	/**
	 * Adds tabs to the Addons page.
	 *
	 * @param string $current Wich tab is to be selected.
	 * @return void
	 */
	public function add_page_tabs( $current = 'settings' ) {
		$tabs  = array(
			'settings' => __( 'Settings', 'ledyer-checkout-for-woocommerce' ),
		);
		$pages = array(
			'settings' => 'lom-settings',
		);
		?>
			<h2 class="nav-tab-wrapper">
			<?php
			foreach ( $tabs as $tab => $name ) {
				$class = ( $tab === $current ) ? 'nav-tab-active' : '';
				?>
					<a class="nav-tab <?php echo esc_html( $class ); ?>" href="?page=<?php echo esc_html( $pages[ $tab ] ); ?>"><?php echo esc_html( $name ); ?></a>
				<?php
			}
			?>
			</h2>
		<?php
	}

	/**
	 * Gets the links to the different settings pages.
	 *
	 * @return void
	 */
	public function get_settings_links() {
		global $wp;
		$pages = apply_filters( 'ledyer_addons_settings_pages', array() );
		$i     = count( $pages );
		?>
		<p>
		<?php
		foreach ( $pages as $slug => $title ) {
			$query_args = array(
				'page' => $slug,
			);
			$i - 1;
			?>
				<a href="<?php echo esc_html( add_query_arg( $query_args, $wp->request ) ); ?>"><?php echo esc_html( $title ); ?></a>
			<?php
		}
		?>
		</p>
		<?php
	}
} new Ledyer_Order_Management_For_WooCommerce_Settings();
