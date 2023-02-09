<?php
/**
 * Registers and configures the necessary Javascript for the button, credit messaging and DCC fields.
 *
 * @package WooCommerce\PayPalCommerce\Button\Assets
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Assets;

use Exception;
use Psr\Log\LoggerInterface;
use WC_Product;
use WC_Product_Variation;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Endpoint\ApproveOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\CreateOrderEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\DataClientIdEndpoint;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Endpoint\StartPayPalVaultingEndpoint;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Onboarding\Environment;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Subscription\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class SmartButton
 */
class SmartButton implements SmartButtonInterface {

	use FreeTrialHandlerTrait;

	/**
	 * The Settings status helper.
	 *
	 * @var SettingsStatus
	 */
	protected $settings_status;

	/**
	 * The URL to the module.
	 *
	 * @var string
	 */
	private $module_url;

	/**
	 * The assets version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The Payer Factory.
	 *
	 * @var PayerFactory
	 */
	private $payer_factory;

	/**
	 * The client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * The Request Data.
	 *
	 * @var RequestData
	 */
	private $request_data;

	/**
	 * The DCC Applies helper.
	 *
	 * @var DccApplies
	 */
	private $dcc_applies;

	/**
	 * The Subscription Helper.
	 *
	 * @var SubscriptionHelper
	 */
	private $subscription_helper;

	/**
	 * The Messages apply helper.
	 *
	 * @var MessagesApply
	 */
	private $messages_apply;

	/**
	 * The environment object.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * The payment token repository.
	 *
	 * @var PaymentTokenRepository
	 */
	private $payment_token_repository;

	/**
	 * 3-letter currency code of the shop.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * All existing funding sources.
	 *
	 * @var array
	 */
	private $all_funding_sources;

	/**
	 * Whether the basic JS validation of the form iss enabled.
	 *
	 * @var bool
	 */
	private $basic_checkout_validation_enabled;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Cached payment tokens.
	 *
	 * @var PaymentToken[]|null
	 */
	private $payment_tokens = null;

	/**
	 * SmartButton constructor.
	 *
	 * @param string                 $module_url The URL to the module.
	 * @param string                 $version                            The assets version.
	 * @param SessionHandler         $session_handler The Session Handler.
	 * @param Settings               $settings The Settings.
	 * @param PayerFactory           $payer_factory The Payer factory.
	 * @param string                 $client_id The client ID.
	 * @param RequestData            $request_data The Request Data helper.
	 * @param DccApplies             $dcc_applies The DCC applies helper.
	 * @param SubscriptionHelper     $subscription_helper The subscription helper.
	 * @param MessagesApply          $messages_apply The Messages apply helper.
	 * @param Environment            $environment The environment object.
	 * @param PaymentTokenRepository $payment_token_repository The payment token repository.
	 * @param SettingsStatus         $settings_status The Settings status helper.
	 * @param string                 $currency 3-letter currency code of the shop.
	 * @param array                  $all_funding_sources All existing funding sources.
	 * @param bool                   $basic_checkout_validation_enabled Whether the basic JS validation of the form iss enabled.
	 * @param LoggerInterface        $logger The logger.
	 */
	public function __construct(
		string $module_url,
		string $version,
		SessionHandler $session_handler,
		Settings $settings,
		PayerFactory $payer_factory,
		string $client_id,
		RequestData $request_data,
		DccApplies $dcc_applies,
		SubscriptionHelper $subscription_helper,
		MessagesApply $messages_apply,
		Environment $environment,
		PaymentTokenRepository $payment_token_repository,
		SettingsStatus $settings_status,
		string $currency,
		array $all_funding_sources,
		bool $basic_checkout_validation_enabled,
		LoggerInterface $logger
	) {

		$this->module_url                        = $module_url;
		$this->version                           = $version;
		$this->session_handler                   = $session_handler;
		$this->settings                          = $settings;
		$this->payer_factory                     = $payer_factory;
		$this->client_id                         = $client_id;
		$this->request_data                      = $request_data;
		$this->dcc_applies                       = $dcc_applies;
		$this->subscription_helper               = $subscription_helper;
		$this->messages_apply                    = $messages_apply;
		$this->environment                       = $environment;
		$this->payment_token_repository          = $payment_token_repository;
		$this->settings_status                   = $settings_status;
		$this->currency                          = $currency;
		$this->all_funding_sources               = $all_funding_sources;
		$this->basic_checkout_validation_enabled = $basic_checkout_validation_enabled;
		$this->logger                            = $logger;
	}

	/**
	 * Registers the necessary action hooks to render the HTML depending on the settings.
	 *
	 * @return bool
	 */
	public function render_wrapper(): bool {
		if ( $this->settings->has( 'enabled' ) && $this->settings->get( 'enabled' ) ) {
			$this->render_button_wrapper_registrar();
			$this->render_message_wrapper_registrar();
		}

		if ( ! $this->can_save_vault_token() && $this->has_subscriptions() ) {
			return false;
		}

		if (
			$this->settings->has( 'dcc_enabled' )
			&& $this->settings->get( 'dcc_enabled' )
		) {
			add_action(
				$this->checkout_dcc_button_renderer_hook(),
				array(
					$this,
					'dcc_renderer',
				),
				11
			);

			add_action(
				$this->pay_order_renderer_hook(),
				array(
					$this,
					'dcc_renderer',
				),
				11
			);

			$subscription_helper = $this->subscription_helper;
			add_filter(
				'woocommerce_credit_card_form_fields',
				function ( array $default_fields, $id ) use ( $subscription_helper ) : array {
					if ( is_user_logged_in() && $this->settings->has( 'vault_enabled' ) && $this->settings->get( 'vault_enabled' ) && CreditCardGateway::ID === $id ) {

						$default_fields['card-vault'] = sprintf(
							'<p class="form-row form-row-wide"><label for="ppcp-credit-card-vault"><input class="ppcp-credit-card-vault" type="checkbox" id="ppcp-credit-card-vault" name="vault">%s</label></p>',
							esc_html__( 'Save your Credit Card', 'woocommerce-paypal-payments' )
						);
						if ( $subscription_helper->cart_contains_subscription() || $subscription_helper->order_pay_contains_subscription() ) {
							$default_fields['card-vault'] = '';
						}

						$tokens = $this->payment_token_repository->all_for_user_id( get_current_user_id() );
						if ( $tokens && $this->payment_token_repository->tokens_contains_card( $tokens ) ) {
							$output = sprintf(
								'<p class="form-row form-row-wide"><label>%1$s</label><select id="saved-credit-card" name="saved_credit_card"><option value="">%2$s</option>',
								esc_html__( 'Or select a saved Credit Card payment', 'woocommerce-paypal-payments' ),
								esc_html__( 'Choose a saved payment', 'woocommerce-paypal-payments' )
							);
							foreach ( $tokens as $token ) {
								if ( isset( $token->source()->card ) ) {
									$output .= sprintf(
										'<option value="%1$s">%2$s ...%3$s</option>',
										$token->id(),
										$token->source()->card->brand,
										$token->source()->card->last_digits
									);
								}
							}
							$output .= '</select></p>';

							$default_fields['saved-credit-card'] = $output;
						}
					}

					return $default_fields;
				},
				10,
				2
			);
		}

		if ( $this->is_free_trial_cart() ) {
			add_action(
				'woocommerce_review_order_after_submit',
				function () {
					$vaulted_email = $this->get_vaulted_paypal_email();
					if ( ! $vaulted_email ) {
						return;
					}

					?>
					<div class="ppcp-vaulted-paypal-details">
						<?php
						echo wp_kses_post(
							sprintf(
							// translators: %1$s - email, %2$s, %3$s - HTML tags for a link.
								esc_html__(
									'Using %2$s%1$s%3$s PayPal.',
									'woocommerce-paypal-payments'
								),
								$vaulted_email,
								'<b>',
								'</b>'
							)
						);
						?>
					</div>
					<?php
				}
			);
		}

		return true;
	}

	/**
	 * Registers the hooks to render the credit messaging HTML depending on the settings.
	 *
	 * @return bool
	 * @throws NotFoundException When a setting was not found.
	 */
	private function render_message_wrapper_registrar(): bool {
		if ( ! $this->settings_status->is_pay_later_messaging_enabled() ) {
			return false;
		}

		$selected_locations = $this->settings->has( 'pay_later_messaging_locations' ) ? $this->settings->get( 'pay_later_messaging_locations' ) : array();

		$not_enabled_on_cart = ! in_array( 'cart', $selected_locations, true );

		add_action(
			$this->proceed_to_checkout_button_renderer_hook(),
			function() use ( $not_enabled_on_cart ) {
				if ( ! is_cart() || $not_enabled_on_cart ) {
					return;
				}
				$this->message_renderer();
			},
			19
		);

		$not_enabled_on_product_page = ! in_array( 'product', $selected_locations, true );
		if (
			( is_product() || wc_post_content_has_shortcode( 'product_page' ) )
			&& ! $not_enabled_on_product_page
			&& ! is_checkout()
		) {
			add_action(
				$this->single_product_renderer_hook(),
				array( $this, 'message_renderer' ),
				30
			);
		}

		$not_enabled_on_checkout = ! in_array( 'checkout', $selected_locations, true );
		if ( ! $not_enabled_on_checkout ) {
			add_action(
				$this->checkout_dcc_button_renderer_hook(),
				array( $this, 'message_renderer' ),
				11
			);
			add_action(
				$this->pay_order_renderer_hook(),
				array( $this, 'message_renderer' ),
				11
			);
		}
		return true;
	}

	/**
	 * Registers the hooks where to render the button HTML according to the settings.
	 *
	 * @return bool
	 * @throws NotFoundException When a setting was not found.
	 */
	private function render_button_wrapper_registrar(): bool {

		$not_enabled_on_product_page = $this->settings->has( 'button_single_product_enabled' ) &&
			! $this->settings->get( 'button_single_product_enabled' );
		if (
			( is_product() || wc_post_content_has_shortcode( 'product_page' ) )
			&& ! $not_enabled_on_product_page
			// TODO: it seems like there is no easy way to properly handle vaulted PayPal free trial,
			// so disable the buttons for now everywhere except checkout for free trial.
			&& ! $this->is_free_trial_product()
			&& ! is_checkout()
		) {
			add_action(
				$this->single_product_renderer_hook(),
				function () {
					$product = wc_get_product();

					if (
						is_a( $product, WC_Product::class )
						&& ! $this->product_supports_payment( $product )
					) {

						return;
					}

					$this->button_renderer( PayPalGateway::ID );
				},
				31
			);
		}

		$not_enabled_on_minicart = $this->settings->has( 'button_mini_cart_enabled' ) &&
			! $this->settings->get( 'button_mini_cart_enabled' );
		if (
		! $not_enabled_on_minicart
			&& ! $this->is_free_trial_cart()
		) {
			add_action(
				$this->mini_cart_button_renderer_hook(),
				function () {
					if ( ! $this->can_save_vault_token() && $this->has_subscriptions() ) {
						return;
					}

					if ( $this->is_cart_price_total_zero() || $this->is_free_trial_cart() ) {
						return;
					}

					echo '<p
                                id="ppc-button-minicart"
                                class="woocommerce-mini-cart__buttons buttons"
                          ></p>';
				},
				30
			);
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( isset( $available_gateways['ppcp-gateway'] ) ) {
			add_action(
				$this->pay_order_renderer_hook(),
				function (): void {
					$this->button_renderer( PayPalGateway::ID );
					$this->button_renderer( CardButtonGateway::ID );
				}
			);
			add_action(
				$this->checkout_button_renderer_hook(),
				function (): void {
					$this->button_renderer( PayPalGateway::ID );
					$this->button_renderer( CardButtonGateway::ID );
				}
			);

			$not_enabled_on_cart = $this->settings->has( 'button_cart_enabled' ) &&
				! $this->settings->get( 'button_cart_enabled' );
			add_action(
				$this->proceed_to_checkout_button_renderer_hook(),
				function() use ( $not_enabled_on_cart ) {
					if ( ! is_cart() || $not_enabled_on_cart || $this->is_free_trial_cart() || $this->is_cart_price_total_zero() ) {
						return;
					}

					$this->button_renderer( PayPalGateway::ID );
				},
				20
			);
		}

		return true;
	}

	/**
	 * Enqueues the script.
	 *
	 * @return bool
	 * @throws NotFoundException When a setting was not found.
	 */
	public function enqueue(): bool {
		$buttons_enabled = $this->settings->has( 'enabled' ) && $this->settings->get( 'enabled' );
		if ( ! is_checkout() && ! $buttons_enabled ) {
			return false;
		}

		$load_script = false;
		if ( is_checkout() && $this->settings->has( 'dcc_enabled' ) && $this->settings->get( 'dcc_enabled' ) ) {
			$load_script = true;
		}
		if ( $this->load_button_component() ) {
			$load_script = true;
		}

		if ( in_array( $this->context(), array( 'pay-now', 'checkout' ), true ) ) {
			wp_enqueue_style(
				'gateway',
				untrailingslashit( $this->module_url ) . '/assets/css/gateway.css',
				array(),
				$this->version
			);

			if ( $this->can_render_dcc() ) {
				wp_enqueue_style(
					'ppcp-hosted-fields',
					untrailingslashit( $this->module_url ) . '/assets/css/hosted-fields.css',
					array(),
					$this->version
				);
			}
		}
		if ( $load_script ) {
			wp_enqueue_script(
				'ppcp-smart-button',
				untrailingslashit( $this->module_url ) . '/assets/js/button.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_localize_script(
				'ppcp-smart-button',
				'PayPalCommerceGateway',
				$this->localize_script()
			);
		}
		return true;
	}

	/**
	 * Renders the HTML for the buttons.
	 *
	 * @param string $gateway_id The gateway ID, like 'ppcp-gateway'.
	 */
	public function button_renderer( string $gateway_id ) {

		if ( ! $this->can_save_vault_token() && $this->has_subscriptions() ) {
			return;
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! isset( $available_gateways[ $gateway_id ] ) ) {
			return;
		}

		// The wrapper is needed for the loading spinner,
		// otherwise jQuery block() prevents buttons rendering.
		echo '<div class="ppc-button-wrapper"><div id="ppc-button-' . esc_attr( $gateway_id ) . '"></div></div>';
	}

	/**
	 * Renders the HTML for the credit messaging.
	 */
	public function message_renderer() {
		if ( ! $this->can_save_vault_token() && $this->has_subscriptions() ) {
			return false;
		}

		$product = wc_get_product();

		if (
			! is_checkout() && is_a( $product, WC_Product::class )
			/**
			 * The filter returning true if PayPal buttons can be rendered, or false otherwise.
			 */
			&& ! $this->product_supports_payment( $product )
		) {
			return;
		}

		echo '<div id="ppcp-messages" data-partner-attribution-id="Woo_PPCP"></div>';
	}

	/**
	 * The values for the credit messaging.
	 *
	 * @return array
	 * @throws NotFoundException When a setting was not found.
	 */
	private function message_values(): array {
		if ( ! $this->settings_status->is_pay_later_messaging_enabled() ) {
			return array();
		}

		$placement = is_checkout() ? 'payment' : ( is_cart() ? 'cart' : 'product' );
		$product   = wc_get_product();
		$amount    = ( is_a( $product, WC_Product::class ) ) ? wc_get_price_including_tax( $product ) : 0;

		if ( is_checkout() || is_cart() ) {
			$amount = WC()->cart->get_total( 'raw' );
		}

		$styling_per_location = $this->settings->has( 'pay_later_enable_styling_per_messaging_location' ) && $this->settings->get( 'pay_later_enable_styling_per_messaging_location' );
		$per_location         = is_checkout() ? 'checkout' : ( is_cart() ? 'cart' : 'product' );
		$location             = $styling_per_location ? $per_location : 'general';
		$setting_name_prefix  = "pay_later_{$location}_message";

		$layout        = $this->settings->has( "{$setting_name_prefix}_layout" ) ? $this->settings->get( "{$setting_name_prefix}_layout" ) : 'text';
		$logo_type     = $this->settings->has( "{$setting_name_prefix}_logo" ) ? $this->settings->get( "{$setting_name_prefix}_logo" ) : 'primary';
		$logo_position = $this->settings->has( "{$setting_name_prefix}_position" ) ? $this->settings->get( "{$setting_name_prefix}_position" ) : 'left';
		$text_color    = $this->settings->has( "{$setting_name_prefix}_color" ) ? $this->settings->get( "{$setting_name_prefix}_color" ) : 'black';
		$style_color   = $this->settings->has( "{$setting_name_prefix}_flex_color" ) ? $this->settings->get( "{$setting_name_prefix}_flex_color" ) : 'blue';
		$ratio         = $this->settings->has( "{$setting_name_prefix}_flex_ratio" ) ? $this->settings->get( "{$setting_name_prefix}_flex_ratio" ) : '1x1';

		return array(
			'wrapper'   => '#ppcp-messages',
			'amount'    => $amount,
			'placement' => $placement,
			'style'     => array(
				'layout' => $layout,
				'logo'   => array(
					'type'     => $logo_type,
					'position' => $logo_position,
				),
				'text'   => array(
					'color' => $text_color,
				),
				'color'  => $style_color,
				'ratio'  => $ratio,
			),
		);

	}

	/**
	 * Whether DCC fields can be rendered.
	 *
	 * @return bool
	 * @throws NotFoundException When a setting was not found.
	 */
	private function can_render_dcc() : bool {

		$can_render = $this->settings->has( 'dcc_enabled' ) && $this->settings->get( 'dcc_enabled' ) && $this->settings->has( 'client_id' ) && $this->settings->get( 'client_id' ) && $this->dcc_applies->for_country_currency();
		return $can_render;
	}

	/**
	 * Renders the HTML for the DCC fields.
	 */
	public function dcc_renderer() {

		$id = 'ppcp-hosted-fields';
		if ( ! $this->can_render_dcc() ) {
			return;
		}

		/**
		 * The WC filter returning the WC order button text.
		 * phpcs:disable WordPress.WP.I18n.TextDomainMismatch
		 */
		$label = 'checkout' === $this->context() ? apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) ) : __( 'Pay for order', 'woocommerce' );
		// phpcs:enable WordPress.WP.I18n.TextDomainMismatch

		printf(
			'<div id="%1$s" style="display:none;">
						<button type="submit" class="button alt ppcp-dcc-order-button" style="display: none;">%2$s</button>
					</div>
                    <div id="payments-sdk__contingency-lightbox"></div>
                    <style id="ppcp-hide-dcc">.payment_method_ppcp-credit-card-gateway {display:none;}</style>',
			esc_attr( $id ),
			esc_html( $label )
		);
	}

	/**
	 * Whether we can store vault tokens or not.
	 *
	 * @return bool
	 * @throws NotFoundException If a setting hasn't been found.
	 */
	public function can_save_vault_token(): bool {

		if ( ! $this->settings->has( 'client_id' ) || ! $this->settings->get( 'client_id' ) ) {
			return false;
		}

		if ( ! $this->settings->has( 'vault_enabled' ) || ! $this->settings->get( 'vault_enabled' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether we need to initialize the script to enable tokenization for subscriptions or not.
	 *
	 * @return bool
	 */
	private function has_subscriptions(): bool {
		if ( ! $this->subscription_helper->accept_only_automatic_payment_gateways() ) {
			return false;
		}
		if ( is_product() ) {
			return $this->subscription_helper->current_product_is_subscription();
		}
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			return $this->subscription_helper->order_pay_contains_subscription();
		}

		return $this->subscription_helper->cart_contains_subscription();
	}

	/**
	 * Retrieves the 3D Secure contingency settings.
	 *
	 * @return string
	 */
	private function get_3ds_contingency(): string {
		if ( $this->settings->has( '3d_secure_contingency' ) ) {
			$value = $this->settings->get( '3d_secure_contingency' );
			if ( $value ) {
				return $value;
			}
		}

		return 'SCA_WHEN_REQUIRED';
	}

	/**
	 * The localized data for the smart button.
	 *
	 * @return array
	 * @throws NotFoundException If a setting hasn't been found.
	 */
	private function localize_script(): array {
		global $wp;

		$is_free_trial_cart = $this->is_free_trial_cart();

		$this->request_data->enqueue_nonce_fix();
		$localize = array(
			'script_attributes'                 => $this->attributes(),
			'data_client_id'                    => array(
				'set_attribute'     => ( is_checkout() && $this->dcc_is_enabled() ) || $this->can_save_vault_token(),
				'endpoint'          => \WC_AJAX::get_endpoint( DataClientIdEndpoint::ENDPOINT ),
				'nonce'             => wp_create_nonce( DataClientIdEndpoint::nonce() ),
				'user'              => get_current_user_id(),
				'has_subscriptions' => $this->has_subscriptions(),
			),
			'redirect'                          => wc_get_checkout_url(),
			'context'                           => $this->context(),
			'ajax'                              => array(
				'change_cart'   => array(
					'endpoint' => \WC_AJAX::get_endpoint( ChangeCartEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ChangeCartEndpoint::nonce() ),
				),
				'create_order'  => array(
					'endpoint' => \WC_AJAX::get_endpoint( CreateOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( CreateOrderEndpoint::nonce() ),
				),
				'approve_order' => array(
					'endpoint' => \WC_AJAX::get_endpoint( ApproveOrderEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( ApproveOrderEndpoint::nonce() ),
				),
				'vault_paypal'  => array(
					'endpoint' => \WC_AJAX::get_endpoint( StartPayPalVaultingEndpoint::ENDPOINT ),
					'nonce'    => wp_create_nonce( StartPayPalVaultingEndpoint::nonce() ),
				),
			),
			'enforce_vault'                     => $this->has_subscriptions(),
			'can_save_vault_token'              => $this->can_save_vault_token(),
			'is_free_trial_cart'                => $is_free_trial_cart,
			'vaulted_paypal_email'              => ( is_checkout() && $is_free_trial_cart ) ? $this->get_vaulted_paypal_email() : '',
			'bn_codes'                          => $this->bn_codes(),
			'payer'                             => $this->payerData(),
			'button'                            => array(
				'wrapper'           => '#ppc-button-' . PayPalGateway::ID,
				'mini_cart_wrapper' => '#ppc-button-minicart',
				'cancel_wrapper'    => '#ppcp-cancel',
				'url'               => $this->url(),
				'mini_cart_style'   => array(
					'layout'  => $this->style_for_context( 'layout', 'mini-cart' ),
					'color'   => $this->style_for_context( 'color', 'mini-cart' ),
					'shape'   => $this->style_for_context( 'shape', 'mini-cart' ),
					'label'   => $this->style_for_context( 'label', 'mini-cart' ),
					'tagline' => $this->style_for_context( 'tagline', 'mini-cart' ),
					'height'  => $this->settings->has( 'button_mini-cart_height' ) && $this->settings->get( 'button_mini-cart_height' ) ? $this->normalize_height( (int) $this->settings->get( 'button_mini-cart_height' ) ) : 35,
				),
				'style'             => array(
					'layout'  => $this->style_for_context( 'layout', $this->context() ),
					'color'   => $this->style_for_context( 'color', $this->context() ),
					'shape'   => $this->style_for_context( 'shape', $this->context() ),
					'label'   => $this->style_for_context( 'label', $this->context() ),
					'tagline' => $this->style_for_context( 'tagline', $this->context() ),
				),
			),
			'separate_buttons'                  => array(
				'card' => array(
					'id'      => CardButtonGateway::ID,
					'wrapper' => '#ppc-button-' . CardButtonGateway::ID,
					'style'   => array(
						'shape' => $this->style_for_context( 'shape', $this->context() ),
						// TODO: color black, white from the gateway settings.
					),
				),
			),
			'hosted_fields'                     => array(
				'wrapper'     => '#ppcp-hosted-fields',
				'labels'      => array(
					'credit_card_number'       => '',
					'cvv'                      => '',
					'mm_yy'                    => __( 'MM/YY', 'woocommerce-paypal-payments' ),
					'fields_not_valid'         => __(
						'Unfortunately, your credit card details are not valid.',
						'woocommerce-paypal-payments'
					),
					'card_not_supported'       => __(
						'Unfortunately, we do not support your credit card.',
						'woocommerce-paypal-payments'
					),
					'cardholder_name_required' => __( 'Cardholder\'s first and last name are required, please fill the checkout form required fields.', 'woocommerce-paypal-payments' ),
				),
				'valid_cards' => $this->dcc_applies->valid_cards(),
				'contingency' => $this->get_3ds_contingency(),
			),
			'messages'                          => $this->message_values(),
			'labels'                            => array(
				'error'          => array(
					'generic'  => __(
						'Something went wrong. Please try again or choose another payment source.',
						'woocommerce-paypal-payments'
					),
					'required' => array(
						'generic'  => __(
							'Required form fields are not filled.',
							'woocommerce-paypal-payments'
						),
						// phpcs:ignore WordPress.WP.I18n
						'field'    => __( '%s is a required field.', 'woocommerce' ),
						'elements' => array(  // Map <form element name> => text for error messages.
							'terms' => __(
								'Please read and accept the terms and conditions to proceed with your order.',
								// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
								'woocommerce'
							),
						),
					),
				),
				// phpcs:ignore WordPress.WP.I18n
				'billing_field'  => _x( 'Billing %s', 'checkout-validation', 'woocommerce' ),
				// phpcs:ignore WordPress.WP.I18n
				'shipping_field' => _x( 'Shipping %s', 'checkout-validation', 'woocommerce' ),
			),
			'order_id'                          => 'pay-now' === $this->context() ? absint( $wp->query_vars['order-pay'] ) : 0,
			'single_product_buttons_enabled'    => $this->settings->has( 'button_product_enabled' ) && $this->settings->get( 'button_product_enabled' ),
			'mini_cart_buttons_enabled'         => $this->settings->has( 'button_mini-cart_enabled' ) && $this->settings->get( 'button_mini-cart_enabled' ),
			'basic_checkout_validation_enabled' => $this->basic_checkout_validation_enabled,
		);

		if ( $this->style_for_context( 'layout', 'mini-cart' ) !== 'horizontal' ) {
			$localize['button']['mini_cart_style']['tagline'] = false;
		}
		if ( $this->style_for_context( 'layout', $this->context() ) !== 'horizontal' ) {
			$localize['button']['style']['tagline'] = false;
		}

		$this->request_data->dequeue_nonce_fix();
		return $localize;
	}

	/**
	 * If we can find the payer data for a current customer, we will return it.
	 *
	 * @return array|null
	 */
	private function payerData() {

		$customer = WC()->customer;
		if ( ! is_user_logged_in() || ! ( $customer instanceof \WC_Customer ) ) {
			return null;
		}
		return $this->payer_factory->from_customer( $customer )->to_array();
	}

	/**
	 * The JavaScript SDK url to load.
	 *
	 * @return string
	 * @throws NotFoundException If a setting was not found.
	 */
	private function url(): string {
		$intent               = ( $this->settings->has( 'intent' ) ) ? $this->settings->get( 'intent' ) : 'capture';
		$product_intent       = $this->subscription_helper->current_product_is_subscription() ? 'authorize' : $intent;
		$other_context_intent = $this->subscription_helper->cart_contains_subscription() ? 'authorize' : $intent;

		$params = array(
			'client-id'        => $this->client_id,
			'currency'         => $this->currency,
			'integration-date' => PAYPAL_INTEGRATION_DATE,
			'components'       => implode( ',', $this->components() ),
			'vault'            => $this->can_save_vault_token() ? 'true' : 'false',
			'commit'           => is_checkout() ? 'true' : 'false',
			'intent'           => $this->context() === 'product' ? $product_intent : $other_context_intent,
		);
		if (
			$this->environment->current_environment_is( Environment::SANDBOX )
			&& defined( 'WP_DEBUG' ) && \WP_DEBUG && is_user_logged_in()
			&& WC()->customer instanceof \WC_Customer && WC()->customer->get_billing_country()
			&& 2 === strlen( WC()->customer->get_billing_country() )
		) {
			$params['buyer-country'] = WC()->customer->get_billing_country();
		}

		$disable_funding = $this->settings->has( 'disable_funding' )
			? $this->settings->get( 'disable_funding' )
			: array();

		if ( ! is_checkout() ) {
			$disable_funding[] = 'card';
		}

		$is_dcc_enabled = $this->settings->has( 'dcc_enabled' ) && $this->settings->get( 'dcc_enabled' );

		$available_gateways       = WC()->payment_gateways->get_available_payment_gateways();
		$is_separate_card_enabled = isset( $available_gateways[ CardButtonGateway::ID ] );

		if ( is_checkout() && ( $is_dcc_enabled || $is_separate_card_enabled ) ) {
			$key = array_search( 'card', $disable_funding, true );
			if ( false !== $key ) {
				unset( $disable_funding[ $key ] );
			}
		}

		if ( $this->is_free_trial_cart() ) {
			$all_sources = array_keys( $this->all_funding_sources );
			if ( $is_dcc_enabled || $is_separate_card_enabled ) {
				$all_sources = array_diff( $all_sources, array( 'card' ) );
			}
			$disable_funding = $all_sources;
		}

		if ( ! $this->settings_status->is_pay_later_button_enabled_for_context( $this->context() ) ) {
			$disable_funding[] = 'credit';
		}

		if ( count( $disable_funding ) > 0 ) {
			$params['disable-funding'] = implode( ',', array_unique( $disable_funding ) );
		}

		$enable_funding = array( 'venmo' );
		if ( $this->settings_status->is_pay_later_messaging_enabled_for_location( $this->context() ) || ! in_array( 'credit', $disable_funding, true ) ) {
			$enable_funding[] = 'paylater';
		}

		if ( $this->is_free_trial_cart() ) {
			$enable_funding = array();
		}

		if ( count( $enable_funding ) > 0 ) {
			$params['enable-funding'] = implode( ',', array_unique( $enable_funding ) );
		}

		$smart_button_url = add_query_arg( $params, 'https://www.paypal.com/sdk/js' );
		return $smart_button_url;
	}

	/**
	 * The attributes we need to load for the JS SDK.
	 *
	 * @return array
	 */
	private function attributes(): array {
		return array(
			'data-partner-attribution-id' => $this->bn_code_for_context( $this->context() ),
		);
	}

	/**
	 * What BN Code to use in a given context.
	 *
	 * @param string $context The context.
	 * @return string
	 */
	private function bn_code_for_context( string $context ): string {

		$codes = $this->bn_codes();
		return ( isset( $codes[ $context ] ) ) ? $codes[ $context ] : '';
	}

	/**
	 * BN Codes to use.
	 *
	 * @return array
	 */
	private function bn_codes(): array {

		return array(
			'checkout'  => 'Woo_PPCP',
			'cart'      => 'Woo_PPCP',
			'mini-cart' => 'Woo_PPCP',
			'product'   => 'Woo_PPCP',
		);
	}

	/**
	 * The JS SKD components we need to load.
	 *
	 * @return array
	 * @throws NotFoundException If a setting was not found.
	 */
	private function components(): array {
		$components = array();

		if ( $this->load_button_component() ) {
			$components[] = 'buttons';
			$components[] = 'funding-eligibility';
		}
		if (
			$this->messages_apply->for_country()
			&& ! $this->is_free_trial_cart()
		) {
			$components[] = 'messages';
		}
		if ( $this->dcc_is_enabled() ) {
			$components[] = 'hosted-fields';
		}
		return $components;
	}

	/**
	 * Determines whether the button component should be loaded.
	 *
	 * @return bool
	 * @throws NotFoundException If a setting has not been found.
	 */
	private function load_button_component() : bool {
		$load_buttons = false;
		if (
			$this->context() === 'checkout'
			&& $this->settings->has( 'button_enabled' )
			&& $this->settings->get( 'button_enabled' )
		) {
			$load_buttons = true;
		}
		if (
			$this->context() === 'product'
			&& (
				( $this->settings->has( 'button_product_enabled' ) && $this->settings->get( 'button_product_enabled' ) ) ||
				( $this->settings_status->is_pay_later_messaging_enabled_for_location( 'product' ) )
			)
		) {
			$load_buttons = true;
		}
		if (
			$this->settings->has( 'button_mini-cart_enabled' )
			&& $this->settings->get( 'button_mini-cart_enabled' )
		) {
			$load_buttons = true;
		}

		if (
			$this->context() === 'cart'
			&& (
				( $this->settings->has( 'button_cart_enabled' ) && $this->settings->get( 'button_cart_enabled' ) ) ||
				( $this->settings_status->is_pay_later_messaging_enabled_for_location( 'cart' ) )
			)
		) {
			$load_buttons = true;
		}

		if ( $this->context() === 'pay-now' ) {
			$load_buttons = true;
		}

		return $load_buttons;
	}

	/**
	 * The current context.
	 *
	 * @return string
	 */
	private function context(): string {
		$context = 'mini-cart';
		if ( is_product() || wc_post_content_has_shortcode( 'product_page' ) ) {
			$context = 'product';
		}
		if ( is_cart() ) {
			$context = 'cart';
		}
		if ( is_checkout() && ! $this->is_paypal_continuation() ) {
			$context = 'checkout';
		}
		if ( is_checkout_pay_page() ) {
			$context = 'pay-now';
		}
		return $context;
	}

	/**
	 * Checks if PayPal payment was already initiated (on the product or cart pages).
	 *
	 * @return bool
	 */
	private function is_paypal_continuation(): bool {
		$order = $this->session_handler->order();
		if ( ! $order ) {
			return false;
		}
		$source = $order->payment_source();
		if ( $source && $source->card() ) {
			return false; // Ignore for DCC.
		}
		if ( 'card' === $this->session_handler->funding_source() ) {
			return false; // Ignore for card buttons.
		}
		return true;
	}

	/**
	 * Whether DCC is enabled or not.
	 *
	 * @return bool
	 * @throws NotFoundException If a setting has not been found.
	 */
	private function dcc_is_enabled(): bool {
		if ( ! is_checkout() ) {
			return false;
		}
		if ( ! $this->dcc_applies->for_country_currency() ) {
			return false;
		}
		$keys = array(
			'client_id',
			'client_secret',
			'dcc_enabled',
		);
		foreach ( $keys as $key ) {
			if ( ! $this->settings->has( $key ) || ! $this->settings->get( $key ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Determines the style for a given indicator in a given context.
	 *
	 * @param string $style The style.
	 * @param string $context The context.
	 *
	 * @return string
	 * @throws NotFoundException When a setting hasn't been found.
	 */
	private function style_for_context( string $style, string $context ): string {
		$defaults = array(
			'layout'  => 'vertical',
			'size'    => 'responsive',
			'color'   => 'gold',
			'shape'   => 'pill',
			'label'   => 'paypal',
			'tagline' => true,
		);

		$value = isset( $defaults[ $style ] ) ?
			$defaults[ $style ] : '';
		$value = $this->settings->has( 'button_' . $style ) ?
			$this->settings->get( 'button_' . $style ) : $value;
		$value = $this->settings->has( 'button_' . $context . '_' . $style ) ?
			$this->settings->get( 'button_' . $context . '_' . $style ) : $value;

		if ( is_bool( $value ) ) {
			$value = $value ? 'true' : 'false';
		}
		return (string) $value;
	}

	/**
	 * Returns a value between 25 and 55.
	 *
	 * @param int $height The input value.
	 * @return int The normalized output value.
	 */
	private function normalize_height( int $height ): int {
		if ( $height < 25 ) {
			return 25;
		}
		if ( $height > 55 ) {
			return 55;
		}

		return $height;
	}

	/**
	 * Returns the action name that PayPal button will use for rendering on the checkout page.
	 *
	 * @return string Action name.
	 */
	private function checkout_button_renderer_hook(): string {
		/**
		 * The filter returning the action name that PayPal button will use for rendering on the checkout page.
		 */
		return (string) apply_filters( 'woocommerce_paypal_payments_checkout_button_renderer_hook', 'woocommerce_review_order_after_payment' );
	}

	/**
	 * Returns the action name that PayPal DCC button will use for rendering on the checkout page.
	 *
	 * @return string
	 */
	private function checkout_dcc_button_renderer_hook(): string {
		/**
		 * The filter returning the action name that PayPal DCC button will use for rendering on the checkout page.
		 */
		return (string) apply_filters( 'woocommerce_paypal_payments_checkout_dcc_renderer_hook', 'woocommerce_review_order_after_submit' );
	}

	/**
	 * Returns the action name that PayPal button and Pay Later message will use for rendering on the pay-order page.
	 *
	 * @return string
	 */
	private function pay_order_renderer_hook(): string {
		/**
		 * The filter returning the action name that PayPal button and Pay Later message will use for rendering on the pay-order page.
		 */
		return (string) apply_filters( 'woocommerce_paypal_payments_pay_order_dcc_renderer_hook', 'woocommerce_pay_order_after_submit' );
	}

	/**
	 * Returns action name that PayPal button will use for rendering next to Proceed to checkout button (normally displayed in cart).
	 *
	 * @return string
	 */
	private function proceed_to_checkout_button_renderer_hook(): string {
		/**
		 * The filter returning the action name that PayPal button will use for rendering next to Proceed to checkout button (normally displayed in cart).
		 */
		return (string) apply_filters(
			'woocommerce_paypal_payments_proceed_to_checkout_button_renderer_hook',
			'woocommerce_proceed_to_checkout'
		);
	}

	/**
	 * Returns the action name that PayPal button will use for rendering in the WC mini cart.
	 *
	 * @return string
	 */
	private function mini_cart_button_renderer_hook(): string {
		/**
		 * The filter returning the action name that PayPal button will use for rendering in the WC mini cart.
		 */
		return (string) apply_filters(
			'woocommerce_paypal_payments_mini_cart_button_renderer_hook',
			'woocommerce_widget_shopping_cart_after_buttons'
		);
	}

	/**
	 * Returns the action name that PayPal button and Pay Later message will use for rendering on the single product page.
	 *
	 * @return string
	 */
	private function single_product_renderer_hook(): string {
		/**
		 * The filter returning the action name that PayPal button and Pay Later message will use for rendering on the single product page.
		 */
		return (string) apply_filters( 'woocommerce_paypal_payments_single_product_renderer_hook', 'woocommerce_single_product_summary' );
	}

	/**
	 * Check if cart product price total is 0.
	 *
	 * @return bool true if is 0, otherwise false.
	 * @psalm-suppress RedundantConditionGivenDocblockType
	 */
	protected function is_cart_price_total_zero(): bool {
        // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		return WC()->cart && WC()->cart->get_total( 'numeric' ) == 0;
	}

	/**
	 * Checks if PayPal buttons/messages can be rendered for the given product.
	 *
	 * @param WC_Product $product The product.
	 *
	 * @return bool
	 */
	protected function product_supports_payment( WC_Product $product ): bool {
		/**
		 * The filter returning true if PayPal buttons/messages can be rendered for this product, or false otherwise.
		 */

		$in_stock = $product->is_in_stock();

		if ( $product->is_type( 'variable' ) ) {
			/**
			 * The method is defined in WC_Product_Variable class.
			 *
			 * @psalm-suppress UndefinedMethod
			 */
			$variations = $product->get_available_variations( 'objects' );
			$in_stock   = $this->has_in_stock_variation( $variations );
		}

		/**
		 * Allows to filter if PayPal buttons/messages can be rendered for the given product.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_product_supports_payment_request_button',
			! $product->is_type( array( 'external', 'grouped' ) ) && $in_stock,
			$product
		);
	}

	/**
	 * Retrieves all payment tokens for the user, via API or cached if already queried.
	 *
	 * @return PaymentToken[]
	 */
	private function get_payment_tokens(): array {
		if ( null === $this->payment_tokens ) {
			$this->payment_tokens = $this->payment_token_repository->all_for_user_id( get_current_user_id() );
		}

		return $this->payment_tokens;
	}

	/**
	 * Returns the vaulted PayPal email or empty string.
	 *
	 * @return string
	 */
	private function get_vaulted_paypal_email(): string {
		try {
			$tokens = $this->get_payment_tokens();

			foreach ( $tokens as $token ) {
				if ( isset( $token->source()->paypal ) ) {
					return $token->source()->paypal->payer->email_address;
				}
			}
		} catch ( Exception $exception ) {
			$this->logger->error( 'Failed to get PayPal vaulted email. ' . $exception->getMessage() );
		}
		return '';
	}

	/**
	 * Checks if variations contain any in stock variation.
	 *
	 * @param WC_Product_Variation[] $variations The list of variations.
	 * @return bool True if any in stock variation, false otherwise.
	 */
	protected function has_in_stock_variation( array $variations ): bool {
		foreach ( $variations as $variation ) {
			if ( $variation->is_in_stock() ) {
				return true;
			}
		}

		return false;
	}
}
