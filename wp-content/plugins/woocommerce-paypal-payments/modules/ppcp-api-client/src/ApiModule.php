<?php
/**
 * The API module.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\ServiceProvider;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;
use WooCommerce\PayPalCommerce\Vendor\Interop\Container\ServiceProviderInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class ApiModule
 */
class ApiModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 */
	public function setup(): ServiceProviderInterface {
		return new ServiceProvider(
			require __DIR__ . '/../services.php',
			require __DIR__ . '/../extensions.php'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function run( ContainerInterface $c ): void {
		add_action(
			'woocommerce_after_calculate_totals',
			function ( \WC_Cart $cart ) {
				$fees = $cart->fees_api()->get_fees();
				WC()->session->set( 'ppcp_fees', $fees );
			}
		);
	}

	/**
	 * Returns the key for the module.
	 *
	 * @return string|void
	 */
	public function getKey() {
	}
}
