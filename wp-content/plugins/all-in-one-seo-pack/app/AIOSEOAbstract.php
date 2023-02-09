<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class holding the class properties of our main AIOSEO class.
 *
 * @since 4.2.7
 */
abstract class AIOSEOAbstract {
	/**
	 * Core class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Core\Core
	 */
	public $core = null;

	/**
	 * Helpers class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var Object
	 */
	public $helpers = null;

	/**
	 * InternalNetworkOptions class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Options\InternalNetworkOptions|\AIOSEO\Plugin\Pro\Options\InternalNetworkOptions
	 */
	public $internalNetworkOptions = null;

	/**
	 * InternalOptions class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Options\InternalOptions|\AIOSEO\Plugin\Pro\Options\InternalOptions
	 */
	public $internalOptions = null;

	/**
	 * PreUpdates class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Main\PreUpdates|\AIOSEO\Plugin\Pro\Main\PreUpdates
	 */
	public $preUpdates = null;

	/**
	 * Db class instance.
	 * This prop is set for backwards compatibility.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Core\Db
	 */
	public $db = null;

	/**
	 * Transients class instance.
	 * This prop is set for backwards compatibility.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Core\Cache
	 */
	public $transients = null;

	/**
	 * OptionsCache class instance.
	 * This prop is set for backwards compatibility.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Options\Cache
	 */
	public $optionsCache = null;

	/**
	 * PostSettings class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Admin\PostSettings|\AIOSEO\Plugin\Pro\Admin\PostSettings
	 */
	public $postSettings = null;

	/**
	 * Standalone class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Admin\PostSettings|\AIOSEO\Plugin\Pro\Admin\PostSettings
	 */
	public $standalone = null;

	/**
	 * ThirdParty class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\ThirdParty\ThirdParty
	 */
	public $thirdParty = null;

	/**
	 * Tags class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\Addons|\AIOSEO\Plugin\Pro\Utils\Addons
	 */
	public $tags = null;

	/**
	 * Addons class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\Blocks
	 */
	public $blocks = null;

	/**
	 * BadBotBlocker class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Tools\BadBotBlocker
	 */
	public $badBotBlocker = null;

	/**
	 * Breadcrumbs class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Breadcrumbs\Breadcrumbs|\AIOSEO\Plugin\Pro\Breadcrumbs\Breadcrumbs
	 */
	public $breadcrumbs = null;

	/**
	 * DynamicBackup class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Options\DynamicBackup|\AIOSEO\Plugin\Pro\Options\DynamicBackup
	 */
	public $dynamicBackup = null;

	/**
	 * NetworkOptions class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Options\NetworkOptions|\AIOSEO\Plugin\Pro\Options\NetworkOptions
	 */
	public $networkOptions = null;

	/**
	 * Backup class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\Backup
	 */
	public $backup = null;

	/**
	 * Access class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\Access|\AIOSEO\Plugin\Pro\Utils\Access
	 */
	public $access = null;

	/**
	 * NetworkLicense class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var null|\AIOSEO\Plugin\Pro\Admin\NetworkLicense
	 */
	public $networkLicense = null;

	/**
	 * License class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var null|\AIOSEO\Plugin\Pro\Admin\License
	 */
	public $license = null;

	/**
	 * Updates class isntance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Main\Updates|\AIOSEO\Plugin\Pro\Main\Updates
	 */
	public $updates = null;

	/**
	 * Meta class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Meta\Meta|\AIOSEO\Plugin\Pro\Meta\Meta
	 */
	public $meta = null;

	/**
	 * Social class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Social\Social|\AIOSEO\Plugin\Pro\Social\Social
	 */
	public $social = null;

	/**
	 * RobotsTxt class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Tools\RobotsTxt
	 */
	public $robotsTxt = null;

	/**
	 * Htaccess class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Tools\Htaccess
	 */
	public $htaccess = null;

	/**
	 * Term class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var null|\AIOSEO\Plugin\Pro\Admin\Term
	 */
	public $term = null;

	/**
	 * Notices class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Admin\Notices\Notices|\AIOSEO\Plugin\Pro\Admin\Notices\Notices
	 */
	public $notices = null;

	/**
	 * WpNotices class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Admin\Notices\WpNotices
	 */
	public $wpNotices = null;

	/**
	 * Admin class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Admin\Admin|\AIOSEO\Plugin\Pro\Admin\Admin
	 */
	public $admin = null;

	/**
	 * NetworkAdmin class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Admin\NetworkAdmin|\AIOSEO\Plugin\Pro\Admin\NetworkAdmin
	 */
	public $networkAdmin = null;

	/**
	 * Activate class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Main|Activate|\AIOSEO\Plugin\Pro\Main\Activate
	 */
	public $activate = null;

	/**
	 * ConflictingPlugins class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Admin\ConflictingPlugins|\AIOSEO\Plugin\Pro\Admin\ConflictingPlugins
	 */
	public $conflictingPlugins = null;

	/**
	 * Migration class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Migration\Migration|\AIOSEO\Plugin\Pro\Migration\Migration
	 */
	public $migration = null;

	/**
	 * ImportExport class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\ImportExport\ImportExport
	 */
	public $importExport = null;

	/**
	 * Sitemap class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Sitemap\Sitemap|\AIOSEO\Plugin\Pro\Sitemap\Sitemap
	 */
	public $sitemap = null;

	/**
	 * HtmlSitemap class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Sitemap\Html\Sitemap
	 */
	public $htmlSitemap = null;

	/**
	 * CategoryBase class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var null|\AIOSEO\Plugin\Pro\Main\CategoryBase
	 */
	public $categoryBase = null;

	/**
	 * SlugMonitor class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Admin\SlugMonitor
	 */
	public $slugMonitor = null;

	/**
	 * Schema class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Schema\Schema|\AIOSEO\Plugin\Pro\Schema\Schema
	 */
	public $schema = null;

	/**
	 * Rss class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Rss
	 */
	public $rss = null;

	/**
	 * Main class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Main\Main|\AIOSEO\Plugin\Pro\Main\Main
	 */
	public $main = null;

	/**
	 * Head class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Main\Head|\AIOSEO\Plugin\Pro\Main\Head
	 */
	public $head = null;

	/**
	 * Dashboard class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Admin\Dashboard|\AIOSEO\Plugin\Pro\Admin\Dashboard
	 */
	public $dashboard = null;

	/**
	 * API class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Api\Api|\AIOSEO\Plugin\Pro\Api\Api
	 */
	public $api = null;

	/**
	 * Help class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Help\Help
	 */
	public $help = null;

	/**
	 * Settings class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\VueSettings
	 */
	public $settings = null;

	/**
	 * Cache class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Core\Cache
	 */
	public $cache = null;

	/**
	 * CachePrune class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Core\CachePrune
	 */
	public $cachePrune = null;

	/**
	 * Addons class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\Addons|\AIOSEO\Plugin\Pro\Utils\Addons
	 */
	public $addons = null;

	/**
	 * Options class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Options\Options|\AIOSEO\Plugin\Pro\Options\Options
	 */
	public $options = null;

	/**
	 * DynamicOptions class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Options\DynamicOptions|\AIOSEO\Plugin\Pro\Options\DynamicOptions
	 */
	public $dynamicOptions = null;

	/**
	 * Usage class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Admin\Usage|\Admin\Plugin\Pro\Admin\Usage
	 */
	public $usage = null;

	/**
	 * SiteHealth class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Admin\SiteHealth|\AIOSEO\Plugin\Pro\Admin\SiteHealth
	 */
	public $siteHealth = null;

	/**
	 * AutoUpdates class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var null|\AIOSEO\Plugin\Admin\AutoUpdates
	 */
	public $autoUpdates = null;

	/**
	 * Templates class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\Templates|\AIOSEO\Plugin\Pro\Utils\Templates
	 */
	public $templates = null;

	/**
	 * Filters class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Lite\Main\Filters|\AIOSEO\Plugin\Pro\Main\Filters
	 */
	public $filters = null;

	/**
	 * ActionScheduler class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Utils\ActionScheduler
	 */
	public $actionScheduler = null;
}