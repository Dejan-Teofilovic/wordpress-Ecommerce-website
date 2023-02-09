<?php
namespace AIOSEO\Plugin\Common\Sitemap;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses the current request and checks whether we need to serve a sitemap or a stylesheet.
 *
 * @since 4.2.1
 */
class RequestParser {
	/**
	 * The cleaned slug of the current request.
	 *
	 * @since 4.2.1
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Whether we've checked if the page needs to be redirected.
	 *
	 * @since 4.2.3
	 *
	 * @var bool
	 */
	protected $checkedForRedirects = false;

	/**
	 * CLass constructor.
	 *
	 * @since 4.2.1
	 */
	public function __construct() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'parse_request', [ $this, 'checkRequest' ] );
	}

	/**
	 * Checks whether we need to serve a sitemap or related stylesheet.
	 *
	 * @since 4.2.1
	 *
	 * @param  WP   $wp The main WordPress environment instance.
	 * @return void
	 */
	public function checkRequest( $wp ) {
		$this->slug = $wp->request
			? $this->cleanSlug( $wp->request )
			// We must fallback to the REQUEST URI in case the site uses plain permalinks.
			: $this->cleanSlug( $_SERVER['REQUEST_URI'] );

		// Check if we need to remove the trailing slash or redirect another sitemap URL like "wp-sitemap.xml".
		$this->maybeRedirect();

		$this->checkForXsl();

		if ( aioseo()->options->sitemap->general->enable ) {
			$this->checkForGeneralSitemap();
		}

		if ( aioseo()->options->sitemap->rss->enable ) {
			$this->checkForRssSitemap();
		}
	}

	/**
	 * Cleans the slug of the current request before we use it.
	 *
	 * @since 4.2.3
	 *
	 * @param  string $slug The slug.
	 * @return string       The cleaned slug.
	 */
	public function cleanSlug( $slug ) {
		$slug = strtolower( $slug );
		$slug = aioseo()->helpers->unleadingSlashIt( $slug );
		$slug = untrailingslashit( $slug );

		return $slug;
	}

	/**
	 * Checks whether the general XML sitemap needs to be served.
	 *
	 * @since 4.2.1
	 *
	 * @return void
	 */
	private function checkForGeneralSitemap() {
		$fileName       = aioseo()->sitemap->helpers->filename( 'general' );
		$indexesEnabled = aioseo()->options->sitemap->general->indexes;

		if ( ! $indexesEnabled ) {
			// If indexes are disabled, check for the root index.
			if ( preg_match( "/^{$fileName}\.xml(\.gz)?$/i", $this->slug, $match ) ) {
				$this->setContext( 'general', $fileName );
				aioseo()->sitemap->generate();
			}

			return;
		}

		// First, check for the root index.
		if ( preg_match( "/^{$fileName}\.xml(\.gz)?$/i", $this->slug, $match ) ) {
			$this->setContext( 'general', $fileName );
			aioseo()->sitemap->generate();

			return;
		}

		if (
			// Now, check for the other indexes.
			preg_match( "/^(?P<objectName>.+)-{$fileName}\.xml(\.gz)?$/i", $this->slug, $match ) ||
			preg_match( "/^(?P<objectName>.+)-{$fileName}(?P<pageNumber>\d+)\.xml(\.gz)?$/i", $this->slug, $match )
		) {
			$pageNumber = ! empty( $match['pageNumber'] ) ? $match['pageNumber'] : 0;
			$this->setContext( 'general', $fileName, $match['objectName'], $pageNumber );
			aioseo()->sitemap->generate();
		}
	}

	/**
	 * Checks whether the RSS sitemap needs to be served.
	 *
	 * @since 4.2.1
	 *
	 * @return void
	 */
	private function checkForRssSitemap() {
		if ( ! preg_match( '/^sitemap(\.latest)?\.rss$/i', $this->slug, $match ) ) {
			return;
		}

		$this->setContext( 'rss' );
		aioseo()->sitemap->generate();
	}

	/**
	 * Checks if we need to serve a stylesheet.
	 *
	 * @since 4.2.1
	 *
	 * @return void
	 */
	protected function checkForXsl() {
		// Trim off the URL params.
		$newSlug = preg_replace( '/\?.*$/', '', $this->slug );
		if ( preg_match( '/^default\.xsl$/i', $newSlug ) ) {
			aioseo()->sitemap->xsl->generate();
		}
	}

	/**
	 * Sets the context for the requested sitemap.
	 *
	 * @since 4.2.1
	 *
	 * @param  string     $type       The sitemap type (e.g. "general" or "rss").
	 * @param  string     $fileName   The sitemap filename.
	 * @param  string     $indexName  The index name ("root" or an object name like "post", "page", "post_tag", etc.).
	 * @param  int        $pageNumber The index number.
	 * @return void|never
	 */
	public function setContext( $type, $fileName = 'sitemap', $indexName = 'root', $pageNumber = 0 ) {
		$indexesEnabled = aioseo()->options->sitemap->{$type}->indexes;

		aioseo()->sitemap->type          = $type;
		aioseo()->sitemap->filename      = $fileName;
		aioseo()->sitemap->indexes       = $indexesEnabled;
		aioseo()->sitemap->indexName     = $indexName;
		aioseo()->sitemap->linksPerIndex = aioseo()->options->sitemap->{$type}->linksPerIndex <= 50000 ? aioseo()->options->sitemap->{$type}->linksPerIndex : 50000;
		aioseo()->sitemap->pageNumber    = $pageNumber >= 1 ? $pageNumber - 1 : 0;
		aioseo()->sitemap->offset        = aioseo()->sitemap->linksPerIndex * aioseo()->sitemap->pageNumber;
		aioseo()->sitemap->isStatic      = false;
	}

	/**
	 * Redirects or alters the current request if:
	 * 1. The request includes our deprecated "aiosp_sitemap_path" URL param.
	 * 2. The request is for one of our sitemaps, but has a trailing slash.
	 * 3. The request is for the first index of a type, but has a page number.
	 * 4. The request is for a sitemap from WordPress Core/other plugin.
	 *
	 * @since 4.2.1
	 */
	protected function maybeRedirect() {
		if ( $this->checkedForRedirects ) {
			return;
		}

		$this->checkedForRedirects = true;

		// The request includes our deprecated "aiosp_sitemap_path" URL param.
		if ( preg_match( '/^\/\?aiosp_sitemap_path=root/i', $_SERVER['REQUEST_URI'] ) ) {
			wp_safe_redirect( home_url( 'sitemap.xml' ) );
			exit;
		}

		// The request is for one of our sitemaps, but has a trailing slash.
		if ( preg_match( '/\/(.*sitemap[0-9]*?\.xml(\.gz)?|.*sitemap(\.latest)?\.rss)\/$/i', $_SERVER['REQUEST_URI'] ) ) {
			wp_safe_redirect( home_url() . untrailingslashit( $_SERVER['REQUEST_URI'] ) );
			exit;
		}

		// The request is for the first index of a type, but has a page number.
		if ( preg_match( '/.*sitemap(0|1){1}?\.xml(\.gz)?$/i', $_SERVER['REQUEST_URI'] ) ) {
			$pathWithoutNumber = preg_replace( '/(.*sitemap)(0|1){1}?(\.xml(\.gz)?)$/i', '$1$3', $_SERVER['REQUEST_URI'] );
			wp_safe_redirect( home_url() . $pathWithoutNumber );
			exit;
		}

		// The request is for a sitemap from WordPress Core/other plugin, but the general sitemap is enabled.
		if ( ! aioseo()->options->sitemap->general->enable ) {
			return;
		}

		$sitemapPatterns = [
			'general' => [
				'sitemap\.txt',
				'sitemaps\.xml',
				'sitemap-xml\.xml',
				'sitemap[0-9]+\.xml',
				'sitemap(|[-_\/])?index[0-9]*\.xml',
				'wp-sitemap\.xml',
			],
			'rss'     => [
				'rss[0-9]*\.xml',
			]
		];

		foreach ( aioseo()->addons->getLoadedAddons() as $addonName => $loadedAddon ) {
			if ( ! empty( $loadedAddon->helpers ) && method_exists( $loadedAddon->helpers, 'getOtherSitemapPatterns' ) ) {
				$sitemapPatterns[ $addonName ] = $loadedAddon->helpers->getOtherSitemapPatterns();
			}
		}

		foreach ( $sitemapPatterns as $type => $patterns ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match( "/^$pattern$/i", $this->slug ) ) {
					wp_safe_redirect( aioseo()->sitemap->helpers->getUrl( $type ) );
					exit;
				}
			}
		}
	}
}