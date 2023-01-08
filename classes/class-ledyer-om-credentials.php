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
	 * Gets Ledyer API credentials (merchant ID and shared secret) from user session.
	 *
	 * @return bool|array $credentials
	 */
	public function get_credentials_from_session() {

		$test_string   = 'yes' === self::$settings['testmode'] ? 'test_' : '';
		$merchant_id   = self::$settings[ $test_string . 'merchant_id' ];
		$shared_secret = self::$settings[ $test_string . 'shared_secret' ];

		// Merchant id and/or shared secret not found for matching country.
		if ( '' === $merchant_id || '' === $shared_secret ) {
			return false;
		}

		$credentials = array(
			'merchant_id'   => self::$settings[ $test_string . 'merchant_id' ],
			'shared_secret' => htmlspecialchars_decode( self::$settings[ $test_string . 'shared_secret' ] ),
			'store_id'      => self::$settings[ $test_string . 'store_id' ]
		);

		return apply_filters( 'lco_wc_credentials_from_session', $credentials, self::$settings['testmode'] );
	}
}
