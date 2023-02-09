<?php
/**
 * Plugin properties.
 *
 * @package  WooCommerce\PayPalCommerce
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce;

use Dhii\Package\Version\VersionInterface;
use WpOop\WordPress\Plugin\PluginInterface;

/**
 * Plugin properties.
 */
class Plugin implements PluginInterface {

	/**
	 * The plugin name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The plugin version.
	 *
	 * @var VersionInterface
	 */
	protected $version;

	/**
	 * The path to the plugin base directory.
	 *
	 * @var string
	 */
	protected $base_dir;

	/**
	 * The plugin base name.
	 *
	 * @var string
	 */
	protected $base_name;

	/**
	 * The plugin title.
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * The plugin description.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * The text domain of this plugin
	 *
	 * @var string
	 */
	protected $text_domain;

	/**
	 * The minimal version of PHP required by this plugin.
	 *
	 * @var VersionInterface
	 */
	protected $min_php_version;

	/**
	 * The minimal version of WP required by this plugin.
	 *
	 * @var VersionInterface
	 */
	protected $min_wp_version;

	/**
	 * Plugin constructor.
	 *
	 * @param string           $name The plugin name.
	 * @param VersionInterface $version The plugin version.
	 * @param string           $base_dir The path to the plugin base directory.
	 * @param string           $base_name The plugin base name.
	 * @param string           $title The plugin title.
	 * @param string           $description The plugin description.
	 * @param string           $text_domain The text domain of this plugin.
	 * @param VersionInterface $min_php_version The minimal version of PHP required by this plugin.
	 * @param VersionInterface $min_wp_version The minimal version of WP required by this plugin.
	 */
	public function __construct(
		string $name,
		VersionInterface $version,
		string $base_dir,
		string $base_name,
		string $title,
		string $description,
		string $text_domain,
		VersionInterface $min_php_version,
		VersionInterface $min_wp_version
	) {
		$this->name            = $name;
		$this->description     = $description;
		$this->version         = $version;
		$this->base_dir        = $base_dir;
		$this->base_name       = $base_name;
		$this->title           = $title;
		$this->text_domain     = $text_domain;
		$this->min_php_version = $min_php_version;
		$this->min_wp_version  = $min_wp_version;
	}

	/**
	 * The plugin name.
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * The plugin description.
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * The plugin version.
	 */
	public function getVersion(): VersionInterface {
		return $this->version;
	}

	/**
	 * The path to the plugin base directory.
	 */
	public function getBaseDir(): string {
		return $this->base_dir;
	}

	/**
	 * The plugin base name.
	 */
	public function getBaseName(): string {
		return $this->base_name;
	}

	/**
	 * The text domain of this plugin.
	 */
	public function getTextDomain(): string {
		return $this->text_domain;
	}

	/**
	 * The plugin title.
	 */
	public function getTitle(): string {
		return $this->title;
	}

	/**
	 * The minimal version of PHP required by this plugin.
	 */
	public function getMinPhpVersion(): VersionInterface {
		return $this->min_php_version;
	}

	/**
	 * The minimal version of WP required by this plugin.
	 */
	public function getMinWpVersion(): VersionInterface {
		return $this->min_wp_version;
	}
}
