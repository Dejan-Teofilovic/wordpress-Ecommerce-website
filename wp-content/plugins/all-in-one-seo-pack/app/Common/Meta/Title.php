<?php
namespace AIOSEO\Plugin\Common\Meta;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the title.
 *
 * @since 4.0.0
 */
class Title {
	/**
	 * Helpers class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var Helpers
	 */
	public $helpers = null;

	/**
	 * Class constructor.
	 *
	* @since 4.1.2
	 */
	public function __construct() {
		$this->helpers = new Helpers( 'title' );
	}

	/**
	 * Returns the filtered page title.
	 *
	 * Acts as a helper for getTitle() because we need to encode the title before sending it back to the filter.
	 *
	 * @since 4.0.0
	 *
	 * @return string The page title.
	 */
	public function filterPageTitle( $wpTitle = '' ) {
		$title = $this->getTitle();

		return ! empty( $title ) ? aioseo()->helpers->encodeOutputHtml( $title ) : $wpTitle;
	}

	/**
	 * Returns the homepage title.
	 *
	 * @since 4.0.0
	 *
	 * @return string The homepage title.
	 */
	public function getHomePageTitle() {
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$title = $this->getPostTitle( (int) get_option( 'page_on_front' ) );

			return $title ? $title : aioseo()->helpers->decodeHtmlEntities( get_bloginfo( 'name' ) );
		}

		$title = $this->helpers->prepare( aioseo()->options->searchAppearance->global->siteTitle );

		return $title ? $title : aioseo()->helpers->decodeHtmlEntities( get_bloginfo( 'name' ) );
	}

	/**
	 * Returns the title for the current page.
	 *
	 * @since 4.0.0
	 *
	 * @param  WP_Post $post    The post object (optional).
	 * @param  boolean $default Whether we want the default value, not the post one.
	 * @return string           The page title.
	 */
	public function getTitle( $post = null, $default = false ) {
		if ( is_home() ) {
			return $this->getHomePageTitle();
		}

		if ( $post || is_singular() || aioseo()->helpers->isStaticPage() ) {
			return $this->getPostTitle( $post, $default );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = $post ? $post : get_queried_object();

			return $this->getTermTitle( $term, $default );
		}

		if ( is_author() ) {
			return $this->helpers->prepare( aioseo()->options->searchAppearance->archives->author->title );
		}

		if ( is_date() ) {
			return $this->helpers->prepare( aioseo()->options->searchAppearance->archives->date->title );
		}

		if ( is_search() ) {
			return $this->helpers->prepare( aioseo()->options->searchAppearance->archives->search->title );
		}

		if ( is_post_type_archive() ) {
			$postType = get_queried_object();
			if ( is_a( $postType, 'WP_Post_Type' ) ) {
				$dynamicOptions = aioseo()->dynamicOptions->noConflict();
				if ( $dynamicOptions->searchAppearance->archives->has( $postType->name ) ) {
					return $this->helpers->prepare( aioseo()->dynamicOptions->searchAppearance->archives->{ $postType->name }->title );
				}
			}
		}

		return '';
	}

	/**
	 * Returns the post title.
	 *
	 * @since 4.0.0
	 *
	 * @param  WP_Post|int $post    The post object or ID.
	 * @param  boolean     $default Whether we want the default value, not the post one.
	 * @return string               The post title.
	 */
	public function getPostTitle( $post, $default = false ) {
		$post = $post && is_object( $post ) ? $post : aioseo()->helpers->getPost( $post );
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return '';
		}

		static $posts = [];
		if ( isset( $posts[ $post->ID ] ) ) {
			return $posts[ $post->ID ];
		}

		$title    = '';
		$metaData = aioseo()->meta->metaData->getMetaData( $post );
		if ( ! empty( $metaData->title ) && ! $default ) {
			$title = $this->helpers->prepare( $metaData->title, $post->ID );
		}

		if ( ! $title ) {
			$title = $this->helpers->prepare( $this->getPostTypeTitle( $post->post_type ), $post->ID, $default );
		}

		// If this post is the static home page and we have no title, let's reset to the site name.
		if ( empty( $title ) && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID ) {
			$title = aioseo()->helpers->decodeHtmlEntities( get_bloginfo( 'name' ) );
		}

		$posts[ $post->ID ] = $title;

		return $posts[ $post->ID ];
	}

	/**
	 * Retrieve the default title for the post type.
	 *
	 * @since 4.0.6
	 *
	 * @param  string $postType The post type.
	 * @return string           The title.
	 */
	public function getPostTypeTitle( $postType ) {
		static $postTypeTitle = [];
		if ( isset( $postTypeTitle[ $postType ] ) ) {
			return $postTypeTitle[ $postType ];
		}

		if ( aioseo()->dynamicOptions->searchAppearance->postTypes->has( $postType ) ) {
			$title = aioseo()->dynamicOptions->searchAppearance->postTypes->{$postType}->title;
		}

		$postTypeTitle[ $postType ] = empty( $title ) ? '' : $title;

		return $postTypeTitle[ $postType ];
	}

	/**
	 * Returns the term title.
	 *
	 * @since 4.0.6
	 *
	 * @param  WP_Term $term    The term object.
	 * @param  boolean $default Whether we want the default value, not the post one.
	 * @return string           The term title.
	 */
	public function getTermTitle( $term, $default = false ) {
		if ( ! is_a( $term, 'WP_Term' ) ) {
			return '';
		}

		static $terms = [];
		if ( isset( $terms[ $term->term_id ] ) ) {
			return $terms[ $term->term_id ];
		}

		$title          = '';
		$dynamicOptions = aioseo()->dynamicOptions->noConflict();
		if ( ! $title && $dynamicOptions->searchAppearance->taxonomies->has( $term->taxonomy ) ) {
			$newTitle = aioseo()->dynamicOptions->searchAppearance->taxonomies->{$term->taxonomy}->title;
			$newTitle = preg_replace( '/#taxonomy_title/', aioseo()->helpers->escapeRegexReplacement( $term->name ), $newTitle );
			$title    = $this->helpers->prepare( $newTitle, false, $default );
		}

		$terms[ $term->term_id ] = $title;

		return $terms[ $term->term_id ];
	}
}