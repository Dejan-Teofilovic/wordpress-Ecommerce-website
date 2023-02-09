<?php
namespace AIOSEO\Plugin\Common\Traits;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Options trait.
 *
 * @since 4.1.9
 */
trait Assets {
	/**
	 * Whether we should load dev scripts.
	 *
	 * @since 4.1.9
	 *
	 * @var boolean|null
	 */
	private $shouldLoadDevScripts = null;

	/**
	 * Holds the location of the manifest file.
	 *
	 * @since 4.1.9
	 *
	 * @var string
	 */
	private $manifestFile;

	/**
	 * Holds the location of the asset manifest file.
	 *
	 * @since 4.1.9
	 *
	 * @var string
	 */
	private $assetManifestFile;

	/**
	 * True if we are in a dev environment. This mirrors the global isDev.
	 *
	 * @since 4.1.9
	 *
	 * @var bool
	 */
	private $isDev = false;

	/**
	 * Asset handles that should load as regular JS and not as modern JS module.
	 *
	 * @since 4.1.9
	 *
	 * @var array An array of handles.
	 */
	private $noModuleTag = [];

	/**
	 * Core class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var \AIOSEO\Plugin\Common\Core\Core
	 */
	protected $core = null;

	/**
	 * The LocalBusiness addon version.
	 *
	 * @since 4.2.7
	 *
	 * @var string
	 */
	protected $version = '';

	/**
	 * The development site domain.
	 *
	 * @since 4.2.7
	 *
	 * @var string
	 */
	protected $domain = '';

	/**
	 * The development server port.
	 *
	 * @since 4.2.7
	 *
	 * @var int
	 */
	protected $port = 0;

	/**
	 * The asset to load.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset        The asset to load.
	 * @param  array  $dependencies An array of dependencies.
	 * @param  mixed  $data         Any data to be localized.
	 * @param  string $objectName   The object name to use when localizing.
	 * @return void
	 */
	public function load( $asset, $dependencies = [], $data = null, $objectName = 'aioseo' ) {
		$this->jsPreloadImports( $asset );
		$this->loadCss( $asset );
		$this->enqueueJs( $asset, $dependencies, $data, $objectName );
	}

	/**
	 * Filter the script loader tag if this is our script.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $tag    The tag that is going to be output.
	 * @param  string $handle The handle for the script.
	 * @return string         The modified tag.
	 */
	public function scriptLoaderTag( $tag, $handle, $src ) {
		if ( $this->skipModuleTag( $handle ) ) {
			return $tag;
		}

		$tag = str_replace( $src, $this->normalizeAssetsHost( $src ), $tag );

		// Remove the type and re-add it as module.
		$tag = preg_replace( '/type=[\'"].*?[\'"]/', '', $tag );
		$tag = preg_replace( '/<script/', '<script type="module"', $tag );

		return $tag;
	}

	/**
	 * Preload JS imports.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to load imports for.
	 * @return void
	 */
	private function jsPreloadImports( $asset ) {
		$res = '';
		foreach ( $this->importsUrls( $asset ) as $url ) {
			$res .= '<link rel="modulepreload" href="' . $url . "\">\n";
		}

		if ( ! empty( $res ) ) {
			add_action( 'admin_head', function () use ( &$res ) {
				echo $res; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} );
			add_action( 'wp_head', function () use ( &$res ) {
				echo $res; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} );
		}
	}

	/**
	 * Loads CSS for an asset from the manifest file.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The script to load CSS for.
	 * @return void
	 */
	public function loadCss( $asset ) {
		if ( $this->shouldLoadDev() ) {
			return;
		}

		foreach ( $this->getCssUrls( $asset ) as $file => $url ) {
			wp_enqueue_style( $this->cssHandle( $file ), $url, [], $this->version );
		}
	}

	/**
	 * Register a CSS asset.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset        The script to load CSS for.
	 * @param  array  $dependencies An array of dependencies.
	 * @param  string $devPath      The file's dev path.
	 * @return void
	 */
	public function registerCss( $asset, $dependencies = [], $devPath = '' ) {
		$handle = $this->cssHandle( $asset );
		if ( wp_style_is( $handle, 'registered' ) ) {
			return;
		}

		$devPath = $devPath ?: $asset;

		$url = $this->shouldLoadDev()
			? $this->getDevUrl() . ltrim( $devPath, '/' )
			: $this->assetUrl( $asset );

		if ( ! $url ) {
			return;
		}

		wp_register_style( $handle, $url, $dependencies, $this->version );
	}

	/**
	 * Enqueue css.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset        The css to load.
	 * @param  string $devPath      The file's dev path.
	 * @param  array  $dependencies An array of dependencies.
	 * @return void
	 */
	public function enqueueCss( $asset, $dependencies = [], $devPath = '' ) {
		$this->registerCss( $asset, $dependencies, $devPath );

		$handle = $this->cssHandle( $asset );
		if ( wp_style_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( $handle );
	}

	/**
	 * Register the JS to enqueue.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset        The script to load.
	 * @param  array  $dependencies An array of dependencies.
	 * @param  mixed  $data         Any data to be localized.
	 * @param  string $objectName   The object name to use when localizing.
	 * @return void
	 */
	public function registerJs( $asset, $dependencies = [], $data = null, $objectName = 'aioseo' ) {
		$handle = $this->jsHandle( $asset );
		if ( wp_script_is( $handle, 'registered' ) ) {
			return;
		}

		$url = $this->shouldLoadDev()
			? $this->getDevUrl() . ltrim( $asset, '/' )
			: $this->jsUrl( $asset );

		if ( ! $url ) {
			return;
		}

		wp_register_script( $handle, $url, $dependencies, $this->version, true );

		if ( empty( $data ) ) {
			return;
		}

		wp_localize_script(
			$handle,
			$objectName,
			$data
		);
	}

	/**
	 * Register the JS to enqueue.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset        The script to load.
	 * @param  array  $dependencies An array of dependencies.
	 * @param  mixed  $data         Any data to be localized.
	 * @param  string $objectName   The object name to use when localizing.
	 * @return void
	 */
	public function enqueueJs( $asset, $dependencies = [], $data = null, $objectName = 'aioseo' ) {
		$this->registerJs( $asset, $dependencies, $data, $objectName );

		$handle = $this->jsHandle( $asset );
		if ( wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script( $handle );
	}

	/**
	 * Return the dev URL.
	 *
	 * @since 4.1.9
	 *
	 * @return string The dev URL.
	 */
	private function getDevUrl() {
		return 'https://' . $this->domain . ':' . $this->port . '/';
	}

	/**
	 * Get the asset URL.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to find the URL for.
	 * @return string        The URL for the asset.
	 */
	private function assetUrl( $asset ) {
		$assetManifest = $this->getAssetManifestItem( $asset );

		return ! empty( $assetManifest )
			? $this->basePath() . $assetManifest
			: $this->basePath() . ltrim( $asset, '/' );
	}

	/**
	 * Get the JS URL.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to find the URL for.
	 * @return string        The URL for the asset.
	 */
	public function jsUrl( $asset ) {
		$manifestAsset = $this->getManifestItem( $asset );

		return ! empty( $manifestAsset['file'] )
			? $this->basePath() . $manifestAsset['file']
			: $this->basePath() . ltrim( $asset, '/' );
	}

	/**
	 * Get an item from the manifest.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to find.
	 * @return string        Manifest object.
	 */
	private function getManifestItem( $asset ) {
		$manifest = $this->getManifest();

		$asset = ltrim( $asset, '/' );

		return isset( $manifest[ $asset ] ) ? $manifest[ $asset ] : null;
	}

	/**
	 * Get the CSS asset handle.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to find the handle for.
	 * @return string        The asset handle.
	 */
	public function cssHandle( $asset ) {
		return "{$this->scriptHandle}/css/$asset";
	}

	/**
	 * Get the JS asset handle.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to find the handle for.
	 * @return string        The asset handle.
	 */
	public function jsHandle( $asset = '' ) {
		return "{$this->scriptHandle}/js/$asset";
	}

	/**
	 * Get the manifest to load assets from.
	 *
	 * @since 4.1.9
	 *
	 * @return array An array of files.
	 */
	private function getManifest() {
		static $file = null;
		if ( $file ) {
			return $file;
		}

		// Required for local business 1.2.5.
		if ( preg_match( '/\.json$/', $this->manifestFile ) ) {
			$content = $this->core->fs->getContents( $this->manifestFile );
			$file    = json_decode( $content, true );

			return $file;
		}

		$manifestJson = ''; // This is set in the view.
		require $this->manifestFile;

		$file = json_decode( $manifestJson, true );

		return $file;
	}

	/**
	 * Get the manifest to load entry assets from.
	 *
	 * @since 4.1.9
	 *
	 * @return array An array of files.
	 */
	private function getAssetManifest() {
		static $file = null;
		if ( $file ) {
			return $file;
		}

		// Required for local business 1.2.5.
		if ( preg_match( '/\.json$/', $this->assetManifestFile ) ) {
			$content = $this->core->fs->getContents( $this->assetManifestFile );
			$file    = json_decode( $content, true );

			return $file;
		}

		$manifestJson = ''; // This is set in the view.
		require $this->assetManifestFile;

		$file = json_decode( $manifestJson, true );

		return $file;
	}

	/**
	 * Get an item from the asset manifest.
	 *
	 * @since 4.1.9
	 *
	 * @param  string      $item An item to retrieve.
	 * @return string|null       The asset item.
	 */
	private function getAssetManifestItem( $item ) {
		$assetManifest = $this->getAssetManifest();

		return ! empty( $assetManifest[ $item ] ) ? $assetManifest[ $item ] : null;
	}

	/**
	 * Get an asset's array of URLs to import.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to find imports for.
	 * @return array         An array of imports.
	 */
	private function importsUrls( $asset ) {
		$urls          = [];
		$manifestAsset = $this->getManifestItem( $asset );
		if ( ! empty( $manifestAsset['imports'] ) ) {
			foreach ( $manifestAsset['imports'] as $import ) {
				$importAsset = $this->getManifestItem( $import );
				if ( ! empty( $importAsset['file'] ) ) {
					$urls[] = $this->getPublicUrlBase() . $importAsset['file'];

					// Load the import's CSS if any.
					$this->loadCss( $import );
				}
			}
		}

		return $urls;
	}

	/**
	 * Returns an asset's CSS urls.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $asset The asset to find CSS URLs for.
	 * @return array         An array of CSS URLs to load.
	 */
	private function getCssUrls( $asset ) {
		$urls          = [];
		$manifestAsset = $this->getManifestItem( $asset );

		if ( ! empty( $manifestAsset['css'] ) ) {
			foreach ( $manifestAsset['css'] as $file ) {
				$urls[ $file ] = $this->getPublicUrlBase() . $file;
			}
		}

		return $urls;
	}

	/**
	 * Check if we should load the dev watcher scripts.
	 *
	 * @since 4.1.9
	 *
	 * @return boolean True if we should load the dev watcher scripts.
	 */
	private function shouldLoadDev() {
		if ( null !== $this->shouldLoadDevScripts ) {
			return $this->shouldLoadDevScripts;
		}

		if (
			! $this->isDev ||
			(
				defined( 'AIOSEO_LOAD_DEV_SCRIPTS' ) &&
				false === AIOSEO_LOAD_DEV_SCRIPTS
			)
		) {
			$this->shouldLoadDevScripts = false;

			return $this->shouldLoadDevScripts;
		}

		if ( ! $this->domain && ! $this->port ) {
			$this->shouldLoadDevScripts = false;

			return $this->shouldLoadDevScripts;
		}

		set_error_handler( function() {} );
		$connection = fsockopen( $this->domain, $this->port ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fsockopen
		restore_error_handler();

		if ( ! $connection ) {
			$this->shouldLoadDevScripts = false;

			return $this->shouldLoadDevScripts;
		}

		$this->shouldLoadDevScripts = true;

		return $this->shouldLoadDevScripts;
	}

	/**
	 * Get the path for the assets.
	 *
	 * @since 4.1.9
	 *
	 * @param  bool   $maybeDev Whether to try and load dev scripts.
	 * @return string           The path for the assets.
	 */
	public function getAssetsPath( $maybeDev = true ) {
		return $maybeDev && $this->shouldLoadDev()
			? $this->getDevUrl()
			: $this->basePath();
	}

	/**
	 * Finds out if a handle should be loaded as regular JS and not as modern JS module.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $handle The script handle.
	 * @return bool           Should the module tag be skipped.
	 */
	public function skipModuleTag( $handle ) {
		if ( ! aioseo()->helpers->stringContains( $handle, $this->jsHandle( '' ) ) ) {
			return true;
		}

		foreach ( $this->noModuleTag as $tag ) {
			if ( aioseo()->helpers->stringContains( $handle, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize the assets host. Some sites manually set the WP_PLUGINS_URL
	 * and if that domain has www. and the site_url does not, then it will fail to load
	 * our assets. This doesn't fix the issue 100% because it will still fail on
	 * sub-domains that don't have the proper CORS headers. Those sites will need
	 * manual fixes.
	 *
	 * 4.1.10
	 *
	 * @param  string $path The path to normalize.
	 * @return string       The normalized path.
	 */
	public function normalizeAssetsHost( $path ) {
		static $paths = [];
		if ( isset( $paths[ $path ] ) ) {
			return apply_filters( 'aioseo_normalize_assets_host', $paths[ $path ] );
		}

		// We need to verify the domain on the $path attribute matches
		// what's in site_url() for our assets or they won't load.
		$siteUrl        = site_url();
		$siteUrlEscaped = aioseo()->helpers->escapeRegex( $siteUrl );
		if ( preg_match( "/^$siteUrlEscaped/i", $path ) ) {
			$paths[ $path ] = $path;

			return apply_filters( 'aioseo_normalize_assets_host', $paths[ $path ] );
		}

		// We now know that the path doesn't contain the site_url().
		$newPath        = $path;
		$siteUrlParsed  = wp_parse_url( $siteUrl );
		$host           = aioseo()->helpers->escapeRegex( str_replace( 'www.', '', $siteUrlParsed['host'] ) );
		$scheme         = aioseo()->helpers->escapeRegex( $siteUrlParsed['scheme'] );

		$siteUrlHasWww = preg_match( "/^{$scheme}:\/\/www\.$host/", $siteUrl );
		$pathHasWww    = preg_match( "/^{$scheme}:\/\/www\.$host/", $path );

		// Check if the path contains www.
		if ( $pathHasWww && ! $siteUrlHasWww ) {
			// If the path contains www., we want to strip it out.
			$newPath = preg_replace( "/^({$scheme}:\/\/)(www\.)($host)/", '$1$3', $path );
		}

		// Check if the site_url contains www.
		if ( $siteUrlHasWww && ! $pathHasWww ) {
			// If the site_url contains www., we want to add it in to the path.
			$newPath = preg_replace( "/^({$scheme}:\/\/)($host)/", '$1www.$2', $path );
		}

		$paths[ $path ] = $newPath;

		return apply_filters( 'aioseo_normalize_assets_host', $paths[ $path ] );
	}
}