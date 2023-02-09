<?php
namespace AIOSEO\Plugin\Common\Sitemap;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles sitemap localization logic.
 *
 * @since 4.2.1
 */
class Localization {
	/**
	 * This is cached so we don't do the lookup each query.
	 *
	 * @since 4.0.0
	 *
	 * @var boolean
	 */
	private static $wpml = null;

	/**
	 * Class constructor.
	 *
	 * @since 4.2.1
	 */
	public function __construct() {
		if ( aioseo()->helpers->isWpmlActive() ) {
			self::$wpml = [
				'defaultLanguage' => apply_filters( 'wpml_default_language', null ),
				'activeLanguages' => apply_filters( 'wpml_active_languages', null )
			];

			add_filter( 'aioseo_sitemap_term', [ $this, 'localizeEntry' ], 10, 4 );
			add_filter( 'aioseo_sitemap_post', [ $this, 'localizeEntry' ], 10, 4 );
		}
	}

	/**
	 * Localize the entries if WPML (or others in the future) are active.
	 *
	 * @since 4.0.0
	 *
	 * @param  array  $entry       The entry.
	 * @param  int)   $entryId     The post/term ID.
	 * @param  string $objectName  The post type or taxonomy name.
	 * @param  string $objectType  Whether the entry is a post or term.
	 * @param  bool   $rss         Whether or not we are localizing for the RSS sitemap.
	 * @return array               The entry.
	 */
	public function localizeEntry( $entry, $entryId, $objectName, $objectType ) {
		$translationGroupId = apply_filters( 'wpml_element_trid', null, $entryId );
		$translations       = apply_filters( 'wpml_get_element_translations', null, $translationGroupId, $objectName );
		if ( empty( $translations ) ) {
			return $entry;
		}

		$entry['languages'] = [];
		foreach ( $translations as $translation ) {
			if ( empty( $translation->element_id ) || ! isset( self::$wpml['activeLanguages'][ $translation->language_code ] ) ) {
				continue;
			}

			if ( (int) $entryId === (int) $translation->element_id ) {
				$entry['language'] = $translation->language_code;
				continue;
			}

			$translatedObjectId = apply_filters( 'wpml_object_id', $entryId, $objectName, false, $translation->language_code );
			if (
				( 'post' === $objectType && $this->isExcludedPost( $translatedObjectId ) ) ||
				( 'term' === $objectType && $this->isExcludedTerm( $translatedObjectId ) )
			) {
				continue;
			}

			$permalink = get_permalink( $translatedObjectId );

			// Special treatment for the home page translations.
			if ( 'page' === get_option( 'show_on_front' ) && aioseo()->helpers->wpmlIsHomePage( $entryId ) ) {
				$permalink = aioseo()->helpers->wpmlHomeUrl( $translation->language_code );
			}

			$currentLanguage = ! empty( self::$wpml['activeLanguages'][ $translation->language_code ] ) ? self::$wpml['activeLanguages'][ $translation->language_code ] : null;
			$languageCode    = ! empty( $currentLanguage['tag'] ) ? $currentLanguage['tag'] : $translation->language_code;

			if ( $languageCode && $permalink ) {
				$entry['languages'][] = [
					'language' => $languageCode,
					'location' => $permalink
				];
			}
		}

		// Also include the main page as a translated variant, per Google's specifications, but only if we found at least one other language.
		if ( ! empty( $entry['languages'] ) ) {
			$entry['languages'][] = [
				'language' => $entry['language'],
				'location' => $entry['loc']
			];
		} else {
			unset( $entry['languages'] );
		}

		$entry = $this->validateSubentries( $entry );

		return $entry;
	}

	/**
	 * Validates the subentries with translated variants to ensure all required values are set.
	 *
	 * @since 4.2.3
	 *
	 * @param  array $entry The entry.
	 * @return array        The validated entry.
	 */
	private function validateSubentries( $entry ) {
		if ( ! isset( $entry['languages'] ) ) {
			return $entry;
		}

		foreach ( $entry['languages'] as $index => $subentry ) {
			if ( empty( $subentry['language'] ) || empty( $subentry['location'] ) ) {
				unset( $entry['languages'][ $index ] );
			}
		}

		return $entry;
	}

	/**
	 * Checks whether the given post should be excluded.
	 *
	 * @since 4.2.4
	 *
	 * @param  int  $postId The post ID.
	 * @return bool         Whether the post should be excluded.
	 */
	private function isExcludedPost( $postId ) {
		static $excludedPostIds = null;
		if ( null === $excludedPostIds ) {
			$excludedPostIds = explode( ', ', aioseo()->sitemap->helpers->excludedPosts() );
			$excludedPostIds = array_map( function ( $postId ) {
				return (int) $postId;
			}, $excludedPostIds );
		}

		if ( in_array( $postId, $excludedPostIds, true ) ) {
			return true;
		}

		// Let's also check if the post is published and not password-protected.
		$post = get_post( $postId );
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return true;
		}

		if ( ! empty( $post->post_password ) || 'publish' !== $post->post_status ) {
			return true;
		}

		// Now, we must also check for noindex.
		$metaData = aioseo()->meta->metaData->getMetaData( $post );
		if ( ! empty( $metaData->robots_noindex ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether the given term should be excluded.
	 *
	 * @since 4.2.4
	 *
	 * @param  int  $termId The term ID.
	 * @return bool         Whether the term should be excluded.
	 */
	private function isExcludedTerm( $termId ) {
		static $excludedTermIds = null;
		if ( null === $excludedTermIds ) {
			$excludedTermIds = explode( ', ', aioseo()->sitemap->helpers->excludedTerms() );
			$excludedTermIds = array_map( function ( $termId ) {
				return (int) $termId;
			}, $excludedTermIds );
		}

		if ( in_array( $termId, $excludedTermIds, true ) ) {
			return true;
		}

		// At least one post must be assigned to the term.
		$posts = aioseo()->core->db->start( 'term_relationships' )
			->select( 'object_id' )
			->where( 'term_taxonomy_id =', $termId )
			->limit( 1 )
			->run()
			->result();

		if ( empty( $posts ) ) {
			return true;
		}

		// Now, we must also check for noindex.
		$term = get_term( $termId );
		if ( ! is_a( $term, 'WP_Term' ) ) {
			return true;
		}

		$metaData = aioseo()->meta->metaData->getMetaData( $term );
		if ( ! empty( $metaData->robots_noindex ) ) {
			return true;
		}

		return false;
	}
}