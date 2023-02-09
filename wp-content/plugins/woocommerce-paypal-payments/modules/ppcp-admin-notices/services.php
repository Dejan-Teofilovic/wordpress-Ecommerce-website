<?php
/**
 * The services of the admin notice module.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AdminNotices;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Renderer\Renderer;
use WooCommerce\PayPalCommerce\AdminNotices\Renderer\RendererInterface;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\AdminNotices\Repository\RepositoryInterface;

return array(
	'admin-notices.renderer'   => static function ( ContainerInterface $container ): RendererInterface {

		$repository = $container->get( 'admin-notices.repository' );
		return new Renderer( $repository );
	},
	'admin-notices.repository' => static function ( ContainerInterface $container ): RepositoryInterface {

		return new Repository();
	},
);
