<?php
namespace AIOSEO\Plugin\Common\Sitemap;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains general helper methods specific to the sitemap.
 *
 * @since 4.0.0
 */
class Helpers {
	/**
	 * Used to track the performance of the sitemap.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 *            $memory The peak memory that is required to generate the sitemap.
	 *            $time   The time that is required to generate the sitemap.
	 */
	private $performance;

	/**
	 * Returns the sitemap filename.
	 *
	 * @since 4.0.0
	 *
	 * @param  string  $type The sitemap type. We pass it in when we need to get the filename for a specific sitemap outside of the context of the sitemap.
	 * @return string        The sitemap filename.
	 */
	public function filename( $type = '' ) {
		if ( ! $type ) {
			$type = isset( aioseo()->sitemap->type ) ? aioseo()->sitemap->type : 'general';
		}

		return apply_filters( 'aioseo_sitemap_filename', aioseo()->options->sitemap->$type->filename );
	}

	/**
	 * Returns the last modified post.
	 *
	 * @since 4.0.0
	 *
	 * @param  array $additionalArgs Any additional arguments for the post query.
	 * @return mixed                 WP_Post object or false.
	 */
	public function lastModifiedPost( $additionalArgs = [] ) {
		$args = [
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby '       => 'modified',
			'order'          => 'ASC'
		];

		if ( $additionalArgs ) {
			foreach ( $additionalArgs as $k => $v ) {
				$args[ $k ] = $v;
			}
		}

		$query = ( new \WP_Query( $args ) );
		if ( ! $query->post_count ) {
			return false;
		}

		return $query->posts[0];
	}

	/**
	 * Returns the timestamp of the last modified post.
	 *
	 * @since 4.0.0
	 *
	 * @param  array  $postTypes      The relevant post types.
	 * @param  array  $additionalArgs Any additional arguments for the post query.
	 * @return string                 Formatted date string (ISO 8601).
	 */
	public function lastModifiedPostTime( $postTypes = [ 'post', 'page' ], $additionalArgs = [] ) {
		if ( is_array( $postTypes ) ) {
			$postTypes = implode( "', '", $postTypes );
		}

		$query = aioseo()->core->db
			->start( aioseo()->core->db->db->posts . ' as p', true )
			->select( 'MAX(`p`.`post_modified_gmt`) as last_modified' )
			->where( 'p.post_status', 'publish' )
			->whereRaw( "( `p`.`post_type` IN ( '$postTypes' ) )" );

		if ( isset( $additionalArgs['author'] ) ) {
			$query->where( 'p.post_author', $additionalArgs['author'] );
		}

		$lastModified = $query->run()
			->result();

		return ! empty( $lastModified[0]->last_modified )
			? aioseo()->helpers->dateTimeToIso8601( $lastModified[0]->last_modified )
			: '';
	}

	/**
	 * Returns the timestamp of the last modified additional page.
	 *
	 * @since 4.0.0
	 *
	 * @return string Formatted date string (ISO 8601).
	 */
	public function lastModifiedAdditionalPagesTime() {
		$pages = [];
		if ( 'posts' === get_option( 'show_on_front' ) || ! in_array( 'page', $this->includedPostTypes(), true ) ) {
			$frontPageId = (int) get_option( 'page_on_front' );
			$post        = aioseo()->helpers->getPost( $frontPageId );
			$pages[]     = $post ? strtotime( $post->post_modified_gmt ) : strtotime( aioseo()->sitemap->helpers->lastModifiedPostTime() );
		}

		foreach ( aioseo()->options->sitemap->general->additionalPages->pages as $page ) {
			$additionalPage = json_decode( $page );
			if ( empty( $additionalPage->url ) ) {
				continue;
			}

			$pages[] = strtotime( $additionalPage->lastModified );
		}

		if ( empty( $pages ) ) {
			$additionalPages = apply_filters( 'aioseo_sitemap_additional_pages', [] );
			if ( empty( $additionalPages ) ) {
				return false;
			}

			$lastModified = 0;
			$timestamp    = time();
			foreach ( $additionalPages as $page ) {
				if ( empty( $page['lastmod'] ) ) {
					continue;
				}
				$timestamp = strtotime( $page['lastmod'] );
				if ( ! $timestamp ) {
					continue;
				}
				if ( $lastModified < $timestamp ) {
					$lastModified = $timestamp;
				}
			}

			return 0 !== $lastModified ? aioseo()->helpers->dateTimeToIso8601( gmdate( 'Y-m-d H:i:s', $timestamp ) ) : false;
		}

		return aioseo()->helpers->dateTimeToIso8601( gmdate( 'Y-m-d H:i:s', max( $pages ) ) );
	}

	/**
	 * Formats a given image URL for usage in the sitemap.
	 *
	 * @since 4.0.0
	 *
	 * @param  string $url The URL.
	 * @return string      The formatted URL.
	 */
	public function formatUrl( $url ) {
		// Remove URL parameters.
		$url = strtok( $url, '?' );
		$url = htmlspecialchars( $url, ENT_COMPAT, 'UTF-8', false );

		return aioseo()->helpers->makeUrlAbsolute( $url );
	}

	/**
	 * Logs the performance of the sitemap for debugging purposes.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function logPerformance() {
		// Start logging the performance.
		if ( ! $this->performance ) {
			$this->performance['time']   = microtime( true );
			$this->performance['memory'] = ( memory_get_peak_usage( true ) / 1024 ) / 1024;

			return;
		}

		// Stop logging the performance.
		$time      = microtime( true ) - $this->performance['time'];
		$memory    = $this->performance['memory'];
		$type      = aioseo()->sitemap->type;
		$indexName = aioseo()->sitemap->indexName;
		error_log( wp_json_encode( "$indexName index of $type sitemap generated in $time seconds using a maximum of $memory mb of memory." ) );
	}

	/**
	 * Returns the post types that should be included in the sitemap.
	 *
	 * @since 4.0.0
	 *
	 * @param  boolean $hasArchivesOnly Whether or not to only include post types which have archives.
	 * @return array   $postTypes       The included post types.
	 */
	public function includedPostTypes( $hasArchivesOnly = false ) {
		$postTypes = [];
		if ( aioseo()->options->sitemap->{aioseo()->sitemap->type}->postTypes->all ) {
			$postTypes = aioseo()->helpers->getPublicPostTypes( true, $hasArchivesOnly );
		} else {
			$postTypes = aioseo()->options->sitemap->{aioseo()->sitemap->type}->postTypes->included;
		}

		if ( ! $postTypes ) {
			return $postTypes;
		}

		$options         = aioseo()->options->noConflict();
		$dynamicOptions  = aioseo()->dynamicOptions->noConflict();
		$publicPostTypes = aioseo()->helpers->getPublicPostTypes( true, $hasArchivesOnly );
		foreach ( $postTypes as $postType ) {
			// Check if post type is no longer registered.
			if ( ! in_array( $postType, $publicPostTypes, true ) || ! $dynamicOptions->searchAppearance->postTypes->has( $postType ) ) {
				$postTypes = aioseo()->helpers->unsetValue( $postTypes, $postType );
				continue;
			}

			// Check if post type isn't noindexed.
			if ( aioseo()->helpers->isPostTypeNoindexed( $postType ) ) {
				if ( ! $this->checkForIndexedPost( $postType ) ) {
					$postTypes = aioseo()->helpers->unsetValue( $postTypes, $postType );
					continue;
				}
			}

			if (
				$dynamicOptions->searchAppearance->postTypes->$postType->advanced->robotsMeta->default &&
				! $options->searchAppearance->advanced->globalRobotsMeta->default &&
				$options->searchAppearance->advanced->globalRobotsMeta->noindex
			) {
				if ( ! $this->checkForIndexedPost( $postType ) ) {
					$postTypes = aioseo()->helpers->unsetValue( $postTypes, $postType );
					continue;
				}
			}
		}

		return $postTypes;
	}

	/**
	 * Checks if any post is explicitly indexed when the post type is noindexed.
	 *
	 * @since 4.0.0
	 *
	 * @param  string $postType The post type to check for.
	 * @return bool             Whether or not there is an indexed post.
	 */
	private function checkForIndexedPost( $postType ) {
		$posts = aioseo()->core->db
			->start( aioseo()->core->db->db->posts . ' as p', true )
			->select( 'p.ID' )
			->join( 'aioseo_posts as ap', '`ap`.`post_id` = `p`.`ID`' )
			->where( 'p.post_status', 'attachment' === $postType ? 'inherit' : 'publish' )
			->where( 'p.post_type', $postType )
			->whereRaw( '( `ap`.`robots_default` = 0 AND `ap`.`robots_noindex` = 0 )' )
			->limit( 1 )
			->run()
			->result();

		if ( $posts && count( $posts ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns the taxonomies that should be included in the sitemap.
	 *
	 * @since 4.0.0
	 *
	 * @return array The included taxonomies.
	 */
	public function includedTaxonomies() {
		$taxonomies = [];
		if ( aioseo()->options->sitemap->{aioseo()->sitemap->type}->taxonomies->all ) {
			$taxonomies = get_taxonomies();
		} else {
			$taxonomies = aioseo()->options->sitemap->{aioseo()->sitemap->type}->taxonomies->included;
		}

		if ( ! $taxonomies ) {
			return [];
		}

		$options          = aioseo()->options->noConflict();
		$dynamicOptions   = aioseo()->dynamicOptions->noConflict();
		$publicTaxonomies = aioseo()->helpers->getPublicTaxonomies( true );
		foreach ( $taxonomies as $taxonomy ) {
			// Check if taxonomy is no longer registered.
			if ( ! in_array( $taxonomy, $publicTaxonomies, true ) || ! $dynamicOptions->searchAppearance->taxonomies->has( $taxonomy ) ) {
				$taxonomies = aioseo()->helpers->unsetValue( $taxonomies, $taxonomy );
				continue;
			}

			// Check if taxonomy isn't noindexed.
			if ( aioseo()->helpers->isTaxonomyNoindexed( $taxonomy ) ) {
				$taxonomies = aioseo()->helpers->unsetValue( $taxonomies, $taxonomy );
				continue;
			}

			if (
				$dynamicOptions->searchAppearance->taxonomies->$taxonomy->advanced->robotsMeta->default &&
				! $options->searchAppearance->advanced->globalRobotsMeta->default &&
				$options->searchAppearance->advanced->globalRobotsMeta->noindex
			) {
				$taxonomies = aioseo()->helpers->unsetValue( $taxonomies, $taxonomy );
				continue;
			}
		}

		return $taxonomies;
	}

	/**
	 * Splits sitemap entries into chuncks based on the max. amount of URLs per index.
	 *
	 * @since 4.0.0
	 *
	 * @param  array $entries The sitemap entries.
	 * @return array          The chunked sitemap entries.
	 */
	public function chunkEntries( $entries ) {
		return array_chunk( $entries, aioseo()->sitemap->linksPerIndex, true );
	}

	/**
	 * Formats the last Modified date of a user-submitted additional page as an ISO 8601 date.
	 *
	 * @since 4.0.0
	 *
	 * @param  object $page The additional page object.
	 * @return string       The formatted datetime.
	 */
	public function lastModifiedAdditionalPage( $page ) {
		return gmdate( 'c', strtotime( $page->lastModified ) );
	}

	/**
	 * Returns a list of excluded post IDs.
	 *
	 * @since 4.0.0
	 *
	 * @return string The excluded IDs.
	 */
	public function excludedPosts() {
		return $this->excludedObjects( 'excludePosts' );
	}

	/**
	 * Returns a list of excluded term IDs.
	 *
	 * @since 4.0.0
	 *
	 * @return string The excluded IDs.
	 */
	public function excludedTerms() {
		return $this->excludedObjects( 'excludeTerms' );
	}

	/**
	 * Returns a list of excluded IDs for a given option as a comma separated string.
	 *
	 * Helper method for excludedPosts() and excludedTerms().
	 *
	 * @since 4.0.0
	 *
	 * @param  string $option The option name.
	 * @return string         The excluded IDs.
	 */
	private function excludedObjects( $option ) {
		$type = aioseo()->sitemap->type;
		// The RSS Sitemap needs to exclude whatever is excluded in the general sitemap.
		if ( 'rss' === $type ) {
			$type = 'general';
		}

		$hasFilter = has_filter( 'aioseo_sitemap_' . aioseo()->helpers->toSnakeCase( $option ) );
		$advanced  = aioseo()->options->sitemap->$type->advancedSettings->enable;
		$excluded  = aioseo()->options->sitemap->$type->advancedSettings->$option;
		if (
			( ! $advanced || empty( $excluded ) ) &&
			! $hasFilter
		) {
			return '';
		}

		$ids = [];
		foreach ( $excluded as $object ) {
			$object = json_decode( $object );
			if ( is_int( $object->value ) ) {
				$ids[] = $object->value;
			}
		}

		if ( 'excludePosts' === $option ) {
			$ids = apply_filters( 'aioseo_sitemap_exclude_posts', $ids, $type );
		}

		if ( 'excludeTerms' === $option ) {
			$ids = apply_filters( 'aioseo_sitemap_exclude_terms', $ids, $type );
		}

		return count( $ids ) ? esc_sql( implode( ', ', $ids ) ) : '';
	}

	/**
	 * Returns the URLs of all active sitemaps.
	 *
	 * @since 4.0.0
	 *
	 * @return array $urls The sitemap URLs.
	 */
	public function getSitemapUrls() {
		static $urls = [];
		if ( $urls ) {
			return $urls;
		}

		foreach ( aioseo()->addons->getLoadedAddons() as $loadedAddon ) {
			if ( ! empty( $loadedAddon->helpers ) && method_exists( $loadedAddon->helpers, 'getSitemapUrls' ) ) {
				$urls = array_merge( $urls, $loadedAddon->helpers->getSitemapUrls() );
			}
		}

		if ( aioseo()->options->sitemap->general->enable ) {
			$urls[] = $this->getUrl( 'general' );
		}
		if ( aioseo()->options->sitemap->rss->enable ) {
			$urls[] = $this->getUrl( 'rss' );
		}

		foreach ( $urls as &$url ) {
			$url = 'Sitemap: ' . $url;
		}

		return $urls;
	}

	/**
	 * Returns the URL of the given sitemap type.
	 *
	 * @since 4.1.5
	 *
	 * @param  string $type The sitemap type.
	 * @return string       The sitemap URL.
	 */
	public function getUrl( $type ) {
		$url = home_url( 'sitemap.xml' );

		if ( 'rss' === $type ) {
			$url = home_url( 'sitemap.rss' );
		}

		if ( 'general' === $type ) {
			// Check if user has a custom filename from the V3 migration.
			$filename = $this->filename( 'general' ) ?: 'sitemap';
			$url      = home_url( $filename . '.xml' );
		}

		$addon = aioseo()->addons->getLoadedAddon( $type );
		if ( ! empty( $addon->helpers ) && method_exists( $addon->helpers, 'getUrl' ) ) {
			$url = $addon->helpers->getUrl();
		}

		return $url;
	}

	/**
	 * Returns if images should be excluded from the sitemap.
	 *
	 * @since 4.2.2
	 *
	 * @return bool
	 */
	public function excludeImages() {
		$shouldExclude = aioseo()->options->sitemap->general->advancedSettings->enable && aioseo()->options->sitemap->general->advancedSettings->excludeImages;

		return apply_filters( 'aioseo_sitemap_exclude_images', $shouldExclude );
	}
}