<?php
/**
 * Controls the endpoint for the incoming webhooks.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks;

use phpDocumentor\Reflection\Types\This;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Webhook;
use WooCommerce\PayPalCommerce\ApiClient\Entity\WebhookEvent;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\WebhookEventFactory;
use WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;

/**
 * Class IncomingWebhookEndpoint
 */
class IncomingWebhookEndpoint {

	const NAMESPACE = 'paypal/v1';
	const ROUTE     = 'incoming';

	/**
	 * The Webhook endpoint.
	 *
	 * @var WebhookEndpoint
	 */
	private $webhook_endpoint;

	/**
	 * Our registered webhook.
	 *
	 * @var Webhook|null
	 */
	private $webhook;

	/**
	 * The Request handlers.
	 *
	 * @var RequestHandler[]
	 */
	private $handlers;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Whether requests need to be verified.
	 *
	 * @var bool
	 */
	private $verify_request;

	/**
	 * The webhook event factory.
	 *
	 * @var WebhookEventFactory
	 */
	private $webhook_event_factory;

	/**
	 * The simulation handler.
	 *
	 * @var WebhookSimulation
	 */
	private $simulation;

	/**
	 * The last webhook info storage.
	 *
	 * @var WebhookInfoStorage
	 */
	private $last_webhook_storage;

	/**
	 * IncomingWebhookEndpoint constructor.
	 *
	 * @param WebhookEndpoint     $webhook_endpoint The webhook endpoint.
	 * @param Webhook|null        $webhook Our registered webhook.
	 * @param LoggerInterface     $logger The logger.
	 * @param bool                $verify_request Whether requests need to be verified or not.
	 * @param WebhookEventFactory $webhook_event_factory The webhook event factory.
	 * @param WebhookSimulation   $simulation The simulation handler.
	 * @param WebhookInfoStorage  $last_webhook_storage The last webhook info storage.
	 * @param RequestHandler      ...$handlers The handlers, which process a request in the end.
	 */
	public function __construct(
		WebhookEndpoint $webhook_endpoint,
		?Webhook $webhook,
		LoggerInterface $logger,
		bool $verify_request,
		WebhookEventFactory $webhook_event_factory,
		WebhookSimulation $simulation,
		WebhookInfoStorage $last_webhook_storage,
		RequestHandler ...$handlers
	) {

		$this->webhook_endpoint      = $webhook_endpoint;
		$this->webhook               = $webhook;
		$this->handlers              = $handlers;
		$this->logger                = $logger;
		$this->verify_request        = $verify_request;
		$this->webhook_event_factory = $webhook_event_factory;
		$this->last_webhook_storage  = $last_webhook_storage;
		$this->simulation            = $simulation;
	}

	/**
	 * Registers the endpoint.
	 *
	 * @return bool
	 */
	public function register(): bool {
		return (bool) register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => array(
					'POST',
				),
				'callback'            => array(
					$this,
					'handle_request',
				),
				'permission_callback' => array(
					$this,
					'verify_request',
				),
			)
		);
	}

	/**
	 * Verifies the current request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return bool
	 */
	public function verify_request( \WP_REST_Request $request ): bool {
		if ( ! $this->verify_request ) {
			return true;
		}

		if ( ! $this->webhook ) {
			$this->logger->error( 'Failed to retrieve stored webhook data.' );
			return false;
		}

		try {
			$event = $this->event_from_request( $request );

			if ( $this->simulation->is_simulation_event( $event ) ) {
				return true;
			}

			$result = $this->webhook_endpoint->verify_current_request_for_webhook( $this->webhook );
			if ( ! $result ) {
				$this->logger->error( 'Webhook verification failed.' );
			}
			return $result;
		} catch ( RuntimeException $exception ) {
			$this->logger->error( 'Webhook verification failed: ' . $exception->getMessage() );
			return false;
		}
	}

	/**
	 * Handles the request.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		$event = $this->event_from_request( $request );

		$this->last_webhook_storage->save( $event );

		if ( $this->simulation->is_simulation_event( $event ) ) {
			$this->logger->info( 'Received simulated webhook.' );
			$this->simulation->receive( $event );
			return rest_ensure_response(
				array(
					'success' => true,
				)
			);
		}

		foreach ( $this->handlers as $handler ) {
			if ( $handler->responsible_for_request( $request ) ) {
				$response = $handler->handle_request( $request );
				$this->logger->log(
					'info',
					sprintf(
						// translators: %s is the event type.
						__( 'Webhook has been handled by %s', 'woocommerce-paypal-payments' ),
						( $handler->event_types() ) ? current( $handler->event_types() ) : ''
					),
					array(
						'request'  => $request,
						'response' => $response,
					)
				);
				return $response;
			}
		}

		$message = sprintf(
			// translators: %s is the request type.
			__( 'Could not find handler for request type %s', 'woocommerce-paypal-payments' ),
			$request['event_type']
		);
		$this->logger->log(
			'warning',
			$message,
			array(
				'request' => $request,
			)
		);
		$response = array(
			'success' => false,
			'message' => $message,
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Returns the URL to the endpoint.
	 *
	 * @return string
	 */
	public function url(): string {
		$url = rest_url( self::NAMESPACE . '/' . self::ROUTE );

		$url = str_replace( 'http://', 'https://', $url );

		$ngrok_host = getenv( 'NGROK_HOST' );
		if ( $ngrok_host ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host ) {
				$url = str_replace( $host, $ngrok_host, $url );
			}
		}

		return $url;
	}

	/**
	 * Returns the event types, which are handled by the endpoint.
	 *
	 * @return string[]
	 */
	public function handled_event_types(): array {
		$event_types = array();
		foreach ( $this->handlers as $handler ) {
			$event_types = array_merge( $event_types, $handler->event_types() );
		}
		return array_unique( $event_types );
	}

	/**
	 * Creates WebhookEvent from request data.
	 *
	 * @param \WP_REST_Request $request The request with event data.
	 *
	 * @return WebhookEvent
	 * @throws RuntimeException When failed to create.
	 */
	private function event_from_request( \WP_REST_Request $request ): WebhookEvent {
		return $this->webhook_event_factory->from_array( $request->get_params() );
	}
}
