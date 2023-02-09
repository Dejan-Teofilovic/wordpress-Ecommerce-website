<?php
namespace AIOSEO\Plugin\Common\Models;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Post DB Model.
 *
 * @since 4.0.0
 */
class Post extends Model {
	/**
	 * The name of the table in the database, without the prefix.
	 *
	 * @since 4.0.0
	 *
	 * @var string
	 */
	protected $table = 'aioseo_posts';

	/**
	 * Fields that should be json encoded on save and decoded on get.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 */
	protected $jsonFields = [
		// 'keywords',
		// 'keyphrases',
		// 'page_analysis',
		'schema',
		// 'schema_type_options',
		'images',
		'videos',
		'options'
	];

	/**
	 * Fields that should be hidden when serialized.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 */
	protected $hidden = [ 'id' ];

	/**
	 * Fields that should be boolean values.
	 *
	 * @since 4.0.13
	 *
	 * @var array
	 */
	protected $booleanFields = [
		'twitter_use_og',
		'pillar_content',
		'robots_default',
		'robots_noindex',
		'robots_noarchive',
		'robots_nosnippet',
		'robots_nofollow',
		'robots_noimageindex',
		'robots_noodp',
		'robots_notranslate',
		'limit_modified_date',
	];

	/**
	 * Returns a Post with a given ID.
	 *
	 * @since 4.0.0
	 *
	 * @param  int  $postId The post ID.
	 * @return Post         The Post object.
	 */
	public static function getPost( $postId ) {
		// This is needed to prevent an error when upgrading from 4.1.8 to 4.1.9.
		// WordPress deletes the attachment .zip file for the new plugin version after installing it, which triggers the "delete_post" hook.
		// In-between the 4.1.8 to 4.1.9 update, the new Core class does not exist yet, causing the PHP error.
		// TODO: Delete this in a future release.
		$post = new self;
		if ( ! property_exists( aioseo(), 'core' ) ) {
			return $post;
		}

		$post = aioseo()->core->db->start( 'aioseo_posts' )
			->where( 'post_id', $postId )
			->run()
			->model( 'AIOSEO\\Plugin\\Common\\Models\\Post' );

		if ( ! $post->exists() ) {
			$post->post_id = $postId;
			$post          = self::setDynamicDefaults( $post, $postId );
		} else {
			$post = self::migrateRemovedQaSchema( $post );
			$post = self::runDynamicMigrations( $post );
		}

		// Set options object.
		$post = self::setOptionsDefaults( $post );

		return apply_filters( 'aioseo_get_post', $post );
	}

	/**
	 * Sets the dynamic defaults on the post object if it doesn't exist in the DB yet.
	 *
	 * @since 4.1.4
	 *
	 * @param  Post $post   The Post object.
	 * @param  int  $postId The post ID.
	 * @return Post         The modified Post object.
	 */
	private static function setDynamicDefaults( $post, $postId ) {
		if ( 'page' === get_post_type( $postId ) ) { // This check cannot be deleted and is required to prevent errors after WordPress cleans up the attachment it creates when a plugin is updated.
			$isWooCommerceCheckoutPage = aioseo()->helpers->isWooCommerceCheckoutPage( $postId );
			if (
				$isWooCommerceCheckoutPage ||
				aioseo()->helpers->isWooCommerceCartPage( $postId ) ||
				aioseo()->helpers->isWooCommerceAccountPage( $postId )
			) {
				$post->robots_default = false;
				$post->robots_noindex = true;
			}
		}

		if ( aioseo()->helpers->isStaticHomePage( $postId ) ) {
			$post->og_object_type = 'website';
		}

		$post->twitter_use_og = aioseo()->options->social->twitter->general->useOgData;

		if ( property_exists( $post, 'schema' ) && null === $post->schema ) {
			$post->schema = self::getDefaultSchemaOptions();
		}

		return $post;
	}

	/**
	 * Migrates removed QAPage schema on-the-fly when the post is loaded.
	 *
	 * @since 4.1.8
	 *
	 * @param  Post $aioseoPost The post object.
	 * @return Post             The modified post object.
	 */
	private static function migrateRemovedQaSchema( $aioseoPost ) {
		if ( ! $aioseoPost->schema_type || 'webpage' !== strtolower( $aioseoPost->schema_type ) ) {
			return $aioseoPost;
		}

		$schemaTypeOptions = json_decode( $aioseoPost->schema_type_options );
		if ( 'qapage' !== strtolower( $schemaTypeOptions->webPage->webPageType ) ) {
			return $aioseoPost;
		}

		$schemaTypeOptions->webPage->webPageType = 'WebPage';
		$aioseoPost->schema_type_options         = wp_json_encode( $schemaTypeOptions );
		$aioseoPost->save();

		return $aioseoPost;
	}

	/**
	 * Runs dynamic migrations whenever the post object is loaded.
	 *
	 * @since 4.1.7
	 *
	 * @param  Post $post The Post object.
	 * @return Post       The modified Post object.
	 */
	private static function runDynamicMigrations( $post ) {
		$post = self::migrateImageTypes( $post );
		$post = self::runDynamicSchemaMigration( $post );

		return $post;
	}


	/**
	 * Migrates the post's schema data when it is loaded.
	 *
	 * @since 4.2.5
	 *
	 * @param  Post $post The Post object.
	 * @return Post       The modified Post object.
	 */
	private static function runDynamicSchemaMigration( $post ) {
		if ( ! property_exists( $post, 'schema' ) ) {
			return $post;
		}

		if ( null === $post->schema ) {
			$post = aioseo()->updates->migratePostSchemaHelper( $post );
		}

		if ( ! property_exists( $post->schema, 'default' ) ) {
			$post->schema = self::getDefaultSchemaOptions( $post->schema );
		}

		return $post;
	}

	/**
	 * Migrates the post's image types when it is loaded.
	 *
	 * @since 4.2.5
	 *
	 * @param  Post $post The Post object.
	 * @return Post       The modified Post object.
	 */
	private static function migrateImageTypes( $post ) {
		$pageBuilder = aioseo()->helpers->getPostPageBuilderName( $post->post_id );
		if ( ! $pageBuilder ) {
			return $post;
		}

		$deprecatedImageSources = 'seedprod' === strtolower( $pageBuilder )
			? [ 'auto', 'custom', 'featured' ]
			: [ 'auto' ];

		if ( ! empty( $post->og_image_type ) && in_array( $post->og_image_type, $deprecatedImageSources, true ) ) {
			$post->og_image_type = 'default';
		}

		if ( ! empty( $post->twitter_image_type ) && in_array( $post->twitter_image_type, $deprecatedImageSources, true ) ) {
			$post->twitter_image_type = 'default';
		}

		return $post;
	}

	/**
	 * Saves the Post object.
	 *
	 * @since 4.0.3
	 *
	 * @param  int              $postId The Post ID.
	 * @param  array            $data   The post data to save.
	 * @return bool|void|string         Whether the post data was saved or a DB error message.
	 */
	public static function savePost( $postId, $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		$thePost = self::getPost( $postId );
		// Before setting the data, we check if the title/description are the same as the defaults and clear them if so.
		$data = self::checkForDefaultFormat( $postId, $thePost, $data );

		$thePost = apply_filters( 'aioseo_save_post', $thePost );
		$thePost = self::sanitizeAndSetDefaults( $postId, $thePost, $data );

		// Update traditional post meta so that it can be used by multilingual plugins.
		self::updatePostMeta( $postId, $data );

		$thePost->save();
		$thePost->reset();

		$lastError = aioseo()->core->db->lastError();
		if ( ! empty( $lastError ) ) {
			return $lastError;
		}
	}

	/**
	 * Checks if the title/description is the same as their default format in Search Appearance and nulls it if this is the case.
	 * Doing this ensures that updates to the default title/description format also propogate to the post.
	 *
	 * @since 4.1.5
	 *
	 * @param  int   $postId  The post ID.
	 * @param  Post  $thePost The Post object.
	 * @param  array $data    The data.
	 * @return array          The data.
	 */
	private static function checkForDefaultFormat( $postId, $thePost, $data ) {
		$data['title']       = trim( $data['title'] );
		$data['description'] = trim( $data['description'] );

		$post                     = aioseo()->helpers->getPost( $postId );
		$defaultTitleFormat       = trim( aioseo()->meta->title->getPostTypeTitle( $post->post_type ) );
		$defaultDescriptionFormat = trim( aioseo()->meta->description->getPostTypeDescription( $post->post_type ) );
		if ( ! empty( $data['title'] ) && $data['title'] === $defaultTitleFormat ) {
			$data['title'] = null;
		}

		if ( ! empty( $data['description'] ) && $data['description'] === $defaultDescriptionFormat ) {
			$data['description'] = null;
		}

		return $data;
	}

	/**
	 * Sanitize the keyphrases posted data.
	 *
	 * @since 4.2.8
	 *
	 * @param  array $data An array containing the keyphrases field data.
	 * @return array       The sanitized data.
	 */
	private static function sanitizeKeyphrases( $data ) {
		if (
			! empty( $data['focus']['analysis'] ) &&
			is_array( $data['focus']['analysis'] )
		) {
			foreach ( $data['focus']['analysis'] as &$analysis ) {
				// Remove unnecessary 'title' and 'description'.
				unset( $analysis['title'] );
				unset( $analysis['description'] );
			}
		}

		if (
			! empty( $data['additional'] ) &&
			is_array( $data['additional'] )
		) {
			foreach ( $data['additional'] as &$additional ) {
				if (
					! empty( $additional['analysis'] ) &&
					is_array( $additional['analysis'] )
				) {
					foreach ( $additional['analysis'] as &$additionalAnalysis ) {
						// Remove unnecessary 'title' and 'description'.
						unset( $additionalAnalysis['title'] );
						unset( $additionalAnalysis['description'] );
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Sanitize the page_analysis posted data.
	 *
	 * @since 4.2.7
	 *
	 * @param  array $data An array containing the page_analysis field data.
	 * @return array       The sanitized data.
	 */
	private static function sanitizePageAnalysis( $data ) {
		if (
			empty( $data['analysis'] ) ||
			! is_array( $data['analysis'] )
		) {
			return $data;
		}

		foreach ( $data['analysis'] as &$analysis ) {
			foreach ( $analysis as $key => $result ) {
				// Remove unnecessary 'title' and 'description'.
				foreach ( [ 'title', 'description' ] as $keyToRemove ) {
					if ( isset( $analysis[ $key ][ $keyToRemove ] ) ) {
						unset( $analysis[ $key ][ $keyToRemove ] );
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Sanitizes the post data and sets it (or the default value) to the Post object.
	 *
	 * @since 4.1.5
	 *
	 * @param  int   $postId  The post ID.
	 * @param  Post  $thePost The Post object.
	 * @param  array $data    The data.
	 * @return Post           The Post object with data set.
	 */
	private static function sanitizeAndSetDefaults( $postId, $thePost, $data ) {
		// General
		$thePost->post_id                     = $postId;
		$thePost->title                       = ! empty( $data['title'] ) ? sanitize_text_field( $data['title'] ) : null;
		$thePost->description                 = ! empty( $data['description'] ) ? sanitize_text_field( $data['description'] ) : null;
		$thePost->canonical_url               = ! empty( $data['canonicalUrl'] ) ? esc_url_raw( $data['canonicalUrl'] ) : null;
		$thePost->keywords                    = ! empty( $data['keywords'] ) ? sanitize_text_field( $data['keywords'] ) : null;
		$thePost->pillar_content              = isset( $data['pillar_content'] ) ? rest_sanitize_boolean( $data['pillar_content'] ) : 0;
		// TruSEO
		$thePost->keyphrases                  = ! empty( $data['keyphrases'] ) ? wp_json_encode( self::sanitizeKeyphrases( $data['keyphrases'] ) ) : null;
		$thePost->page_analysis               = ! empty( $data['page_analysis'] ) ? wp_json_encode( self::sanitizePageAnalysis( $data['page_analysis'] ) ) : null;
		$thePost->seo_score                   = ! empty( $data['seo_score'] ) ? sanitize_text_field( $data['seo_score'] ) : 0;
		// Sitemap
		$thePost->priority                    = ! empty( $data['priority'] ) ? sanitize_text_field( $data['priority'] ) : null;
		$thePost->frequency                   = ! empty( $data['frequency'] ) ? sanitize_text_field( $data['frequency'] ) : null;
		// Robots Meta
		$thePost->robots_default              = isset( $data['default'] ) ? rest_sanitize_boolean( $data['default'] ) : 1;
		$thePost->robots_noindex              = isset( $data['noindex'] ) ? rest_sanitize_boolean( $data['noindex'] ) : 0;
		$thePost->robots_nofollow             = isset( $data['nofollow'] ) ? rest_sanitize_boolean( $data['nofollow'] ) : 0;
		$thePost->robots_noarchive            = isset( $data['noarchive'] ) ? rest_sanitize_boolean( $data['noarchive'] ) : 0;
		$thePost->robots_notranslate          = isset( $data['notranslate'] ) ? rest_sanitize_boolean( $data['notranslate'] ) : 0;
		$thePost->robots_noimageindex         = isset( $data['noimageindex'] ) ? rest_sanitize_boolean( $data['noimageindex'] ) : 0;
		$thePost->robots_nosnippet            = isset( $data['nosnippet'] ) ? rest_sanitize_boolean( $data['nosnippet'] ) : 0;
		$thePost->robots_noodp                = isset( $data['noodp'] ) ? rest_sanitize_boolean( $data['noodp'] ) : 0;
		$thePost->robots_max_snippet          = ! empty( $data['maxSnippet'] ) ? (int) sanitize_text_field( $data['maxSnippet'] ) : -1;
		$thePost->robots_max_videopreview     = ! empty( $data['maxVideoPreview'] ) ? (int) sanitize_text_field( $data['maxVideoPreview'] ) : -1;
		$thePost->robots_max_imagepreview     = ! empty( $data['maxImagePreview'] ) ? sanitize_text_field( $data['maxImagePreview'] ) : 'large';
		// Open Graph Meta
		$thePost->og_title                    = ! empty( $data['og_title'] ) ? sanitize_text_field( $data['og_title'] ) : null;
		$thePost->og_description              = ! empty( $data['og_description'] ) ? sanitize_text_field( $data['og_description'] ) : null;
		$thePost->og_object_type              = ! empty( $data['og_object_type'] ) ? sanitize_text_field( $data['og_object_type'] ) : 'default';
		$thePost->og_image_type               = ! empty( $data['og_image_type'] ) ? sanitize_text_field( $data['og_image_type'] ) : 'default';
		$thePost->og_image_url                = null; // We'll reset this below.
		$thePost->og_image_width              = null; // We'll reset this below.
		$thePost->og_image_height             = null; // We'll reset this below.
		$thePost->og_image_custom_url         = ! empty( $data['og_image_custom_url'] ) ? esc_url_raw( $data['og_image_custom_url'] ) : null;
		$thePost->og_image_custom_fields      = ! empty( $data['og_image_custom_fields'] ) ? sanitize_text_field( $data['og_image_custom_fields'] ) : null;
		$thePost->og_video                    = ! empty( $data['og_video'] ) ? sanitize_text_field( $data['og_video'] ) : '';
		$thePost->og_article_section          = ! empty( $data['og_article_section'] ) ? sanitize_text_field( $data['og_article_section'] ) : null;
		$thePost->og_article_tags             = ! empty( $data['og_article_tags'] ) ? sanitize_text_field( $data['og_article_tags'] ) : null;
		// Twitter Meta
		$thePost->twitter_title               = ! empty( $data['twitter_title'] ) ? sanitize_text_field( $data['twitter_title'] ) : null;
		$thePost->twitter_description         = ! empty( $data['twitter_description'] ) ? sanitize_text_field( $data['twitter_description'] ) : null;
		$thePost->twitter_use_og              = isset( $data['twitter_use_og'] ) ? rest_sanitize_boolean( $data['twitter_use_og'] ) : 0;
		$thePost->twitter_card                = ! empty( $data['twitter_card'] ) ? sanitize_text_field( $data['twitter_card'] ) : 'default';
		$thePost->twitter_image_type          = ! empty( $data['twitter_image_type'] ) ? sanitize_text_field( $data['twitter_image_type'] ) : 'default';
		$thePost->twitter_image_url           = null; // We'll reset this below.
		$thePost->twitter_image_custom_url    = ! empty( $data['twitter_image_custom_url'] ) ? esc_url_raw( $data['twitter_image_custom_url'] ) : null;
		$thePost->twitter_image_custom_fields = ! empty( $data['twitter_image_custom_fields'] ) ? sanitize_text_field( $data['twitter_image_custom_fields'] ) : null;
		// Schema
		$thePost->schema                      = ! empty( $data['schema'] )
			? wp_json_encode( self::getDefaultSchemaOptions( $data['schema'] ) )
			: wp_json_encode( self::getDefaultSchemaOptions() );
		$thePost->local_seo                   = ! empty( $data['local_seo'] ) ? wp_json_encode( $data['local_seo'] ) : null;
		$thePost->limit_modified_date         = isset( $data['limit_modified_date'] ) ? rest_sanitize_boolean( $data['limit_modified_date'] ) : 0;
		$thePost->updated                     = gmdate( 'Y-m-d H:i:s' );

		// Before we determine the OG/Twitter image, we need to set the meta data cache manually because the changes haven't been saved yet.
		aioseo()->meta->metaData->bustPostCache( $thePost->post_id, $thePost );

		// Set the OG/Twitter image data.
		$thePost = self::setOgTwitterImageData( $thePost );

		if ( ! $thePost->exists() ) {
			$thePost->created = gmdate( 'Y-m-d H:i:s' );
		}

		return $thePost;
	}

	/**
	 * Set the OG/Twitter image data on the post object.
	 *
	 * @since 4.1.6
	 *
	 * @param  Post $thePost The Post object to modify.
	 * @return Post          The modified Post object.
	 */
	public static function setOgTwitterImageData( $thePost ) {
		// Set the OG image.
		if (
			in_array( $thePost->og_image_type, [
				'featured',
				'content',
				'attach',
				'custom',
				'custom_image'
			], true )
		) {
			// Disable the cache.
			aioseo()->social->image->useCache = false;

			// Set the image details.
			$ogImage                  = aioseo()->social->facebook->getImage( $thePost->post_id );
			$thePost->og_image_url    = is_array( $ogImage ) ? $ogImage[0] : $ogImage;
			$thePost->og_image_width  = aioseo()->social->facebook->getImageWidth();
			$thePost->og_image_height = aioseo()->social->facebook->getImageHeight();

			// Reset the cache property.
			aioseo()->social->image->useCache = true;
		}

		// Set the Twitter image.
		if (
			! $thePost->twitter_use_og &&
			in_array( $thePost->twitter_image_type, [
				'featured',
				'content',
				'attach',
				'custom',
				'custom_image'
			], true )
		) {
			// Disable the cache.
			aioseo()->social->image->useCache = false;

			// Set the image details.
			$ogImage                    = aioseo()->social->twitter->getImage( $thePost->post_id );
			$thePost->twitter_image_url = is_array( $ogImage ) ? $ogImage[0] : $ogImage;

			// Reset the cache property.
			aioseo()->social->image->useCache = true;
		}

		return $thePost;
	}

	/**
	 * Saves some of the data as post meta so that it can be used for localization.
	 *
	 * @since 4.1.5
	 *
	 * @param  int   $postId The post ID.
	 * @param  array $data   The data.
	 * @return void
	 */
	private static function updatePostMeta( $postId, $data ) {
		// Update the post meta as well for localization.
		$keywords      = ! empty( $data['keywords'] ) ? aioseo()->helpers->jsonTagsToCommaSeparatedList( $data['keywords'] ) : [];
		$ogArticleTags = ! empty( $data['og_article_tags'] ) ? aioseo()->helpers->jsonTagsToCommaSeparatedList( $data['og_article_tags'] ) : [];

		update_post_meta( $postId, '_aioseo_title', $data['title'] );
		update_post_meta( $postId, '_aioseo_description', $data['description'] );
		update_post_meta( $postId, '_aioseo_keywords', $keywords );
		update_post_meta( $postId, '_aioseo_og_title', $data['og_title'] );
		update_post_meta( $postId, '_aioseo_og_description', $data['og_description'] );
		update_post_meta( $postId, '_aioseo_og_article_section', $data['og_article_section'] );
		update_post_meta( $postId, '_aioseo_og_article_tags', $ogArticleTags );
		update_post_meta( $postId, '_aioseo_twitter_title', $data['twitter_title'] );
		update_post_meta( $postId, '_aioseo_twitter_description', $data['twitter_description'] );
	}

	/**
	 * Returns the default values for the TruSEO page analysis.
	 *
	 * @since 4.0.0
	 *
	 * @return object The default values.
	 */
	public static function getPageAnalysisDefaults() {
		$defaults = [
			'analysis' => [
				'basic'       => [
					'lengthContent' => [
						'error'    => 1,
						'maxScore' => 9,
						'score'    => 6,
					],
				],
				'title'       => [
					'titleLength' => [
						'error'    => 1,
						'maxScore' => 9,
						'score'    => 1,
					],
				],
				'readability' => [
					'contentHasAssets' => [
						'error'    => 1,
						'maxScore' => 5,
						'score'    => 0,
					],
				]
			]
		];

		return json_decode( wp_json_encode( $defaults ) );
	}

	/**
	 * Returns a JSON object with default schema options.
	 *
	 * @since 4.2.5
	 *
	 * @param  string       $existingOptions The existing options in JSON.
	 * @param  null|WP_Post $post            The post object.
	 * @return string                        The existing options with defaults added in JSON.
	 */
	public static function getDefaultSchemaOptions( $existingOptions = '', $post = null ) {
		$defaultGraphName = aioseo()->schema->getDefaultPostTypeGraph( $post );

		$defaults = [
			'blockGraphs'  => [],
			'customGraphs' => [],
			'default'      => [
				'data'      => [
					'Article'             => [],
					'Course'              => [],
					'Dataset'             => [],
					'FAQPage'             => [],
					'Movie'               => [],
					'Person'              => [],
					'Product'             => [],
					'Recipe'              => [],
					'Service'             => [],
					'SoftwareApplication' => [],
					'WebPage'             => []
				],
				'graphName' => $defaultGraphName,
				'isEnabled' => true,
			],
			'graphs'       => []
		];

		if ( empty( $existingOptions ) ) {
			return json_decode( wp_json_encode( $defaults ) );
		}

		$existingOptions = json_decode( wp_json_encode( $existingOptions ), true );
		$existingOptions = array_replace_recursive( $defaults, $existingOptions );

		if ( isset( $existingOptions['defaultGraph'] ) && ! empty( $existingOptions['defaultPostTypeGraph'] ) ) {
			$existingOptions['default']['isEnabled'] = ! empty( $existingOptions['defaultGraph'] );

			unset( $existingOptions['defaultGraph'] );
			unset( $existingOptions['defaultPostTypeGraph'] );
		}

		// Reset the default graph type to make sure it's accurate.
		$existingOptions['default']['graphName'] = $defaultGraphName;

		return json_decode( wp_json_encode( $existingOptions ) );
	}

	/**
	 * Returns the defaults for the keyphrases column.
	 *
	 * @since 4.1.7
	 *
	 * @param  string $keyphrases The database keyphrases.
	 * @return array              The defaults.
	 */
	public static function getKeyphrasesDefaults( $keyphrases = '' ) {
		$keyphrases = json_decode( (string) $keyphrases );
		$defaults   = [
			'focus'      => [
				'keyphrase' => '',
				'score'     => 0,
				'analysis'  => [
					'keyphraseInTitle' => [
						'score'    => 0,
						'maxScore' => 9,
						'error'    => 1
					]
				]
			],
			'additional' => []
		];

		if ( empty( $keyphrases ) ) {
			return json_decode( wp_json_encode( $defaults ) );
		}

		if ( empty( $keyphrases->focus ) ) {
			$keyphrases->focus = $defaults['focus'];
		}

		if ( empty( $keyphrases->additional ) ) {
			$keyphrases->additional = $defaults['additional'];
		}

		return $keyphrases;
	}

	/**
	 * Returns the defaults for the options column.
	 *
	 * @since   4.2.2
	 * @version 4.2.9
	 *
	 * @param  Post $post   The Post object.
	 * @return Post         The modified Post object.
	 */
	public static function setOptionsDefaults( $post ) {
		$defaults = [
			'linkFormat' => [
				'internalLinkCount'      => 0,
				'linkAssistantDismissed' => false
			]
		];

		if ( empty( $post->options ) ) {
			$post->options = json_decode( wp_json_encode( $defaults ) );

			return $post;
		}

		$post->options = json_decode( wp_json_encode( $post->options ), true );
		$post->options = array_replace_recursive( $defaults, $post->options );
		$post->options = json_decode( wp_json_encode( $post->options ) );

		return $post;
	}
}