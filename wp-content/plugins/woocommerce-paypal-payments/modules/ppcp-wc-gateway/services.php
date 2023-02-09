<?php
/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

// phpcs:disable WordPress.Security.NonceVerification.Recommended

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PayUponInvoiceOrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ApplicationContext;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesDisclaimers;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Onboarding\Render\OnboardingOptionsRenderer;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Admin\FeesRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Admin\OrderTablePaymentStatusColumn;
use WooCommerce\PayPalCommerce\WcGateway\Admin\PaymentStatusOrderDetail;
use WooCommerce\PayPalCommerce\WcGateway\Admin\RenderAuthorizeAction;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\CheckoutPayPalAddressPreset;
use WooCommerce\PayPalCommerce\WcGateway\Checkout\DisableGateways;
use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ReturnUrlEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\FraudNet;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\FraudNetSessionId;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\FraudNetSourceWebsiteId;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PaymentSourceFactory;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoice;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\TransactionUrlProvider;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CheckoutHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceHelper;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Notice\AuthorizeOrderActionNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\ConnectAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Notice\GatewayWithoutPayPalAdminNotice;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Settings\HeaderRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SectionsRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener;
use WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsRenderer;

return array(
	'wcgateway.paypal-gateway'                             => static function ( ContainerInterface $container ): PayPalGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$funding_source_renderer   = $container->get( 'wcgateway.funding-source.renderer' );
		$settings            = $container->get( 'wcgateway.settings' );
		$session_handler     = $container->get( 'session.handler' );
		$refund_processor    = $container->get( 'wcgateway.processor.refunds' );
		$state               = $container->get( 'onboarding.state' );
		$transaction_url_provider = $container->get( 'wcgateway.transaction-url-provider' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$page_id             = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		$payment_token_repository = $container->get( 'vaulting.repository.payment-token' );
		$environment         = $container->get( 'onboarding.environment' );
		$logger              = $container->get( 'woocommerce.logger.woocommerce' );
		$api_shop_country = $container->get( 'api.shop.country' );
		return new PayPalGateway(
			$settings_renderer,
			$funding_source_renderer,
			$order_processor,
			$settings,
			$session_handler,
			$refund_processor,
			$state,
			$transaction_url_provider,
			$subscription_helper,
			$page_id,
			$environment,
			$payment_token_repository,
			$logger,
			$api_shop_country
		);
	},
	'wcgateway.credit-card-gateway'                        => static function ( ContainerInterface $container ): CreditCardGateway {
		$order_processor     = $container->get( 'wcgateway.order-processor' );
		$settings_renderer   = $container->get( 'wcgateway.settings.render' );
		$settings            = $container->get( 'wcgateway.settings' );
		$module_url          = $container->get( 'wcgateway.url' );
		$session_handler     = $container->get( 'session.handler' );
		$refund_processor    = $container->get( 'wcgateway.processor.refunds' );
		$state               = $container->get( 'onboarding.state' );
		$transaction_url_provider = $container->get( 'wcgateway.transaction-url-provider' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$payments_endpoint = $container->get( 'api.endpoint.payments' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$vaulted_credit_card_handler = $container->get( 'vaulting.credit-card-handler' );
		return new CreditCardGateway(
			$settings_renderer,
			$order_processor,
			$settings,
			$module_url,
			$session_handler,
			$refund_processor,
			$state,
			$transaction_url_provider,
			$subscription_helper,
			$logger,
			$payments_endpoint,
			$vaulted_credit_card_handler
		);
	},
	'wcgateway.card-button-gateway'                        => static function ( ContainerInterface $container ): CardButtonGateway {
		return new CardButtonGateway(
			$container->get( 'wcgateway.settings.render' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'subscription.helper' ),
			$container->get( 'wcgateway.settings.allow_card_button_gateway.default' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wcgateway.disabler'                                   => static function ( ContainerInterface $container ): DisableGateways {
		$session_handler = $container->get( 'session.handler' );
		$settings       = $container->get( 'wcgateway.settings' );
		return new DisableGateways( $session_handler, $settings );
	},

	'wcgateway.is-wc-payments-page'                        => static function ( ContainerInterface $container ): bool {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		return 'wc-settings' === $page && 'checkout' === $tab;
	},
	'wcgateway.is-wc-gateways-list-page'                   => static function ( ContainerInterface $container ): bool {
		return $container->get( 'wcgateway.is-wc-payments-page' ) && ! isset( $_GET['section'] );
	},

	'wcgateway.is-ppcp-settings-page'                      => static function ( ContainerInterface $container ): bool {
		if ( ! $container->get( 'wcgateway.is-wc-payments-page' ) ) {
			return false;
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		return in_array(
			$section,
			array(
				Settings::CONNECTION_TAB_ID,
				PayPalGateway::ID,
				CreditCardGateway::ID,
				PayUponInvoiceGateway::ID,
				CardButtonGateway::ID,
				OXXOGateway::ID,
				Settings::PAY_LATER_TAB_ID,
			),
			true
		);
	},

	'wcgateway.current-ppcp-settings-page-id'              => static function ( ContainerInterface $container ): string {
		if ( ! $container->get( 'wcgateway.is-ppcp-settings-page' ) ) {
			return '';
		}

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : '';
		$ppcp_tab = isset( $_GET[ SectionsRenderer::KEY ] ) ? sanitize_text_field( wp_unslash( $_GET[ SectionsRenderer::KEY ] ) ) : '';

		$state = $container->get( 'onboarding.state' );
		assert( $state instanceof State );

		if ( ! $ppcp_tab && PayPalGateway::ID === $section && $state->current_state() !== State::STATE_ONBOARDED ) {
			return Settings::CONNECTION_TAB_ID;
		}

		return $ppcp_tab ? $ppcp_tab : $section;
	},

	'wcgateway.settings'                                   => static function ( ContainerInterface $container ): Settings {
		$messaging_locations = $container->get( 'wcgateway.settings.pay-later.messaging-locations' );
		return new Settings( array_keys( $messaging_locations ) );
	},
	'wcgateway.notice.connect'                             => static function ( ContainerInterface $container ): ConnectAdminNotice {
		$state    = $container->get( 'onboarding.state' );
		$settings = $container->get( 'wcgateway.settings' );
		return new ConnectAdminNotice( $state, $settings );
	},
	'wcgateway.notice.dcc-without-paypal'                  => static function ( ContainerInterface $container ): GatewayWithoutPayPalAdminNotice {
		return new GatewayWithoutPayPalAdminNotice(
			CreditCardGateway::ID,
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.is-wc-payments-page' ),
			$container->get( 'wcgateway.is-ppcp-settings-page' )
		);
	},
	'wcgateway.notice.card-button-without-paypal'          => static function ( ContainerInterface $container ): GatewayWithoutPayPalAdminNotice {
		return new GatewayWithoutPayPalAdminNotice(
			CardButtonGateway::ID,
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.is-wc-payments-page' ),
			$container->get( 'wcgateway.is-ppcp-settings-page' )
		);
	},
	'wcgateway.notice.authorize-order-action'              =>
		static function ( ContainerInterface $container ): AuthorizeOrderActionNotice {
			return new AuthorizeOrderActionNotice();
		},
	'wcgateway.settings.sections-renderer'                 => static function ( ContainerInterface $container ): SectionsRenderer {
		return new SectionsRenderer(
			$container->get( 'wcgateway.current-ppcp-settings-page-id' ),
			$container->get( 'wcgateway.settings.sections' ),
			$container->get( 'onboarding.state' )
		);
	},
	'wcgateway.settings.header-renderer'                   => static function ( ContainerInterface $container ): HeaderRenderer {
		return new HeaderRenderer(
			$container->get( 'wcgateway.current-ppcp-settings-page-id' ),
			$container->get( 'wcgateway.url' )
		);
	},
	'wcgateway.settings.sections'                          => static function ( ContainerInterface $container ): array {
		$sections = array(
			Settings::CONNECTION_TAB_ID => __( 'Connection', 'woocommerce-paypal-payments' ),
			PayPalGateway::ID           => __( 'Standard Payments', 'woocommerce-paypal-payments' ),
			Settings::PAY_LATER_TAB_ID  => __( 'Pay Later', 'woocommerce-paypal-payments' ),
			CreditCardGateway::ID       => __( 'Advanced Card Processing', 'woocommerce-paypal-payments' ),
			CardButtonGateway::ID       => __( 'Standard Card Button', 'woocommerce-paypal-payments' ),
			OXXOGateway::ID             => __( 'OXXO', 'woocommerce-paypal-payments' ),
			PayUponInvoiceGateway::ID   => __( 'Pay upon Invoice', 'woocommerce-paypal-payments' ),
		);

		// Remove for all not registered in WC gateways that cannot render anything in this case.
		$gateways = WC()->payment_gateways->payment_gateways();
		foreach ( array_diff(
			array_keys( $sections ),
			array( Settings::CONNECTION_TAB_ID, PayPalGateway::ID, CreditCardGateway::ID, Settings::PAY_LATER_TAB_ID )
		) as $id ) {
			if ( ! isset( $gateways[ $id ] ) ) {
				unset( $sections[ $id ] );
			}
		}

		$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
		assert( $dcc_product_status instanceof DCCProductStatus );
		$dcc_applies = $container->get( 'api.helpers.dccapplies' );
		assert( $dcc_applies instanceof DccApplies );
		if ( ! $dcc_product_status->dcc_is_active() || ! $dcc_applies->for_country_currency() ) {
			unset( $sections['ppcp-credit-card-gateway'] );
		}

		$messages_apply = $container->get( 'button.helper.messages-apply' );
		assert( $messages_apply instanceof MessagesApply );

		if ( ! $messages_apply->for_country() ) {
			unset( $sections[ Settings::PAY_LATER_TAB_ID ] );
		}

		$pui_product_status = $container->get( 'wcgateway.pay-upon-invoice-product-status' );
		assert( $pui_product_status instanceof PayUponInvoiceProductStatus );

		if ( ! $pui_product_status->pui_is_active() ) {
			unset( $sections[ PayUponInvoiceGateway::ID ] );
		}

		return $sections;
	},
	'wcgateway.settings.status'                            => static function ( ContainerInterface $container ): SettingsStatus {
		$settings      = $container->get( 'wcgateway.settings' );
		return new SettingsStatus( $settings );
	},
	'wcgateway.settings.render'                            => static function ( ContainerInterface $container ): SettingsRenderer {
		$settings      = $container->get( 'wcgateway.settings' );
		$state         = $container->get( 'onboarding.state' );
		$fields        = $container->get( 'wcgateway.settings.fields' );
		$dcc_applies    = $container->get( 'api.helpers.dccapplies' );
		$messages_apply = $container->get( 'button.helper.messages-apply' );
		$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
		$settings_status = $container->get( 'wcgateway.settings.status' );
		$page_id         = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		$api_shop_country = $container->get( 'api.shop.country' );
		return new SettingsRenderer(
			$settings,
			$state,
			$fields,
			$dcc_applies,
			$messages_apply,
			$dcc_product_status,
			$settings_status,
			$page_id,
			$api_shop_country
		);
	},
	'wcgateway.settings.listener'                          => static function ( ContainerInterface $container ): SettingsListener {
		$settings         = $container->get( 'wcgateway.settings' );
		$fields           = $container->get( 'wcgateway.settings.fields' );
		$webhook_registrar = $container->get( 'webhook.registrar' );
		$state            = $container->get( 'onboarding.state' );
		$cache = new Cache( 'ppcp-paypal-bearer' );
		$bearer = $container->get( 'api.bearer' );
		$page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );
		$signup_link_cache = $container->get( 'onboarding.signup-link-cache' );
		$signup_link_ids = $container->get( 'onboarding.signup-link-ids' );
		$pui_status_cache = $container->get( 'pui.status-cache' );
		$dcc_status_cache = $container->get( 'dcc.status-cache' );
		return new SettingsListener(
			$settings,
			$fields,
			$webhook_registrar,
			$cache,
			$state,
			$bearer,
			$page_id,
			$signup_link_cache,
			$signup_link_ids,
			$pui_status_cache,
			$dcc_status_cache
		);
	},
	'wcgateway.order-processor'                            => static function ( ContainerInterface $container ): OrderProcessor {

		$session_handler              = $container->get( 'session.handler' );
		$order_endpoint               = $container->get( 'api.endpoint.order' );
		$order_factory                = $container->get( 'api.factory.order' );
		$threed_secure                = $container->get( 'button.helper.three-d-secure' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		$settings                      = $container->get( 'wcgateway.settings' );
		$environment                   = $container->get( 'onboarding.environment' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		$subscription_helper = $container->get( 'subscription.helper' );
		$order_helper = $container->get( 'api.order-helper' );
		return new OrderProcessor(
			$session_handler,
			$order_endpoint,
			$order_factory,
			$threed_secure,
			$authorized_payments_processor,
			$settings,
			$logger,
			$environment,
			$subscription_helper,
			$order_helper
		);
	},
	'wcgateway.processor.refunds'                          => static function ( ContainerInterface $container ): RefundProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint    = $container->get( 'api.endpoint.payments' );
		$logger                        = $container->get( 'woocommerce.logger.woocommerce' );
		return new RefundProcessor( $order_endpoint, $payments_endpoint, $logger );
	},
	'wcgateway.processor.authorized-payments'              => static function ( ContainerInterface $container ): AuthorizedPaymentsProcessor {
		$order_endpoint    = $container->get( 'api.endpoint.order' );
		$payments_endpoint = $container->get( 'api.endpoint.payments' );
		$logger = $container->get( 'woocommerce.logger.woocommerce' );
		$notice              = $container->get( 'wcgateway.notice.authorize-order-action' );
		$settings            = $container->get( 'wcgateway.settings' );
		$subscription_helper = $container->get( 'subscription.helper' );
		return new AuthorizedPaymentsProcessor(
			$order_endpoint,
			$payments_endpoint,
			$logger,
			$notice,
			$settings,
			$subscription_helper
		);
	},
	'wcgateway.admin.render-authorize-action'              => static function ( ContainerInterface $container ): RenderAuthorizeAction {
		$column = $container->get( 'wcgateway.admin.orders-payment-status-column' );
		return new RenderAuthorizeAction( $column );
	},
	'wcgateway.admin.order-payment-status'                 => static function ( ContainerInterface $container ): PaymentStatusOrderDetail {
		$column = $container->get( 'wcgateway.admin.orders-payment-status-column' );
		return new PaymentStatusOrderDetail( $column );
	},
	'wcgateway.admin.orders-payment-status-column'         => static function ( ContainerInterface $container ): OrderTablePaymentStatusColumn {
		$settings = $container->get( 'wcgateway.settings' );
		return new OrderTablePaymentStatusColumn( $settings );
	},
	'wcgateway.admin.fees-renderer'                        => static function ( ContainerInterface $container ): FeesRenderer {
		return new FeesRenderer();
	},

	'wcgateway.settings.should-render-settings'            => static function ( ContainerInterface $container ): bool {

		$sections = array(
			Settings::CONNECTION_TAB_ID => __( 'Connection', 'woocommerce-paypal-payments' ),
			PayPalGateway::ID           => __( 'Standard Payments', 'woocommerce-paypal-payments' ),
			Settings::PAY_LATER_TAB_ID  => __( 'Pay Later', 'woocommerce-paypal-payments' ),
			CreditCardGateway::ID       => __( 'Advanced Card Processing', 'woocommerce-paypal-payments' ),
			CardButtonGateway::ID       => __( 'Standard Card Button', 'woocommerce-paypal-payments' ),
		);

		$current_page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );

		return array_key_exists( $current_page_id, $sections );
	},

	'wcgateway.settings.fields'                            => static function ( ContainerInterface $container ): array {

		$should_render_settings = $container->get( 'wcgateway.settings.should-render-settings' );

		if ( ! $should_render_settings ) {
			return array();
		}

		$state = $container->get( 'onboarding.state' );
		assert( $state instanceof State );

		$messages_disclaimers = $container->get( 'button.helper.messages-disclaimers' );

		$dcc_applies = $container->get( 'api.helpers.dccapplies' );
		assert( $dcc_applies instanceof DccApplies );

		$onboarding_options_renderer = $container->get( 'onboarding.render-options' );
		assert( $onboarding_options_renderer instanceof OnboardingOptionsRenderer );

		$render_preview_element = function ( string $id ): string {
			return '
<div class="ppcp-preview ppcp-button-preview">
	<h4>' . __( 'Preview', 'woocommerce-paypal-payments' ) . '</h4>
	<div id="' . $id . '" class="ppcp-button-preview-inner"></div>
</div>';
		};

		$subscription_helper = $container->get( 'subscription.helper' );
		assert( $subscription_helper instanceof SubscriptionHelper );

		$has_enabled_separate_button_gateways = $container->get( 'wcgateway.settings.has_enabled_separate_button_gateways' );

		$fields              = array(
			'checkout_settings_heading'              => array(
				'heading'      => __( 'Standard Payments Settings', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'title'                                  => array(
				'title'        => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => __( 'PayPal', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'dcc_enabled'                            => array(
				'title'        => __( 'Enable/Disable', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'description'  => __( 'Once enabled, the Credit Card option will show up in the checkout.', 'woocommerce-paypal-payments' ),
				'label'        => __( 'Enable Advanced Card Processing', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'gateway'      => 'dcc',
				'requirements' => array(
					'dcc',
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
			),
			'dcc_gateway_title'                      => array(
				'title'        => __( 'Title', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'description'  => __(
					'This controls the title which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => __( 'Credit Cards', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'description'                            => array(
				'title'        => __( 'Description', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'desc_tip'     => true,
				'description'  => __(
					'This controls the description which the user sees during checkout.',
					'woocommerce-paypal-payments'
				),
				'default'      => __(
					'Pay via PayPal; you can pay with your credit card if you don\'t have a PayPal account.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'intent'                                 => array(
				'title'        => __( 'Intent', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'capture',
				'desc_tip'     => true,
				'description'  => __(
					'The intent to either capture payment immediately or authorize a payment for an order after order creation.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'capture'   => __( 'Capture', 'woocommerce-paypal-payments' ),
					'authorize' => __( 'Authorize', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'capture_for_virtual_only'               => array(
				'title'        => __( 'Capture Virtual-Only Orders ', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If the order contains exclusively virtual items, enable this to immediately capture, rather than authorize, the transaction.',
					'woocommerce-paypal-payments'
				),
				'label'        => __( 'Capture Virtual-Only Orders', 'woocommerce-paypal-payments' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'payee_preferred'                        => array(
				'title'        => __( 'Instant Payments ', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'If you enable this setting, PayPal will be instructed not to allow the buyer to use funding sources that take additional time to complete (for example, eChecks). Instead, the buyer will be required to use an instant funding source, such as an instant transfer, a credit/debit card, or Pay Later.',
					'woocommerce-paypal-payments'
				),
				'label'        => __( 'Require Instant Payment', 'woocommerce-paypal-payments' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'brand_name'                             => array(
				'title'        => __( 'Brand Name', 'woocommerce-paypal-payments' ),
				'type'         => 'text',
				'default'      => get_bloginfo( 'name' ),
				'desc_tip'     => true,
				'description'  => __(
					'Control the name of your shop, customers will see in the PayPal process.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'landing_page'                           => array(
				'title'        => __( 'Landing Page', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Type of PayPal page to display.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					ApplicationContext::LANDING_PAGE_LOGIN => __( 'Login (PayPal account login)', 'woocommerce-paypal-payments' ),
					ApplicationContext::LANDING_PAGE_BILLING => __( 'Billing (Non-PayPal account)', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'disable_funding'                        => array(
				'title'        => __( 'Hide Funding Source(s)', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => false,
				'description'  => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'By default, all possible funding sources will be shown. This setting can disable funding sources such as Credit Cards, Pay Later, Venmo, or other %1$sAlternative Payment Methods%2$s.', 'woocommerce-paypal-payments' ),
					'<a
						href="https://developer.paypal.com/docs/checkout/apm/"
						target="_blank"
					>',
					'</a>'
				),
				'options'      => $container->get( 'wcgateway.all-funding-sources' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'vault_enabled'                          => array(
				'title'        => __( 'Vaulting', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => $container->get( 'button.helper.vaulting-label' ),
				'description'  => __( 'Allow registered buyers to save PayPal and Credit Card accounts. Allow Subscription renewals.', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => array( 'paypal', 'dcc' ),
				'input_class'  => $container->get( 'wcgateway.helper.vaulting-scope' ) ? array() : array( 'ppcp-disabled-checkbox' ),
			),
			'subscription_behavior_when_vault_fails' => array(
				'title'                => __( 'Subscription capture behavior if Vault fails', 'woocommerce-paypal-payments' ),
				'type'                 => 'select',
				'classes'              => $subscription_helper->plugin_is_active() ? array() : array( 'hide' ),
				'input_class'          => array( 'wc-enhanced-select' ),
				'default'              => 'void_auth',
				'desc_tip'             => true,
				'description'          => __( 'By default, subscription payments are captured only when saving the payment method was successful. Without a saved payment method, automatic renewal payments are not possible.', 'woocommerce-paypal-payments' ),
				'description_with_tip' => __( 'Determines whether authorized payments for subscription orders are captured or voided if there is no saved payment method. This only applies when the intent Capture is used for the subscription order.', 'woocommerce-paypal-payments' ),
				'options'              => array(
					'void_auth'           => __( 'Void authorization & fail the order/subscription', 'woocommerce-paypal-payments' ),
					'capture_auth'        => __( 'Capture authorized payment & set subscription to Manual Renewal', 'woocommerce-paypal-payments' ),
					'capture_auth_ignore' => __( 'Capture authorized payment & disregard missing payment method', 'woocommerce-paypal-payments' ),
				),
				'screens'              => array(
					State::STATE_ONBOARDED,
				),
				'requirements'         => array(),
				'gateway'              => array( 'paypal', 'dcc' ),
			),
			'card_billing_data_mode'                 => array(
				'title'        => __( 'Card billing data handling', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'desc_tip'     => true,
				'description'  => __( 'Using the WC form data increases convenience for the customers, but can cause issues if card details do not match the billing data in the checkout form.', 'woocommerce-paypal-payments' ),
				'default'      => $container->get( 'wcgateway.settings.card_billing_data_mode.default' ),
				'options'      => array(
					CardBillingMode::USE_WC        => __( 'Use WC checkout form data (do not show any address fields)', 'woocommerce-paypal-payments' ),
					CardBillingMode::MINIMAL_INPUT => __( 'Request only name and postal code', 'woocommerce-paypal-payments' ),
					CardBillingMode::NO_WC         => __( 'Do not use WC checkout form data (request all address fields)', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => array( 'paypal', CardButtonGateway::ID ),
			),
			'allow_card_button_gateway'              => array(
				'title'        => __( 'Separate Card Button from PayPal gateway', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'desc_tip'     => true,
				'label'        => __( 'Enable a separate payment gateway for the branded PayPal Debit or Credit Card button.', 'woocommerce-paypal-payments' ),
				'description'  => __( 'By default, the Debit or Credit Card button is displayed in the Standard Payments payment gateway. This setting creates a second gateway for the Card button.', 'woocommerce-paypal-payments' ),
				'default'      => $container->get( 'wcgateway.settings.allow_card_button_gateway.default' ),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// General button styles.
			'button_style_heading'                   => array(
				'heading'      => __( 'Checkout Buttons', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Customize the appearance of the PayPal smart buttons on the %1$sCheckout page%2$s.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-checkout" target="_blank">',
					'</a>'
				),
			),
			'button_enabled'                         => array(
				'title'        => __( 'Enable buttons on Checkout', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Checkout', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_layout'                          => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'classes'      => $has_enabled_separate_button_gateways ? array( 'hide' ) : array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'vertical',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_tagline'                         => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'default'      => false,
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_label'                           => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				/**
				 * Returns default label ID of the PayPal button.
				 */
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_color'                           => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_shape'                           => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_preview'                         => array(
				'type'         => 'ppcp-text',
				'text'         => $render_preview_element( 'ppcpCheckoutButtonPreview' ),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// Single product page.
			'button_product_heading'                 => array(
				'heading'      => __( 'Single Product Page Buttons', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Customize the appearance of the PayPal smart buttons on the %1$sSingle Product Page%2$s.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-single-product" target="_blank">',
					'</a>'
				),
			),
			'button_product_enabled'                 => array(
				'title'        => __( 'Enable buttons on Single Product', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Single Product', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_layout'                  => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_tagline'                 => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_label'                   => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				/**
				 * Returns default label ID of the PayPal button on product pages.
				 */
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_product_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_color'                   => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_shape'                   => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_product_preview'                 => array(
				'type'         => 'ppcp-text',
				'text'         => $render_preview_element( 'ppcpProductButtonPreview' ),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// Cart settings.
			'button_cart_heading'                    => array(
				'heading'      => __( 'Cart Buttons', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Customize the appearance of the PayPal smart buttons %1$son the Cart page%2$s.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-cart" target="_blank">',
					'</a>'
				),
			),
			'button_cart_enabled'                    => array(
				'title'        => __( 'Enable buttons on Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Cart', 'woocommerce-paypal-payments' ),
				'default'      => true,
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_layout'                     => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'horizontal',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_tagline'                    => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_label'                      => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				/**
				 * Returns default label ID of the PayPal button in cart.
				 */
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_cart_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_color'                      => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_shape'                      => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_cart_preview'                    => array(
				'type'         => 'ppcp-text',
				'text'         => $render_preview_element( 'ppcpCartButtonPreview' ),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			// Mini cart settings.
			'button_mini-cart_heading'               => array(
				'heading'      => __( 'Mini Cart Buttons', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
				'description'  => sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
					__( 'Customize the appearance of the PayPal smart buttons %1$son the Mini Cart page%2$s.', 'woocommerce-paypal-payments' ),
					'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-mini-cart" target="_blank">',
					'</a>'
				),
			),
			'button_mini-cart_enabled'               => array(
				'title'        => __( 'Enable buttons on Mini Cart', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable on Mini Cart', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_layout'                => array(
				'title'        => __( 'Button Layout', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'vertical',
				'desc_tip'     => true,
				'description'  => __(
					'If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'vertical'   => __( 'Vertical', 'woocommerce-paypal-payments' ),
					'horizontal' => __( 'Horizontal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_tagline'               => array(
				'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
				'type'         => 'checkbox',
				'label'        => __( 'Enable tagline', 'woocommerce-paypal-payments' ),
				'default'      => false,
				'desc_tip'     => true,
				'description'  => __(
					'Add the tagline. This line will only show up, if you select a horizontal layout.',
					'woocommerce-paypal-payments'
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_label'                 => array(
				'title'        => __( 'Button Label', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				/**
				 * Returns default label ID of the PayPal button in mini cart.
				 */
				'default'      => apply_filters( 'woocommerce_paypal_payments_button_mini_cart_label_default', 'paypal' ),
				'desc_tip'     => true,
				'description'  => __(
					'This controls the label on the primary button.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'paypal'   => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'checkout' => __( 'Checkout', 'woocommerce-paypal-payments' ),
					'buynow'   => __( 'PayPal Buy Now', 'woocommerce-paypal-payments' ),
					'pay'      => __( 'Pay with PayPal', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_color'                 => array(
				'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'gold',
				'desc_tip'     => true,
				'description'  => __(
					'Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'gold'   => __( 'Gold (Recommended)', 'woocommerce-paypal-payments' ),
					'blue'   => __( 'Blue', 'woocommerce-paypal-payments' ),
					'silver' => __( 'Silver', 'woocommerce-paypal-payments' ),
					'black'  => __( 'Black', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_shape'                 => array(
				'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => 'rect',
				'desc_tip'     => true,
				'description'  => __(
					'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
					'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),
			'button_mini-cart_height'                => array(
				'title'             => __( 'Button Height', 'woocommerce-paypal-payments' ),
				'type'              => 'number',
				'default'           => '35',
				'custom_attributes' => array(
					'min' => 25,
					'max' => 55,
				),
				'desc_tip'          => true,
				'description'       => __( 'Add a value from 25 to 55.', 'woocommerce-paypal-payments' ),
				'screens'           => array(
					State::STATE_START,
					State::STATE_ONBOARDED,
				),
				'requirements'      => array(),
				'gateway'           => 'paypal',
			),
			'button_mini-cart_preview'               => array(
				'type'         => 'ppcp-text',
				'text'         => $render_preview_element( 'ppcpMiniCartButtonPreview' ),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(),
				'gateway'      => 'paypal',
			),

			'disable_cards'                          => array(
				'title'        => __( 'Disable specific credit cards', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'By default all possible credit cards will be accepted. You can disable some cards, if you wish.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'visa'       => _x( 'Visa', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'mastercard' => _x( 'Mastercard', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'amex'       => _x( 'American Express', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'discover'   => _x( 'Discover', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'jcb'        => _x( 'JCB', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'elo'        => _x( 'Elo', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'hiper'      => _x( 'Hiper', 'Name of credit card', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'card_icons'                             => array(
				'title'        => __( 'Show logo of the following credit cards', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-multiselect',
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => array(),
				'desc_tip'     => true,
				'description'  => __(
					'Define which cards you want to display in your checkout.',
					'woocommerce-paypal-payments'
				),
				'options'      => array(
					'visa'            => _x( 'Visa (light)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'visa-dark'       => _x( 'Visa (dark)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'mastercard'      => _x( 'Mastercard (light)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'mastercard-dark' => _x( 'Mastercard (dark)', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'amex'            => _x( 'American Express', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'discover'        => _x( 'Discover', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'jcb'             => _x( 'JCB', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'elo'             => _x( 'Elo', 'Name of credit card', 'woocommerce-paypal-payments' ),
					'hiper'           => _x( 'Hiper', 'Name of credit card', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'3d_secure_heading'                      => array(
				'heading'      => __( '3D Secure', 'woocommerce-paypal-payments' ),
				'type'         => 'ppcp-heading',
				'description'  => wp_kses_post(
					sprintf(
					// translators: %1$s and %2$s is a link tag.
						__(
							'3D Secure benefits cardholders and merchants by providing
                                  an additional layer of verification using Verified by Visa,
                                  MasterCard SecureCode and American Express SafeKey.
                                  %1$sLearn more about 3D Secure.%2$s',
							'woocommerce-paypal-payments'
						),
						'<a
                            rel="noreferrer noopener"
                            href="https://woocommerce.com/posts/introducing-strong-customer-authentication-sca/"
                            >',
						'</a>'
					)
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
			'3d_secure_contingency'                  => array(
				'title'        => __( 'Contingency for 3D Secure', 'woocommerce-paypal-payments' ),
				'type'         => 'select',
				'description'  => sprintf(
				// translators: %1$s and %2$s opening and closing ul tag, %3$s and %4$s opening and closing li tag.
					__( '%1$s%3$sNo 3D Secure will cause transactions to be denied if 3D Secure is required by the bank of the cardholder.%4$s%3$sSCA_WHEN_REQUIRED returns a 3D Secure contingency when it is a mandate in the region where you operate.%4$s%3$sSCA_ALWAYS triggers 3D Secure for every transaction, regardless of SCA requirements.%4$s%2$s', 'woocommerce-paypal-payments' ),
					'<ul>',
					'</ul>',
					'<li>',
					'</li>'
				),
				'class'        => array(),
				'input_class'  => array( 'wc-enhanced-select' ),
				'default'      => $container->get( 'api.shop.is-psd2-country' ) ? 'SCA_WHEN_REQUIRED' : 'NO_3D_SECURE',
				'desc_tip'     => true,
				'options'      => array(
					'NO_3D_SECURE'      => __( 'No 3D Secure (transaction will be denied if 3D Secure is required)', 'woocommerce-paypal-payments' ),
					'SCA_WHEN_REQUIRED' => __( '3D Secure when required', 'woocommerce-paypal-payments' ),
					'SCA_ALWAYS'        => __( 'Always trigger 3D Secure', 'woocommerce-paypal-payments' ),
				),
				'screens'      => array(
					State::STATE_ONBOARDED,
				),
				'requirements' => array(
					'dcc',
				),
				'gateway'      => 'dcc',
			),
		);
		if ( ! defined( 'PPCP_FLAG_SUBSCRIPTION' ) || ! PPCP_FLAG_SUBSCRIPTION ) {
			unset( $fields['vault_enabled'] );
		}

		/**
		 * Depending on your store location, some credit cards can't be used.
		 * Here, we filter them out.
		 */
		$card_options = $fields['disable_cards']['options'];
		$card_icons = $fields['card_icons']['options'];
		$dark_versions = array();
		foreach ( $card_options as $card => $label ) {
			if ( $dcc_applies->can_process_card( $card ) ) {
				if ( 'visa' === $card || 'mastercard' === $card ) {
					$dark_versions = array(
						'visa-dark'       => $card_icons['visa-dark'],
						'mastercard-dark' => $card_icons['mastercard-dark'],
					);
				}
				continue;
			}
			unset( $card_options[ $card ] );
		}

		$fields['disable_cards']['options'] = $card_options;
		$fields['card_icons']['options'] = array_merge( $dark_versions, $card_options );

		return $fields;
	},

	'wcgateway.all-funding-sources'                        => static function( ContainerInterface $container ): array {
		return array(
			'card'        => _x( 'Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'sepa'        => _x( 'SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'bancontact'  => _x( 'Bancontact', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'blik'        => _x( 'BLIK', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'eps'         => _x( 'eps', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'giropay'     => _x( 'giropay', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'ideal'       => _x( 'iDEAL', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'mercadopago' => _x( 'Mercado Pago', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'mybank'      => _x( 'MyBank', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'p24'         => _x( 'Przelewy24', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'sofort'      => _x( 'Sofort', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'venmo'       => _x( 'Venmo', 'Name of payment method', 'woocommerce-paypal-payments' ),
		);
	},

	'wcgateway.checkout.address-preset'                    => static function( ContainerInterface $container ): CheckoutPayPalAddressPreset {

		return new CheckoutPayPalAddressPreset(
			$container->get( 'session.handler' )
		);
	},
	'wcgateway.url'                                        => static function ( ContainerInterface $container ): string {
		return plugins_url(
			$container->get( 'wcgateway.relative-path' ),
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'wcgateway.relative-path'                              => static function( ContainerInterface $container ): string {
		return 'modules/ppcp-wc-gateway/';
	},
	'wcgateway.absolute-path'                              => static function( ContainerInterface $container ): string {
		return plugin_dir_path(
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		) .
			$container->get( 'wcgateway.relative-path' );
	},
	'wcgateway.endpoint.return-url'                        => static function ( ContainerInterface $container ) : ReturnUrlEndpoint {
		$gateway  = $container->get( 'wcgateway.paypal-gateway' );
		$endpoint = $container->get( 'api.endpoint.order' );
		$prefix   = $container->get( 'api.prefix' );
		return new ReturnUrlEndpoint(
			$gateway,
			$endpoint,
			$prefix
		);
	},

	'wcgateway.transaction-url-sandbox'                    => static function ( ContainerInterface $container ): string {
		return 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
	},

	'wcgateway.transaction-url-live'                       => static function ( ContainerInterface $container ): string {
		return 'https://www.paypal.com/cgi-bin/webscr?cmd=_view-a-trans&id=%s';
	},

	'wcgateway.transaction-url-provider'                   => static function ( ContainerInterface $container ): TransactionUrlProvider {
		$sandbox_url_base = $container->get( 'wcgateway.transaction-url-sandbox' );
		$live_url_base    = $container->get( 'wcgateway.transaction-url-live' );

		return new TransactionUrlProvider( $sandbox_url_base, $live_url_base );
	},

	'wcgateway.helper.dcc-product-status'                  => static function ( ContainerInterface $container ) : DCCProductStatus {

		$settings         = $container->get( 'wcgateway.settings' );
		$partner_endpoint = $container->get( 'api.endpoint.partners' );
		return new DCCProductStatus(
			$settings,
			$partner_endpoint,
			$container->get( 'dcc.status-cache' ),
			$container->get( 'api.helpers.dccapplies' )
		);
	},

	'button.helper.messages-disclaimers'                   => static function ( ContainerInterface $container ): MessagesDisclaimers {
		return new MessagesDisclaimers(
			$container->get( 'api.shop.country' )
		);
	},

	'wcgateway.funding-source.renderer'                    => function ( ContainerInterface $container ) : FundingSourceRenderer {
		return new FundingSourceRenderer(
			$container->get( 'wcgateway.settings' )
		);
	},
	'wcgateway.checkout-helper'                            => static function ( ContainerInterface $container ): CheckoutHelper {
		return new CheckoutHelper();
	},
	'wcgateway.pay-upon-invoice-order-endpoint'            => static function ( ContainerInterface $container ): PayUponInvoiceOrderEndpoint {
		return new PayUponInvoiceOrderEndpoint(
			$container->get( 'api.host' ),
			$container->get( 'api.bearer' ),
			$container->get( 'api.factory.order' ),
			$container->get( 'wcgateway.pay-upon-invoice-fraudnet' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wcgateway.pay-upon-invoice-payment-source-factory'    => static function ( ContainerInterface $container ): PaymentSourceFactory {
		return new PaymentSourceFactory();
	},
	'wcgateway.pay-upon-invoice-gateway'                   => static function ( ContainerInterface $container ): PayUponInvoiceGateway {
		return new PayUponInvoiceGateway(
			$container->get( 'wcgateway.pay-upon-invoice-order-endpoint' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.pay-upon-invoice-payment-source-factory' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'wcgateway.pay-upon-invoice-helper' ),
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.processor.refunds' )
		);
	},
	'wcgateway.pay-upon-invoice-fraudnet-session-id'       => static function ( ContainerInterface $container ): FraudNetSessionId {
		return new FraudNetSessionId();
	},
	'wcgateway.pay-upon-invoice-fraudnet-source-website-id' => static function ( ContainerInterface $container ): FraudNetSourceWebsiteId {
		return new FraudNetSourceWebsiteId( $container->get( 'api.merchant_id' ) );
	},
	'wcgateway.pay-upon-invoice-fraudnet'                  => static function ( ContainerInterface $container ): FraudNet {
		$session_id = $container->get( 'wcgateway.pay-upon-invoice-fraudnet-session-id' );
		$source_website_id = $container->get( 'wcgateway.pay-upon-invoice-fraudnet-source-website-id' );
		return new FraudNet(
			(string) $session_id(),
			(string) $source_website_id()
		);
	},
	'wcgateway.pay-upon-invoice-helper'                    => static function( ContainerInterface $container ): PayUponInvoiceHelper {
		return new PayUponInvoiceHelper(
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'api.shop.country' )
		);
	},
	'wcgateway.pay-upon-invoice-product-status'            => static function( ContainerInterface $container ): PayUponInvoiceProductStatus {
		return new PayUponInvoiceProductStatus(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'api.endpoint.partners' ),
			$container->get( 'pui.status-cache' )
		);
	},
	'wcgateway.pay-upon-invoice'                           => static function ( ContainerInterface $container ): PayUponInvoice {
		return new PayUponInvoice(
			$container->get( 'wcgateway.url' ),
			$container->get( 'wcgateway.pay-upon-invoice-fraudnet' ),
			$container->get( 'wcgateway.pay-upon-invoice-order-endpoint' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'onboarding.state' ),
			$container->get( 'wcgateway.is-ppcp-settings-page' ),
			$container->get( 'wcgateway.current-ppcp-settings-page-id' ),
			$container->get( 'wcgateway.pay-upon-invoice-product-status' ),
			$container->get( 'wcgateway.pay-upon-invoice-helper' ),
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'api.factory.capture' ),
			$container->get( 'session.handler' )
		);
	},
	'wcgateway.oxxo'                                       => static function( ContainerInterface $container ): OXXO {
		return new OXXO(
			$container->get( 'wcgateway.checkout-helper' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'ppcp.asset-version' )
		);
	},
	'wcgateway.oxxo-gateway'                               => static function( ContainerInterface $container ): OXXOGateway {
		return new OXXOGateway(
			$container->get( 'api.endpoint.order' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'wcgateway.url' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wcgateway.endpoint.oxxo'                              => static function ( ContainerInterface $container ): OXXOEndpoint {
		return new OXXOEndpoint(
			$container->get( 'button.request-data' ),
			$container->get( 'api.endpoint.order' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'api.factory.shipping-preference' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'wcgateway.logging.is-enabled'                         => function ( ContainerInterface $container ) : bool {
		$settings = $container->get( 'wcgateway.settings' );

		/**
		 * Whether the logging of the plugin errors/events is enabled.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_is_logging_enabled',
			$settings->has( 'logging_enabled' ) && $settings->get( 'logging_enabled' )
		);
	},

	'wcgateway.helper.vaulting-scope'                      => static function ( ContainerInterface $container ): bool {
		try {
			$token = $container->get( 'api.bearer' )->bearer();
			return $token->vaulting_available();
		} catch ( RuntimeException $exception ) {
			return false;
		}
	},

	'button.helper.vaulting-label'                         => static function ( ContainerInterface $container ): string {
		$vaulting_label = sprintf(
		// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
			__( 'Enable saved cards, PayPal accounts, and subscription features on your store. Payment methods are saved in the secure %1$sPayPal Vault%2$s.', 'woocommerce-paypal-payments' ),
			'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#vaulting-saving-a-payment-method" target="_blank">',
			'</a>'
		);

		if ( ! $container->get( 'wcgateway.helper.vaulting-scope' ) ) {
			$vaulting_label .= sprintf(
				// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
				__( ' To use vaulting features, you must %1$senable vaulting on your account%2$s.', 'woocommerce-paypal-payments' ),
				'<a
					href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/#enable-vaulting-on-your-live-account"
					target="_blank"
				>',
				'</a>'
			);
		}

		$vaulting_label .= '<p class="description">';
		$vaulting_label .= sprintf(
		// translators: %1$s, %2$s, %3$s and %4$s are the opening and closing of HTML <a> tag.
			__( 'This will disable all %1$sPay Later%2$s features and %3$sAlternative Payment Methods%4$s on your site.', 'woocommerce-paypal-payments' ),
			'<a
					href="https://woocommerce.com/document/woocommerce-paypal-payments/#pay-later"
					target="_blank"
				>',
			'</a>',
			'<a
					href="https://woocommerce.com/document/woocommerce-paypal-payments/#alternative-payment-methods"
					target="_blank"
				>',
			'</a>'
		);
		$vaulting_label .= '</p>';

		return $vaulting_label;
	},

	'wcgateway.settings.card_billing_data_mode.default'    => static function ( ContainerInterface $container ): string {
		return $container->get( 'api.shop.is-latin-america' ) ? CardBillingMode::MINIMAL_INPUT : CardBillingMode::USE_WC;
	},
	'wcgateway.settings.card_billing_data_mode'            => static function ( ContainerInterface $container ): string {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof ContainerInterface );

		return $settings->has( 'card_billing_data_mode' ) ?
			(string) $settings->get( 'card_billing_data_mode' ) :
			$container->get( 'wcgateway.settings.card_billing_data_mode.default' );
	},

	'wcgateway.settings.allow_card_button_gateway.default' => static function ( ContainerInterface $container ): bool {
		return $container->get( 'api.shop.is-latin-america' );
	},
	'wcgateway.settings.allow_card_button_gateway'         => static function ( ContainerInterface $container ): bool {
		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof ContainerInterface );

		return $settings->has( 'allow_card_button_gateway' ) ?
			(bool) $settings->get( 'allow_card_button_gateway' ) :
			$container->get( 'wcgateway.settings.allow_card_button_gateway.default' );
	},
	'wcgateway.settings.has_enabled_separate_button_gateways' => static function ( ContainerInterface $container ): bool {
		return (bool) $container->get( 'wcgateway.settings.allow_card_button_gateway' );
	},

	'order-tracking.is-tracking-available'                 => static function ( ContainerInterface $container ): bool {
		try {
			$bearer = $container->get( 'api.bearer' );
			assert( $bearer instanceof Bearer );

			$token = $bearer->bearer();
			return $token->is_tracking_available();
		} catch ( RuntimeException $exception ) {
			return false;
		}
	},

	'wcgateway.settings.should-disable-tracking-checkbox'  => static function ( ContainerInterface $container ): bool {
		$pui_helper = $container->get( 'wcgateway.pay-upon-invoice-helper' );
		assert( $pui_helper instanceof PayUponInvoiceHelper );

		$is_tracking_available = $container->get( 'order-tracking.is-tracking-available' );

		if ( ! $is_tracking_available ) {
			return true;
		}

		if ( $pui_helper->is_pui_gateway_enabled() ) {
			return true;
		}

		return false;
	},
	'wcgateway.settings.tracking-label'                    => static function ( ContainerInterface $container ): string {
		$tracking_label = sprintf(
		// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
			__( 'Enable shipment tracking information to be sent to PayPal for seller protection features. Required when %1$sPay upon Invoice%2$s is used.', 'woocommerce-paypal-payments' ),
			'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#pay-upon-invoice-PUI" target="_blank">',
			'</a>'
		);
		$is_tracking_available = $container->get( 'order-tracking.is-tracking-available' );

		if ( $is_tracking_available ) {
			return $tracking_label;
		}

		$tracking_label .= sprintf(
		// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
			__(
				' To use tracking features, you must %1$senable tracking on your account%2$s.',
				'woocommerce-paypal-payments'
			),
			'<a
					href="https://docs.woocommerce.com/document/woocommerce-paypal-payments/#enable-tracking-on-your-live-account"
					target="_blank"
				>',
			'</a>'
		);

		return $tracking_label;
	},
	'wcgateway.enable-dcc-url-sandbox'                     => static function ( ContainerInterface $container ): string {
		return 'https://www.sandbox.paypal.com/bizsignup/entry/product/ppcp';
	},
	'wcgateway.enable-dcc-url-live'                        => static function ( ContainerInterface $container ): string {
		return 'https://www.paypal.com/bizsignup/entry/product/ppcp';
	},
	'wcgateway.enable-pui-url-sandbox'                     => static function ( ContainerInterface $container ): string {
		return 'https://www.sandbox.paypal.com/bizsignup/entry?country.x=DE&product=payment_methods&capabilities=PAY_UPON_INVOICE';
	},
	'wcgateway.enable-pui-url-live'                        => static function ( ContainerInterface $container ): string {
		return 'https://www.paypal.com/bizsignup/entry?country.x=DE&product=payment_methods&capabilities=PAY_UPON_INVOICE';
	},
	'wcgateway.settings.connection.dcc-status-text'        => static function ( ContainerInterface $container ): string {
		$dcc_product_status = $container->get( 'wcgateway.helper.dcc-product-status' );
		assert( $dcc_product_status instanceof DCCProductStatus );

		$environment = $container->get( 'onboarding.environment' );
		assert( $environment instanceof Environment );

		$dcc_enabled = $dcc_product_status->dcc_is_active();

		$enabled_status_text  = esc_html__( 'Status: Enabled', 'woocommerce-paypal-payments' );
		$disabled_status_text = esc_html__( 'Status: Not yet enabled', 'woocommerce-paypal-payments' );

		$dcc_button_text = $dcc_enabled
			? esc_html__( 'Settings', 'woocommerce-paypal-payments' )
			: esc_html__( 'Enable Advanced Card Payments', 'woocommerce-paypal-payments' );

		$enable_dcc_url = $environment->current_environment_is( Environment::PRODUCTION )
			? $container->get( 'wcgateway.enable-dcc-url-live' )
			: $container->get( 'wcgateway.enable-dcc-url-sandbox' );

		$dcc_button_url = $dcc_enabled
			? admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-credit-card-gateway' )
			: $enable_dcc_url;

		return sprintf(
			'<p>%1$s %2$s</p><p><a target="%3$s" href="%4$s" class="button">%5$s</a></p>',
			$dcc_enabled ? $enabled_status_text : $disabled_status_text,
			$dcc_enabled ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>',
			$dcc_enabled ? '_self' : '_blank',
			esc_url( $dcc_button_url ),
			esc_html( $dcc_button_text )
		);
	},
	'wcgateway.settings.connection.pui-status-text'        => static function ( ContainerInterface $container ): string {
		$pui_product_status = $container->get( 'wcgateway.pay-upon-invoice-product-status' );
		assert( $pui_product_status instanceof PayUponInvoiceProductStatus );

		$environment = $container->get( 'onboarding.environment' );
		assert( $environment instanceof Environment );

		$pui_enabled = $pui_product_status->pui_is_active();

		$enabled_status_text  = esc_html__( 'Status: Enabled', 'woocommerce-paypal-payments' );
		$disabled_status_text = esc_html__( 'Status: Not yet enabled', 'woocommerce-paypal-payments' );

		$enable_pui_url = $environment->current_environment_is( Environment::PRODUCTION )
			? $container->get( 'wcgateway.enable-pui-url-live' )
			: $container->get( 'wcgateway.enable-pui-url-sandbox' );

		$pui_button_url = $pui_enabled
			? admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-pay-upon-invoice-gateway' )
			: $enable_pui_url;

		$pui_button_text = $pui_enabled
			? esc_html__( 'Settings', 'woocommerce-paypal-payments' )
			: esc_html__( 'Enable Pay upon Invoice', 'woocommerce-paypal-payments' );

		return sprintf(
			'<p>%1$s %2$s</p><p><a target="%3$s" href="%4$s" class="button">%5$s</a></p>',
			$pui_enabled ? $enabled_status_text : $disabled_status_text,
			$pui_enabled ? '<span class="dashicons dashicons-yes"></span>' : '<span class="dashicons dashicons-no"></span>',
			$pui_enabled ? '_self' : '_blank',
			esc_url( $pui_button_url ),
			esc_html( $pui_button_text )
		);
	},
	'pui.status-cache'                                     => static function( ContainerInterface $container ): Cache {
		return new Cache( 'ppcp-paypal-pui-status-cache' );
	},
	'dcc.status-cache'                                     => static function( ContainerInterface $container ): Cache {
		return new Cache( 'ppcp-paypal-dcc-status-cache' );
	},
	'wcgateway.settings.pay-later.messaging-locations'     => static function( ContainerInterface $container ): array {
		return array(
			'product'  => 'Single Product',
			'cart'     => 'Cart',
			'checkout' => 'Checkout',
		);
	},
	'wcgateway.settings.pay-later.button-locations'        => static function( ContainerInterface $container ): array {
		$button_locations = array();

		$settings = $container->get( 'wcgateway.settings' );
		assert( $settings instanceof Settings );

		$is_product_buttons_enabled = $settings->has( 'button_product_enabled' ) && $settings->get( 'button_product_enabled' );
		$is_mini_cart_buttons_enabled = $settings->has( 'button_mini-cart_enabled' ) && $settings->get( 'button_mini-cart_enabled' );
		$is_cart_buttons_enabled = $settings->has( 'button_cart_enabled' ) && $settings->get( 'button_cart_enabled' );
		$is_checkout_buttons_enabled = $settings->has( 'button_enabled' ) && $settings->get( 'button_enabled' );

		if ( $is_product_buttons_enabled ) {
			$button_locations['product'] = 'Single Product';
		}

		if ( $is_mini_cart_buttons_enabled ) {
			$button_locations['mini-cart'] = 'Mini Cart';
		}

		if ( $is_cart_buttons_enabled ) {
			$button_locations['cart'] = 'Cart';
		}

		if ( $is_checkout_buttons_enabled ) {
			$button_locations['checkout'] = 'Checkout';
		}

		return $button_locations;
	},
);
