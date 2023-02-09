<?php
/**
 * Manage the Seller status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Throwable;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusProduct;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

/**
 * Class DccProductStatus
 */
class DCCProductStatus {

	const DCC_STATUS_CACHE_KEY = 'dcc_status_cache';

	/**
	 * The Cache.
	 *
	 * @var Cache
	 */
	protected $cache;

	/**
	 * Caches the status for the current load.
	 *
	 * @var bool|null
	 */
	private $current_status_cache;
	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * The partners endpoint.
	 *
	 * @var PartnersEndpoint
	 */
	private $partners_endpoint;

	/**
	 * The dcc applies helper.
	 *
	 * @var DccApplies
	 */
	protected $dcc_applies;

	/**
	 * DccProductStatus constructor.
	 *
	 * @param Settings         $settings The Settings.
	 * @param PartnersEndpoint $partners_endpoint The Partner Endpoint.
	 * @param Cache            $cache The cache.
	 * @param DccApplies       $dcc_applies The dcc applies helper.
	 */
	public function __construct(
		Settings $settings,
		PartnersEndpoint $partners_endpoint,
		Cache $cache,
		DccApplies $dcc_applies
	) {
		$this->settings          = $settings;
		$this->partners_endpoint = $partners_endpoint;
		$this->cache             = $cache;
		$this->dcc_applies       = $dcc_applies;
	}

	/**
	 * Whether the active/subscribed products support DCC.
	 *
	 * @return bool
	 */
	public function dcc_is_active() : bool {
		if ( $this->cache->has( self::DCC_STATUS_CACHE_KEY ) ) {
			return (bool) $this->cache->get( self::DCC_STATUS_CACHE_KEY );
		}

		if ( is_bool( $this->current_status_cache ) ) {
			return $this->current_status_cache;
		}

		if ( $this->settings->has( 'products_dcc_enabled' ) && $this->settings->get( 'products_dcc_enabled' ) ) {
			$this->current_status_cache = true;
			return true;
		}

		try {
			$seller_status = $this->partners_endpoint->seller_status();
		} catch ( Throwable $error ) {
			$this->current_status_cache = false;
			return false;
		}

		foreach ( $seller_status->products() as $product ) {
			if ( ! in_array(
				$product->vetting_status(),
				array(
					SellerStatusProduct::VETTING_STATUS_APPROVED,
					SellerStatusProduct::VETTING_STATUS_SUBSCRIBED,
				),
				true
			)
			) {
				continue;
			}

			if ( in_array( 'CUSTOM_CARD_PROCESSING', $product->capabilities(), true ) ) {
				$this->settings->set( 'products_dcc_enabled', true );
				$this->settings->persist();
				$this->current_status_cache = true;
				$this->cache->set( self::DCC_STATUS_CACHE_KEY, true, 3 * MONTH_IN_SECONDS );
				return true;
			}
		}

		$expiration = 3 * MONTH_IN_SECONDS;
		if ( $this->dcc_applies->for_country_currency() ) {
			$expiration = 3 * HOUR_IN_SECONDS;
		}
		$this->cache->set( self::DCC_STATUS_CACHE_KEY, false, $expiration );

		$this->current_status_cache = false;
		return false;
	}
}
