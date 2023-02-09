<?php
/**
 * The onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Onboarding
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Onboarding;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Modular\Module\ModuleInterface;

return static function (): ModuleInterface {
	return new OnboardingModule();
};
