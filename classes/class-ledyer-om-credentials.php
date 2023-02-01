<?php
/**
 * File for Credentials class.
 *
 * @package LedyerOm
 */

namespace LedyerOm;

\defined( 'ABSPATH' ) || die();

/**
 * Credentials class.
 *
 * Gets correct credentials based on test/live mode.
 */
class Credentials {

	use Singleton;

	/**
	 * Credentials constructor.
	 */
	public function set_settings() {
		// First try and get credentials from Ledyer checkout
		self::$settings = get_option( 'woocommerce_lco_settings' );
		// If that is not found, try and get from Ledyer Payments (to be developed)
	}

	/**
	 * Gets Ledyer API credentials
	 *
	 * @return bool|array $credentials
	 */
	public function get_client_credentials() {
		$test_string   = 'yes' === self::$settings['testmode'] ? 'test_' : '';
		$client_id   = self::$settings[ $test_string . 'merchant_id' ];
		$client_secret = self::$settings[ $test_string . 'shared_secret' ];

		if ( '' === $client_id || '' === $client_secret ) {
			return false;
		}

		$credentials = array(
			'client_id'   => $client_id,
			'client_secret' => htmlspecialchars_decode( $client_secret ),
		);

		return $credentials;
	}
}
