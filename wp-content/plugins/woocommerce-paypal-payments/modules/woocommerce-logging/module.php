<?php
/**
 * The logging module.
 *
 * @package WooCommerce\WooCommerce\Logging
 */

declare(strict_types=1);

namespace WooCommerce\WooCommerce\Logging;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return function (): ModuleInterface {
	return new WooCommerceLoggingModule();
};
