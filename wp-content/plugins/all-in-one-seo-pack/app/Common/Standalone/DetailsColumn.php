<?php
namespace AIOSEO\Plugin\Common\Standalone;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIOSEO\Plugin\Common\Models;

/**
 * Handles the AIOSEO Details post column.
 *
 * @since 4.2.0
 */
class DetailsColumn {
	/**
	 * The slug for the script.
	 *
	 * @since 4.2.0
	 *
	 * @var string
	 */
	protected $scriptSlug = 'src/vue/standalone/posts-table/main.js';

	/**
	 * Class constructor.
	 *
	 * @since 4.2.0
	 */
	public function __construct() {
		if ( wp_doing_ajax() ) {
			add_action( 'init', [ $this, 'addPostColumnsAjax' ], 1 );
		}

		if ( ! is_admin() || wp_doing_cron() ) {
			return;
		}

		add_action( 'current_screen', [ $this, 'registerColumnHooks' ], 1 );
	}

	/**
	 * Adds the columns to the page/post types.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function registerColumnHooks() {
		$screen = get_current_screen();
		if ( ! $this->shouldRegisterColumn( $screen->base, $screen->post_type ) ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueScripts' ] );

		if ( 'product' === $screen->post_type ) {
			add_filter( 'manage_edit-product_columns', [ $this, 'addColumn' ] );
			add_action( 'manage_posts_custom_column', [ $this, 'renderColumn' ], 10, 2 );

			return;
		}

		if ( 'attachment' === $screen->post_type ) {
			$enabled = apply_filters( 'aioseo_image_seo_media_columns', true );
			if ( ! $enabled ) {
				return;
			}

			add_filter( 'manage_media_columns', [ $this, 'addColumn' ] );
			add_action( 'manage_media_custom_column', [ $this, 'renderColumn' ], 10, 2 );

			return;
		}

		add_filter( "manage_edit-{$screen->post_type}_columns", [ $this, 'addColumn' ] );
		add_action( "manage_{$screen->post_type}_posts_custom_column", [ $this, 'renderColumn' ], 10, 2 );
	}

	/**
	 * Registers our post columns after a post has been quick-edited.
	 *
	 * @since 4.2.3
	 *
	 * @return void
	 */
	public function addPostColumnsAjax() {
		if (
			! isset( $_POST['_inline_edit'], $_POST['post_ID'] ) ||
			! wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' )
		) {
			return;
		}

		$postId = (int) $_POST['post_ID'];
		if ( ! $postId ) {
			return;
		}

		$post     = get_post( $postId );
		$postType = $post->post_type;

		add_filter( "manage_edit-{$postType}_columns", [ $this, 'addColumn' ] );
		add_action( "manage_{$postType}_posts_custom_column", [ $this, 'renderColumn' ], 10, 2 );
	}

	/**
	 * Enqueues the JS/CSS for the page/posts table page.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function enqueueScripts() {
		$data          = aioseo()->helpers->getVueData();
		$data['posts'] = [];
		$data['terms'] = [];

		aioseo()->core->assets->load( $this->scriptSlug, [], $data );
	}

	/**
	 * Adds the AIOSEO Details column to the page/post tables in the admin.
	 *
	 * @since 4.0.0
	 *
	 * @param  array $columns The columns we are adding ours onto.
	 * @return array          The modified columns.
	 */
	public function addColumn( $columns ) {
		$canManageSeo = apply_filters( 'aioseo_manage_seo', 'aioseo_manage_seo' );
		if (
			! current_user_can( $canManageSeo ) &&
			(
				! current_user_can( 'aioseo_page_general_settings' ) &&
				! current_user_can( 'aioseo_page_analysis' )
			)
		) {
			return $columns;
		}

		// Translators: 1 - The short plugin name ("AIOSEO").
		$columns['aioseo-details'] = sprintf( esc_html__( '%1$s Details', 'all-in-one-seo-pack' ), AIOSEO_PLUGIN_SHORT_NAME );

		return $columns;
	}

	/**
	 * Renders the column in the page/post table.
	 *
	 * @since 4.0.0
	 *
	 * @param  string $columnName The column name.
	 * @param  int    $postId     The current rows, post id.
	 * @return void
	 */
	public function renderColumn( $columnName, $postId ) {
		if ( ! current_user_can( 'edit_post', $postId ) && ! current_user_can( 'aioseo_manage_seo' ) ) {
			return;
		}

		if ( 'aioseo-details' !== $columnName ) {
			return;
		}

		// Add this column/post to the localized array.
		global $wp_scripts;

		$data = $wp_scripts->get_data( 'aioseo/js/' . $this->scriptSlug, 'data' );

		if ( ! is_array( $data ) ) {
			$data = json_decode( str_replace( 'var aioseo = ', '', substr( $data, 0, -1 ) ), true );
		}

		$nonce    = wp_create_nonce( "aioseo_meta_{$columnName}_{$postId}" );
		$posts    = ! empty( $data['posts'] ) ? $data['posts'] : [];
		$thePost  = Models\Post::getPost( $postId );
		$postType = get_post_type( $postId );
		$postData = [
			'id'                 => $postId,
			'columnName'         => $columnName,
			'nonce'              => $nonce,
			'title'              => $thePost->title,
			'titleParsed'        => aioseo()->meta->title->getPostTitle( $postId ),
			'defaultTitle'       => aioseo()->meta->title->getPostTypeTitle( $postType ),
			'description'        => $thePost->description,
			'descriptionParsed'  => aioseo()->meta->description->getPostDescription( $postId ),
			'defaultDescription' => aioseo()->meta->description->getPostTypeDescription( $postType ),
			'value'              => (int) $thePost->seo_score,
			'showMedia'          => false,
			'isSpecialPage'      => aioseo()->helpers->isSpecialPage( $postId ),
			'postType'           => $postType
		];

		foreach ( aioseo()->addons->getLoadedAddons() as $loadedAddon ) {
			if ( isset( $loadedAddon->admin ) && method_exists( $loadedAddon->admin, 'renderColumnData' ) ) {
				$postData = array_merge( $postData, $loadedAddon->admin->renderColumnData( $columnName, $postId, $postData ) );
			}
		}

		$posts[]       = $postData;
		$data['posts'] = $posts;

		$wp_scripts->add_data( 'aioseo/js/' . $this->scriptSlug, 'data', '' );
		wp_localize_script( 'aioseo/js/' . $this->scriptSlug, 'aioseo', $data );

		require AIOSEO_DIR . '/app/Common/Views/admin/posts/columns.php';
	}

	/**
	 * Checks whether the AIOSEO Details column should be registered.
	 *
	 * @since 4.0.0
	 *
	 * @return bool Whether the column should be registered.
	 */
	public function shouldRegisterColumn( $screen, $postType ) {
		if ( 'type' === $postType ) {
			$postType = '_aioseo_type';
		}

		if ( 'edit' === $screen || 'upload' === $screen ) {
			if (
				aioseo()->options->advanced->postTypes->all &&
				in_array( $postType, aioseo()->helpers->getPublicPostTypes( true ), true )
			) {
				return true;
			}

			$postTypes = aioseo()->options->advanced->postTypes->included;
			if ( in_array( $postType, $postTypes, true ) ) {
				return true;
			}
		}

		return false;
	}
}