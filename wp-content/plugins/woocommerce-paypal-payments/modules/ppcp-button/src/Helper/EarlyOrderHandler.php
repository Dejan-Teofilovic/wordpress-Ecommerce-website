<?php
/**
 * Handles the Early Order logic, when we need to create the WC_Order by ourselfs.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WooCommerce\PayPalCommerce\Webhooks\Handler\PrefixTrait;

/**
 * Class EarlyOrderHandler
 */
class EarlyOrderHandler {

	use PrefixTrait;

	/**
	 * The State.
	 *
	 * @var State
	 */
	private $state;

	/**
	 * The Order Processor.
	 *
	 * @var OrderProcessor
	 */
	private $order_processor;

	/**
	 * The Session Handler.
	 *
	 * @var SessionHandler
	 */
	private $session_handler;

	/**
	 * EarlyOrderHandler constructor.
	 *
	 * @param State          $state The State.
	 * @param OrderProcessor $order_processor The Order Processor.
	 * @param SessionHandler $session_handler The Session Handler.
	 * @param string         $prefix The Prefix.
	 */
	public function __construct(
		State $state,
		OrderProcessor $order_processor,
		SessionHandler $session_handler,
		string $prefix
	) {

		$this->state           = $state;
		$this->order_processor = $order_processor;
		$this->session_handler = $session_handler;
		$this->prefix          = $prefix;
	}

	/**
	 * Whether early orders should be created at all.
	 *
	 * @return bool
	 */
	public function should_create_early_order(): bool {
		return $this->state->current_state() === State::STATE_ONBOARDED;
	}

    //phpcs:disable WordPress.Security.NonceVerification.Recommended

	/**
	 * Tries to determine the current WC Order Id based on the PayPal order
	 * and the current order in session.
	 *
	 * @param int|null $value The initial value.
	 *
	 * @return int|null
	 */
	public function determine_wc_order_id( int $value = null ) {

		if ( ! isset( $_REQUEST['ppcp-resume-order'] ) ) {
			return $value;
		}

		$resume_order_id = (int) WC()->session->get( 'order_awaiting_payment' );

		$order = $this->session_handler->order();
		if ( ! $order ) {
			return $value;
		}

		$order_id = false;
		foreach ( $order->purchase_units() as $purchase_unit ) {
			if ( $purchase_unit->custom_id() === sanitize_text_field( wp_unslash( $_REQUEST['ppcp-resume-order'] ) ) ) {
				$order_id = (int) $this->sanitize_custom_id( $purchase_unit->custom_id() );
			}
		}
		if ( $order_id === $resume_order_id ) {
			$value = $order_id;
		}
		return $value;
	}
    //phpcs:enable WordPress.Security.NonceVerification.Recommended

	/**
	 * Registers the necessary checkout actions for a given order.
	 *
	 * @param Order $order The PayPal order.
	 *
	 * @return bool
	 */
	public function register_for_order( Order $order ): bool {

		$success = (bool) add_action(
			'woocommerce_checkout_order_processed',
			function ( $order_id ) use ( $order ) {
				try {
					$order = $this->configure_session_and_order( (int) $order_id, $order );
					wp_send_json_success( $order->to_array() );
				} catch ( \RuntimeException $error ) {
					wp_send_json_error(
						array(
							'name'    => is_a( $error, PayPalApiException::class ) ?
								$error->name() : '',
							'message' => $error->getMessage(),
							'code'    => $error->getCode(),
							'details' => is_a( $error, PayPalApiException::class ) ?
								$error->details() : array(),

						)
					);
				}
			}
		);

		return $success;
	}

	/**
	 * Configures the session, so we can pick up the order, once we pass through the checkout.
	 *
	 * @param int   $order_id The WooCommerce order id.
	 * @param Order $order The PayPal order.
	 *
	 * @return Order
	 */
	public function configure_session_and_order( int $order_id, Order $order ): Order {

		/**
		 * Set the order id in our session in order for
		 * us to resume this order in checkout.
		 */
		WC()->session->set( 'order_awaiting_payment', $order_id );

		$wc_order = wc_get_order( $order_id );
		$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, $order->id() );
		$wc_order->update_meta_data( PayPalGateway::INTENT_META_KEY, $order->intent() );
		$wc_order->save_meta_data();

		/**
		 * Patch Order so we have the \WC_Order id added.
		 */
		return $this->order_processor->patch_order( $wc_order, $order );
	}
}
