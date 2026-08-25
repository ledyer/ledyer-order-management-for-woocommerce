# ledyer-order-management-for-woocommerce
Ledyer order management for Woocommerce

Spin up docker images

1) update deps with `composer update'
2) cd to ledyer-order-management-for-woocommerce and add a .env
3) add `PHP_VERSION=8` or `PHP_VERSION=7` and save the file
4) composer install (brew install composer)
5) docker-compose up --build -d
6) Browse http://localhost:8181 / http://localhost:8181/wp-admin

## Run order management with Ledyer checkout
If you want to run the ledyer-order-management-for-woocommerce together with ledyer-checkout-for-woocommerce you need to either install it from the market place or include a local copy. To run it with a local copy, you need to have [Ledyer checkout](https://github.com/ledyer/ledyer-checkout-for-woocommerce) checked out.

Then edit docker-compose and add `ledyer-checkout-for-woocommerce`

```
wordpress-dev:
  ...configuration
  volumes:
    - "./:/var/www/html/wp-content/plugins/ledyer-order-management-for-woocommerce"
    - "../ledyer-checkout-for-woocommerce/:/var/www/html/wp-content/plugins/ledyer-checkout-for-woocommerce"
    - wpdata:/var/www/html
    
wordpress-cli:
  ...configuration
  volumes:
    - "./:/var/www/html/wp-content/plugins/ledyer-order-management-for-woocommerce"
    - "../ledyer-checkout-for-woocommerce/:/var/www/html/wp-content/plugins/ledyer-checkout-for-woocommerce"
    - wpdata:/var/www/html
```

## Debugging

In VS code, install xdebug and optionally a PHP intellisense extension. Then just click Run and debug

## Capture API for WordPress integrations

Trusted server-side plugins can request a full capture using the WooCommerce order ID:

```php
$result = ledyer_om_capture_order( $order_id );

if ( is_wp_error( $result ) ) {
	// Handle $result->get_error_message().
} else {
	// $result['result'] is "captured" or "already_captured".
}
```

The function bypasses the automatic capture setting and local WooCommerce paid/ready state. It captures only when Ledyer reports `paymentConfirmed`; other statuses return `WP_Error`. Call it after `plugins_loaded` and guard with `function_exists( 'ledyer_om_capture_order' )`.
