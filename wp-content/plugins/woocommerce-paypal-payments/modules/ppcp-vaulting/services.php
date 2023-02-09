<?php
/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Vaulting\Assets\MyAccountPaymentsAssets;
use WooCommerce\PayPalCommerce\Vaulting\Endpoint\DeletePaymentTokenEndpoint;

return array(
	'vaulting.module-url'                 => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules/ppcp-vaulting/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'vaulting.assets.myaccount-payments'  => function( ContainerInterface $container ) : MyAccountPaymentsAssets {
		return new MyAccountPaymentsAssets(
			$container->get( 'vaulting.module-url' ),
			$container->get( 'ppcp.asset-version' )
		);
	},
	'vaulting.payment-tokens-renderer'    => static function (): PaymentTokensRenderer {
		return new PaymentTokensRenderer();
	},
	'vaulting.repository.payment-token'   => static function ( ContainerInterface $container ): PaymentTokenRepository {
		$factory  = $container->get( 'api.factory.payment-token' );
		$endpoint = $container->get( 'api.endpoint.payment-token' );
		return new PaymentTokenRepository( $factory, $endpoint );
	},
	'vaulting.endpoint.delete'            => function( ContainerInterface $container ) : DeletePaymentTokenEndpoint {
		return new DeletePaymentTokenEndpoint(
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'button.request-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'vaulting.payment-token-checker'      => function( ContainerInterface $container ) : PaymentTokenChecker {
		return new PaymentTokenChecker(
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'api.repository.order' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.processor.authorized-payments' ),
			$container->get( 'api.endpoint.payments' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'vaulting.customer-approval-listener' => function( ContainerInterface $container ) : CustomerApprovalListener {
		return new CustomerApprovalListener(
			$container->get( 'api.endpoint.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'vaulting.credit-card-handler'        => function( ContainerInterface $container ): VaultedCreditCardHandler {
		return new VaultedCreditCardHandler(
			$container->get( 'subscription.helper' ),
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.payer' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'api.endpoint.order' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.processor.authorized-payments' ),
			$container->get( 'wcgateway.settings' )
		);
	},
);
