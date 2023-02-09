<?php
namespace AIOSEO\Plugin\Common\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIOSEO\Plugin\Common\Models;

/**
 * Route class for the API.
 *
 * @since 4.0.0
 */
class PostsTerms {
	/**
	 * Searches for posts or terms by ID/name.
	 *
	 * @since 4.0.0
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function searchForObjects( $request ) {
		$body = $request->get_json_params();

		if ( empty( $body['query'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'No search term was provided.'
			], 400 );
		}
		if ( empty( $body['type'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'No type was provided.'
			], 400 );
		}

		$searchQuery = esc_sql( aioseo()->core->db->db->esc_like( $body['query'] ) );

		$objects        = [];
		$dynamicOptions = aioseo()->dynamicOptions->noConflict();
		if ( 'posts' === $body['type'] ) {

			$postTypes = aioseo()->helpers->getPublicPostTypes( true );
			foreach ( $postTypes as $postType ) {
				// Check if post type isn't noindexed.
				if ( $dynamicOptions->searchAppearance->postTypes->has( $postType ) && ! $dynamicOptions->searchAppearance->postTypes->$postType->show ) {
					$postTypes = aioseo()->helpers->unsetValue( $postTypes, $postType );
				}
			}

			$objects = aioseo()->core->db
				->start( 'posts' )
				->select( 'ID, post_type, post_title, post_name' )
				->whereRaw( "( post_title LIKE '%{$searchQuery}%' OR post_name LIKE '%{$searchQuery}%' OR ID = '{$searchQuery}' )" )
				->whereIn( 'post_type', $postTypes )
				->whereIn( 'post_status', [ 'publish', 'draft', 'future', 'pending' ] )
				->orderBy( 'post_title' )
				->limit( 10 )
				->run()
				->result();

		} elseif ( 'terms' === $body['type'] ) {

			$taxonomies = aioseo()->helpers->getPublicTaxonomies( true );
			foreach ( $taxonomies as $taxonomy ) {
				// Check if taxonomy isn't noindexed.
				if ( $dynamicOptions->searchAppearance->taxonomies->has( $taxonomy ) && ! $dynamicOptions->searchAppearance->taxonomies->$taxonomy->show ) {
					$taxonomies = aioseo()->helpers->unsetValue( $taxonomies, $taxonomy );
				}
			}

			$objects = aioseo()->core->db
				->start( 'terms as t' )
				->select( 't.term_id as term_id, t.slug as slug, t.name as name, tt.taxonomy as taxonomy' )
				->join( 'term_taxonomy as tt', 't.term_id = tt.term_id', 'INNER' )
				->whereRaw( "( t.name LIKE '%{$searchQuery}%' OR t.slug LIKE '%{$searchQuery}%' OR t.term_id = '{$searchQuery}' )" )
				->whereIn( 'tt.taxonomy', $taxonomies )
				->orderBy( 't.name' )
				->limit( 10 )
				->run()
				->result();
		}

		if ( empty( $objects ) ) {
			return new \WP_REST_Response( [
				'success' => true,
				'objects' => []
			], 200 );
		}

		$parsed = [];
		foreach ( $objects as $object ) {
			if ( 'posts' === $body['type'] ) {
				$parsed[] = [
					'type'  => $object->post_type,
					'value' => (int) $object->ID,
					'slug'  => $object->post_name,
					'label' => $object->post_title,
					'link'  => get_permalink( $object->ID )
				];
			} elseif ( 'terms' === $body['type'] ) {
				$parsed[] = [
					'type'  => $object->taxonomy,
					'value' => (int) $object->term_id,
					'slug'  => $object->slug,
					'label' => $object->name,
					'link'  => get_term_link( $object->term_id )
				];
			}
		}

		return new \WP_REST_Response( [
			'success' => true,
			'objects' => $parsed
		], 200 );
	}

	/**
	 * Get post data for fetch requests
	 *
	 * @since 4.0.0
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function getPostData( $request ) {
		$args = $request->get_query_params();

		if ( empty( $args['postId'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'No post ID was provided.', 'all-in-one-seo-pack' )
			], 400 );
		}

		$thePost = Models\Post::getPost( $args['postId'] );

		return new \WP_REST_Response( [
			'success'  => true,
			'post'     => $thePost,
			'postData' => [
				'parsedTitle'       => aioseo()->tags->replaceTags( $thePost->title, $args['postId'] ),
				'parsedDescription' => aioseo()->tags->replaceTags( $thePost->description, $args['postId'] ),
				'content'           => aioseo()->helpers->theContent( self::getAnalysisContent( $args['postId'] ) ),
				'slug'              => get_post_field( 'post_name', $args['postId'] )
			]
		], 200 );
	}

	/**
	 * Get the first attached image for a post.
	 *
	 * @since 4.1.8
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function getFirstAttachedImage( $request ) {
		$args = $request->get_params();

		if ( empty( $args['postId'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'No post ID was provided.', 'all-in-one-seo-pack' )
			], 400 );
		}

		// Disable the cache.
		aioseo()->social->image->useCache = false;

		$post = aioseo()->helpers->getPost( $args['postId'] );
		$url  = aioseo()->social->image->getImage( 'facebook', 'attach', $post );

		// Reset the cache property.
		aioseo()->social->image->useCache = true;

		return new \WP_REST_Response( [
			'success' => true,
			'url'     => is_array( $url ) ? $url[0] : $url,
		], 200 );
	}

	/**
	 * Returns the posts custom fields.
	 *
	 * @since 4.0.6
	 *
	 * @param  WP_Post|int $post The post.
	 * @return string            The custom field content.
	 */
	private static function getAnalysisContent( $post = null ) {
		$analysisContent = apply_filters( 'aioseo_analysis_content', aioseo()->helpers->getPostContent( $post ) );

		return sanitize_post_field( 'post_content', $analysisContent, $post->ID, 'display' );
	}

	/**
	 * Update post settings.
	 *
	 * @since 4.0.0
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function updatePosts( $request ) {
		$body   = $request->get_json_params();
		$postId = ! empty( $body['id'] ) ? intval( $body['id'] ) : null;

		if ( ! $postId ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Post ID is missing.', 'all-in-one-seo-pack' )
			], 400 );
		}

		$body['id']                  = $postId;
		$body['title']               = ! empty( $body['title'] ) ? sanitize_text_field( $body['title'] ) : null;
		$body['description']         = ! empty( $body['description'] ) ? sanitize_text_field( $body['description'] ) : null;
		$body['keywords']            = ! empty( $body['keywords'] ) ? sanitize_text_field( $body['keywords'] ) : null;
		$body['og_title']            = ! empty( $body['og_title'] ) ? sanitize_text_field( $body['og_title'] ) : null;
		$body['og_description']      = ! empty( $body['og_description'] ) ? sanitize_text_field( $body['og_description'] ) : null;
		$body['og_article_section']  = ! empty( $body['og_article_section'] ) ? sanitize_text_field( $body['og_article_section'] ) : null;
		$body['og_article_tags']     = ! empty( $body['og_article_tags'] ) ? sanitize_text_field( $body['og_article_tags'] ) : null;
		$body['twitter_title']       = ! empty( $body['twitter_title'] ) ? sanitize_text_field( $body['twitter_title'] ) : null;
		$body['twitter_description'] = ! empty( $body['twitter_description'] ) ? sanitize_text_field( $body['twitter_description'] ) : null;

		$error = Models\Post::savePost( $postId, $body );

		if ( ! empty( $error ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'Failed update query: ' . $error
			], 401 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'posts'   => $postId
		], 200 );
	}

	/**
	 * Update post settings from Post screen.
	 *
	 * @since 4.0.0
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function updatePostFromScreen( $request ) {
		$body    = $request->get_json_params();
		$postId  = ! empty( $body['postId'] ) ? intval( $body['postId'] ) : null;
		$isMedia = isset( $body['isMedia'] ) ? true : false;
		$post    = aioseo()->helpers->getPost( $postId );

		if ( ! $postId ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'Post ID is missing.'
			], 400 );
		}

		$thePost = Models\Post::getPost( $postId );

		if ( $thePost->exists() ) {
			$metaTitle = aioseo()->meta->title->getPostTypeTitle( $post->post_type );
			if ( empty( $thePost->title ) && ! empty( $body['title'] ) && trim( $body['title'] ) === trim( $metaTitle ) ) {
				$body['title'] = null;
			}
			$thePost->title = ! empty( $body['title'] ) ? sanitize_text_field( $body['title'] ) : null;

			$metaDescription = aioseo()->meta->description->getPostTypeDescription( $post->post_type );
			if ( empty( $thePost->description ) && ! empty( $body['description'] ) && trim( $body['description'] ) === trim( $metaDescription ) ) {
				$body['description'] = null;
			}
			$thePost->description = ! empty( $body['description'] ) ? sanitize_text_field( $body['description'] ) : '';
			$thePost->updated     = gmdate( 'Y-m-d H:i:s' );
			if ( $isMedia ) {
				wp_update_post(
					[
						'ID'         => $postId,
						'post_title' => sanitize_text_field( $body['imageTitle'] ),
					]
				);
				update_post_meta( $postId, '_wp_attachment_image_alt', sanitize_text_field( $body['imageAltTag'] ) );
			}
		} else {
			$thePost->post_id     = $postId;
			$thePost->title       = ! empty( $body['title'] ) ? sanitize_text_field( $body['title'] ) : '';
			$thePost->description = ! empty( $body['description'] ) ? sanitize_text_field( $body['description'] ) : null;
			$thePost->created     = gmdate( 'Y-m-d H:i:s' );
			$thePost->updated     = gmdate( 'Y-m-d H:i:s' );
			if ( $isMedia ) {
				wp_update_post(
					[
						'ID'         => $postId,
						'post_title' => sanitize_text_field( $body['imageTitle'] ),
					]
				);
				update_post_meta( $postId, '_wp_attachment_image_alt', sanitize_text_field( $body['imageAltTag'] ) );
			}
		}
		$thePost->save();

		$lastError = aioseo()->core->db->lastError();
		if ( ! empty( $lastError ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'Failed update query: ' . $lastError
			], 401 );
		}

		return new \WP_REST_Response( [
			'success'     => true,
			'posts'       => $postId,
			'title'       => aioseo()->meta->title->getPostTitle( $postId ),
			'description' => aioseo()->meta->description->getPostDescription( $postId )
		], 200 );
	}

	/**
	 * Update post keyphrases.
	 *
	 * @since 4.0.0
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function updatePostKeyphrases( $request ) {
		$body   = $request->get_json_params();
		$postId = ! empty( $body['postId'] ) ? intval( $body['postId'] ) : null;

		if ( ! $postId ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'Post ID is missing.'
			], 400 );
		}

		$thePost = Models\Post::getPost( $postId );

		$thePost->post_id = $postId;
		if ( ! empty( $body['keyphrases'] ) ) {
			$thePost->keyphrases = wp_json_encode( $body['keyphrases'] );
		}
		if ( ! empty( $body['page_analysis'] ) ) {
			$thePost->page_analysis = wp_json_encode( $body['page_analysis'] );
		}
		if ( ! empty( $body['seo_score'] ) ) {
			$thePost->seo_score = sanitize_text_field( $body['seo_score'] );
		}
		$thePost->updated = gmdate( 'Y-m-d H:i:s' );
		$thePost->save();

		$lastError = aioseo()->core->db->lastError();
		if ( ! empty( $lastError ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'Failed update query: ' . $lastError
			], 401 );
		}

		return new \WP_REST_Response( [
			'success' => true,
			'post'    => $postId
		], 200 );
	}

	/**
	 * Disable the link format education.
	 *
	 * @since 4.2.2
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function disableLinkFormatEducation( $request ) {
		$args = $request->get_params();

		if ( empty( $args['postId'] ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'No post ID was provided.', 'all-in-one-seo-pack' )
			], 400 );
		}

		$thePost = Models\Post::getPost( $args['postId'] );
		$thePost->options->linkFormat->linkAssistantDismissed = true;
		$thePost->save();

		return new \WP_REST_Response( [
			'success' => true
		], 200 );
	}

	/**
	 * Increment the internal link count.
	 *
	 * @since 4.2.2
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function updateInternalLinkCount( $request ) {
		$args  = $request->get_params();
		$body  = $request->get_json_params();
		$count = ! empty( $body['count'] ) ? intval( $body['count'] ) : null;

		if ( empty( $args['postId'] ) || null === $count ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'No post ID or count was provided.', 'all-in-one-seo-pack' )
			], 400 );
		}

		$thePost = Models\Post::getPost( $args['postId'] );
		$thePost->options->linkFormat->internalLinkCount = $count;
		$thePost->save();

		return new \WP_REST_Response( [
			'success' => true
		], 200 );
	}
}