<?php
namespace AIOSEO\Plugin\Common\Traits\Helpers;

use AIOSEO\Plugin\Common\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains all WP related helper methods.
 *
 * @since 4.1.4
 */
trait Wp {
	/**
	 * Whether or not we have a local connection.
	 *
	 * @since 4.0.0
	 *
	 * @var bool
	 */
	private static $connection = false;

	/**
	 * Returns user roles in the current WP install.
	 *
	 * @since 4.0.0
	 *
	 * @return array An array of user roles.
	 */
	public function getUserRoles() {
		global $wp_roles;

		$wpRoles = $wp_roles;
		if ( ! is_object( $wpRoles ) ) {
			// Don't assign this to the global because otherwise WordPress won't override it.
			$wpRoles = new \WP_Roles();
		}

		$roleNames = $wpRoles->get_names();
		asort( $roleNames );

		return $roleNames;
	}

	/**
	 * Returns the custom roles in the current WP install.
	 *
	 * @since 4.1.3
	 *
	 * @return array An array of custom roles.
	 */
	public function getCustomRoles() {
		$allRoles = $this->getUserRoles();

		$toSkip = array_merge(
			// Default WordPress roles.
			[ 'superadmin', 'administrator', 'editor', 'author', 'contributor' ],
			// Default AIOSEO roles.
			[ 'aioseo_manager', 'aioseo_editor' ],
			// Filterable roles.
			apply_filters( 'aioseo_access_control_excluded_roles', array_merge( [
				'subscriber'
			], aioseo()->helpers->isWooCommerceActive() ? [ 'customer' ] : [] ) )
		);

		// Remove empty entries.
		$toSkip = array_filter( $toSkip );

		$customRoles = [];
		foreach ( $allRoles as $roleName => $role ) {
			// Skip specific roles.
			if ( in_array( $roleName, $toSkip, true ) ) {
				continue;
			}

			$customRoles[ $roleName ] = $role;
		}

		return $customRoles;
	}

	/**
	 * Returns an array of plugins with the active status.
	 *
	 * @since 4.0.0
	 *
	 * @return array An array of plugins with active status.
	 */
	public function getPluginData() {
		$pluginUpgrader   = new Utils\PluginUpgraderSilentAjax();
		$installedPlugins = array_keys( get_plugins() );

		$plugins = [];
		foreach ( $pluginUpgrader->pluginSlugs as $key => $slug ) {
			$adminUrl        = admin_url( $pluginUpgrader->pluginAdminUrls[ $key ] );
			$networkAdminUrl = null;
			if (
				is_multisite() &&
				is_network_admin() &&
				! empty( $pluginUpgrader->hasNetworkAdmin[ $key ] )
			) {
				$networkAdminUrl = network_admin_url( $pluginUpgrader->hasNetworkAdmin[ $key ] );
				if ( aioseo()->helpers->isPluginNetworkActivated( $pluginUpgrader->pluginSlugs[ $key ] ) ) {
					$adminUrl = $networkAdminUrl;
				}
			}

			$plugins[ $key ] = [
				'basename'        => $slug,
				'installed'       => in_array( $slug, $installedPlugins, true ),
				'activated'       => is_plugin_active( $slug ),
				'adminUrl'        => $adminUrl,
				'networkAdminUrl' => $networkAdminUrl,
				'canInstall'      => aioseo()->addons->canInstall(),
				'canActivate'     => aioseo()->addons->canActivate(),
				'canUpdate'       => aioseo()->addons->canUpdate(),
				'wpLink'          => ! empty( $pluginUpgrader->wpPluginLinks[ $key ] ) ? $pluginUpgrader->wpPluginLinks[ $key ] : null
			];
		}

		return $plugins;
	}

	/**
	 * Get all registered Post Statuses.
	 *
	 * @since 4.1.6
	 *
	 * @param  boolean $statusesOnly Whether or not to only return statuses.
	 * @return array              An array of post statuses.
	 */
	public function getPublicPostStatuses( $statusesOnly = false ) {
		$allStatuses = get_post_stati( [ 'show_in_admin_all_list' => true ], 'objects' );

		$postStatuses = [];
		foreach ( $allStatuses as $status => $data ) {
			if (
				! $data->public &&
				! $data->protected &&
				! $data->private
			) {
				continue;
			}

			if ( $statusesOnly ) {
				$postStatuses[] = $status;
				continue;
			}

			$postStatuses[] = [
				'label'  => $data->label,
				'status' => $status
			];
		}

		return $postStatuses;
	}

	/**
	 * Retrieve a list of public post types with slugs/icons.
	 *
	 * @since 4.0.0
	 *
	 * @param  boolean $namesOnly       Whether only the names should be returned.
	 * @param  boolean $hasArchivesOnly Whether or not to only include post types which have archives.
	 * @param  boolean $rewriteType     Whether or not to rewrite the type slugs.
	 * @return array                    An array of public post types.
	 */
	public function getPublicPostTypes( $namesOnly = false, $hasArchivesOnly = false, $rewriteType = false ) {
		$postTypes   = [];
		$postTypeObjects = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $postTypeObjects as $postTypeObject ) {
			$postTypeArray = $this->getPostType( $postTypeObject, $namesOnly, $hasArchivesOnly, $rewriteType );
			if ( ! empty( $postTypeArray ) ) {
				$postTypes[] = $postTypeArray;
			}
		}

		return apply_filters( 'aioseo_public_post_types', $postTypes, $namesOnly, $hasArchivesOnly );
	}

	/**
	 * Get the data for the post type.
	 *
	 * @since 4.2.2
	 *
	 * @param  \WP_Post_Type $postTypeObject  The post type object.
	 * @param  boolean       $namesOnly       Whether only the names should be returned.
	 * @param  boolean       $hasArchivesOnly Whether or not to only include post types which have archives.
	 * @param  boolean       $rewriteType     Whether or not to rewrite the type slugs.
	 * @return mixed                          Data for the post type.
	 */
	public function getPostType( $postTypeObject, $namesOnly = false, $hasArchivesOnly = false, $rewriteType = false ) {
		if ( empty( $postTypeObject->label ) ) {
			return $namesOnly ? null : [];
		}

		// We don't want to include archives for the WooCommerce shop page.
		if (
			$hasArchivesOnly &&
			(
				! $postTypeObject->has_archive ||
				( 'product' === $postTypeObject->name && $this->isWooCommerceActive() )
			)
		) {
			return $namesOnly ? null : [];
		}

		if ( $namesOnly ) {
			return $postTypeObject->name;
		}

		if ( 'attachment' === $postTypeObject->name ) {
			$postTypeObject->label = __( 'Attachments', 'all-in-one-seo-pack' );
		}

		if ( 'product' === $postTypeObject->name && $this->isWooCommerceActive() ) {
			$postTypeObject->menu_icon = 'dashicons-products';
		}

		$name = $postTypeObject->name;
		if ( 'type' === $postTypeObject->name && $rewriteType ) {
			$name = '_aioseo_type';
		}

		return [
			'name'         => $name,
			'label'        => ucwords( $postTypeObject->label ),
			'singular'     => ucwords( $postTypeObject->labels->singular_name ),
			'icon'         => $postTypeObject->menu_icon,
			'hasExcerpt'   => post_type_supports( $postTypeObject->name, 'excerpt' ),
			'hasArchive'   => $postTypeObject->has_archive,
			'hierarchical' => $postTypeObject->hierarchical,
			'taxonomies'   => get_object_taxonomies( $name ),
			'slug'         => isset( $postTypeObject->rewrite['slug'] ) ? $postTypeObject->rewrite['slug'] : $name
		];
	}

	/**
	 * Retrieve a list of public taxonomies with slugs/icons.
	 *
	 * @since 4.0.0
	 *
	 * @param  boolean $namesOnly   Whether only the names should be returned.
	 * @param  boolean $rewriteType Whether or not to rewrite the type slugs.
	 * @return array                An array of public taxonomies.
	 */
	public function getPublicTaxonomies( $namesOnly = false, $rewriteType = false ) {
		$taxonomies = [];
		if ( count( $taxonomies ) ) {
			return $taxonomies;
		}

		$taxObjects = get_taxonomies( [ 'public' => true ], 'objects' );
		foreach ( $taxObjects as $taxObject ) {
			if ( empty( $taxObject->label ) ) {
				continue;
			}

			if ( in_array( $taxObject->name, [
				'product_shipping_class',
				'post_format'
			], true ) ) {
				continue;
			}

			// We need to exclude product attributes from this list as well.
			if (
				'pa_' === substr( $taxObject->name, 0, 3 ) &&
				'manage_product_terms' === $taxObject->cap->manage_terms &&
				! apply_filters( 'aioseo_woocommerce_product_attributes', false )
			) {
				continue;
			}

			if ( $namesOnly ) {
				$taxonomies[] = $taxObject->name;
				continue;
			}

			$name = $taxObject->name;
			if ( 'type' === $taxObject->name && $rewriteType ) {
				$name = '_aioseo_type';
			}

			global $wp_taxonomies;
			$taxonomyPostTypes = ! empty( $wp_taxonomies[ $name ] )
				? $wp_taxonomies[ $name ]->object_type
				: [];

			$taxonomies[] = [
				'name'         => $name,
				'label'        => ucwords( $taxObject->label ),
				'singular'     => ucwords( $taxObject->labels->singular_name ),
				'icon'         => strpos( $taxObject->label, 'categor' ) !== false ? 'dashicons-category' : 'dashicons-tag',
				'hierarchical' => $taxObject->hierarchical,
				'slug'         => isset( $taxObject->rewrite['slug'] ) ? $taxObject->rewrite['slug'] : '',
				'postTypes'    => $taxonomyPostTypes
			];
		}

		return apply_filters( 'aioseo_public_taxonomies', $taxonomies, $namesOnly );
	}

	/**
	 * Retrieve a list of users that match passed in roles.
	 *
	 * @since 4.0.0
	 *
	 * @return array An array of user data.
	 */
	public function getSiteUsers( $roles ) {
		static $users = [];

		if ( ! empty( $users ) ) {
			return $users;
		}

		$rolesWhere = [];
		foreach ( $roles as $role ) {
			$rolesWhere[] = '(um.meta_key = \'' . aioseo()->core->db->db->prefix . 'capabilities\' AND um.meta_value LIKE \'%\"' . $role . '\"%\')';
		}
		$dbUsers = aioseo()->core->db->start( 'users as u' )
			->select( 'u.ID, u.display_name, u.user_nicename, u.user_email' )
			->join( 'usermeta as um', 'u.ID = um.user_id' )
			->whereRaw( '(' . implode( ' OR ', $rolesWhere ) . ')' )
			->orderBy( 'u.user_nicename' )
			->run()
			->result();

		foreach ( $dbUsers as $dbUser ) {
			$users[] = [
				'id'          => (int) $dbUser->ID,
				'displayName' => $dbUser->display_name,
				'niceName'    => $dbUser->user_nicename,
				'email'       => $dbUser->user_email,
				'gravatar'    => get_avatar_url( $dbUser->user_email )
			];
		}

		return $users;
	}

	/**
	 * Returns the ID of the site logo if it exists.
	 *
	 * @since 4.0.0
	 *
	 * @return int
	 */
	public function getSiteLogoId() {
		if ( ! get_theme_support( 'custom-logo' ) ) {
			return false;
		}

		return get_theme_mod( 'custom_logo' );
	}

	/**
	 * Returns the URL of the site logo if it exists.
	 *
	 * @since 4.0.0
	 *
	 * @return string
	 */
	public function getSiteLogoUrl() {
		$id = $this->getSiteLogoId();
		if ( ! $id ) {
			return false;
		}

		$image = wp_get_attachment_image_src( $id, 'full' );
		if ( empty( $image ) ) {
			return false;
		}

		return $image[0];
	}

	/**
	 * Returns noindexed post types.
	 *
	 * @since 4.0.0
	 *
	 * @return array A list of noindexed post types.
	 */
	public function getNoindexedPostTypes() {
		return $this->getNoindexedObjects( 'postTypes' );
	}

	/**
	 * Checks whether a given post type is noindexed.
	 *
	 * @since 4.0.0
	 *
	 * @param  string  $postType The post type.
	 * @return bool              Whether the post type is noindexed.
	 */
	public function isPostTypeNoindexed( $postType ) {
		$noindexedPostTypes = $this->getNoindexedPostTypes();

		return in_array( $postType, $noindexedPostTypes, true );
	}

	/**
	 * Checks whether a given post type is public.
	 *
	 * @since 4.2.2
	 *
	 * @param  string  $postType The post type.
	 * @return bool              Whether the post type is public.
	 */
	public function isPostTypePublic( $postType ) {
		$publicPostTypes = $this->getPublicPostTypes( true );

		return in_array( $postType, $publicPostTypes, true );
	}

	/**
	 * Returns noindexed taxonomies.
	 *
	 * @since 4.0.0
	 *
	 * @return array A list of noindexed taxonomies.
	 */
	public function getNoindexedTaxonomies() {
		return $this->getNoindexedObjects( 'taxonomies' );
	}

	/**
	 * Checks whether a given post type is noindexed.
	 *
	 * @since 4.0.0
	 *
	 * @param  string  $taxonomy The taxonomy.
	 * @return bool              Whether the taxonomy is noindexed.
	 */
	public function isTaxonomyNoindexed( $taxonomy ) {
		$noindexedTaxonomies = $this->getNoindexedTaxonomies();

		return in_array( $taxonomy, $noindexedTaxonomies, true );
	}

	/**
	 * Checks whether a given taxonomy is public.
	 *
	 * @since 4.2.2
	 *
	 * @param  string  $taxonomy The taxonomy.
	 * @return bool              Whether the taxonomy is public.
	 */
	public function isTaxonomyPublic( $taxonomy ) {
		$publicTaxonomies = $this->getPublicTaxonomies( true );

		return in_array( $taxonomy, $publicTaxonomies, true );
	}

	/**
	 * Returns noindexed object types of a given parent type.
	 *
	 * @since 4.0.0
	 *
	 * @param  string $type The parent object type ("postTypes" or "taxonomies").
	 * @return array        A list of noindexed objects types.
	 */
	private function getNoindexedObjects( $type ) {
		$noindexed = [];
		foreach ( aioseo()->dynamicOptions->searchAppearance->$type->all() as $name => $object ) {
			if (
				! $object['show'] ||
				( $object['advanced']['robotsMeta'] && ! $object['advanced']['robotsMeta']['default'] && $object['advanced']['robotsMeta']['noindex'] )
			) {
				$noindexed[] = $name;
			}
		}

		return $noindexed;
	}

	/**
	 * Returns all categories for a post.
	 *
	 * @since 4.1.4
	 *
	 * @param  int   $postId The post ID.
	 * @return array $names  The category names.
	 */
	public function getAllCategories( $postId = 0 ) {
		$names      = [];
		$categories = get_the_category( $postId );
		if ( $categories && count( $categories ) ) {
			foreach ( $categories as $category ) {
				$names[] = aioseo()->helpers->internationalize( $category->cat_name );
			}
		}

		return $names;
	}

	/**
	 * Returns all tags for a post.
	 *
	 * @since 4.1.4
	 *
	 * @param  int   $postId The post ID.
	 * @return array $names  The tag names.
	 */
	public function getAllTags( $postId = 0 ) {
		$names = [];

		$tags = get_the_tags( $postId );
		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			foreach ( $tags as $tag ) {
				if ( ! empty( $tag->name ) ) {
					$names[] = aioseo()->helpers->internationalize( $tag->name );
				}
			}
		}

		return $names;
	}

	/**
	 * Loads the translations for a given domain.
	 *
	 * @since 4.1.4
	 *
	 * @return void
	 */
	public function loadTextDomain( $domain ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Unload the domain in case WordPress has enqueued the translations for the site language instead of profile language.
		// Reloading the text domain will otherwise not override the existing loaded translations.
		unload_textdomain( $domain );

		$mofile = $domain . '-' . get_user_locale() . '.mo';
		load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $mofile );
	}

	/**
	 * Get the page builder the given Post ID was built with.
	 *
	 * @since 4.1.7
	 *
	 * @param  int         $postId The Post ID.
	 * @return bool|string         The page builder or false if not built with page builders.
	 */
	public function getPostPageBuilderName( $postId ) {
		foreach ( aioseo()->standalone->pageBuilderIntegrations as $integration => $pageBuilder ) {
			if ( $pageBuilder->isBuiltWith( $postId ) ) {
				return $integration;
			}
		}

		return false;
	}

	/**
	 * Checks if the current user can edit posts of the given post type.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $postType The name of the post type.
	 * @return bool             Whether the user can edit posts of the given post type.
	 */
	public function canEditPostType( $postType ) {
		$capabilities = $this->getPostTypeCapabilities( $postType );

		return current_user_can( $capabilities['edit_posts'] );
	}

	/**
	 * Returns a list of capabilities for the given post type.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $postType The name of the post type.
	 * @return array            The capabilities.
	 */
	public function getPostTypeCapabilities( $postType ) {
		static $capabilities = [];
		if ( isset( $capabilities[ $postType ] ) ) {
			return $capabilities[ $postType ];
		}

		$postTypeObject = get_post_type_object( $postType );
		if ( ! is_a( $postTypeObject, 'WP_Post_Type' ) ) {
			$capabilities[ $postType ] = [];

			return $capabilities[ $postType ];
		}

		$capabilityType = $postTypeObject->capability_type;
		if ( ! is_array( $capabilityType ) ) {
			$capabilityType = [
				$capabilityType,
				$capabilityType . 's'
			];
		}

		// Singular base for meta capabilities, plural base for primitive capabilities.
		list( $singularBase, $pluralBase ) = $capabilityType;

		$capabilities[ $postType ] = [
			'edit_post'          => 'edit_' . $singularBase,
			'read_post'          => 'read_' . $singularBase,
			'delete_post'        => 'delete_' . $singularBase,
			'edit_posts'         => 'edit_' . $pluralBase,
			'edit_others_posts'  => 'edit_others_' . $pluralBase,
			'delete_posts'       => 'delete_' . $pluralBase,
			'publish_posts'      => 'publish_' . $pluralBase,
			'read_private_posts' => 'read_private_' . $pluralBase,
		];

		return $capabilities[ $postType ];
	}

	/**
	 * Checks if the current user can edit terms of the given taxonomy.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $taxonomy The name of the taxonomy.
	 * @return bool             Whether the user can edit posts of the given taxonomy.
	 */
	public function canEditTaxonomy( $taxonomy ) {
		$capabilities = $this->getTaxonomyCapabilities( $taxonomy );

		return current_user_can( $capabilities['edit_terms'] );
	}

	/**
	 * Returns a list of capabilities for the given taxonomy.
	 *
	 * @since 4.1.9
	 *
	 * @param  string $postType The name of the taxonomy.
	 * @return array            The capabilities.
	 */
	public function getTaxonomyCapabilities( $taxonomy ) {
		static $capabilities = [];
		if ( isset( $capabilities[ $taxonomy ] ) ) {
			return $capabilities[ $taxonomy ];
		}

		$taxonomyObject = get_taxonomy( $taxonomy );
		if ( ! is_a( $taxonomyObject, 'WP_Taxonomy' ) ) {
			$capabilities[ $taxonomy ] = [];

			return $capabilities[ $taxonomy ];
		}

		$capabilities[ $taxonomy ] = (array) $taxonomyObject->cap;

		return $capabilities[ $taxonomy ];
	}

	/**
	 * Returns the charset for the site.
	 *
	 * @since 4.2.3
	 *
	 * @return string The name of the charset.
	 */
	public function getCharset() {
		static $charset = null;
		if ( null !== $charset ) {
			return $charset;
		}

		$charset = get_option( 'blog_charset' );
		$charset = $charset ? $charset : 'UTF-8';

		return $charset;
	}

	/**
	 * Returns the given data as JSON.
	 * We temporarily change the floating point precision in order to prevent rounding errors.
	 * Otherwise e.g. 4.9 could be output as 4.90000004.
	 *
	 * @since 4.2.7
	 *
	 * @param  mixed  $data  The data.
	 * @param  int    $flags The flags.
	 * @return string        The JSON output.
	 */
	public function wpJsonEncode( $data, $flags = 0 ) {
		$originalPrecision          = false;
		$originalSerializePrecision = false;
		if ( version_compare( PHP_VERSION, '7.1', '>=' ) ) {
			$originalPrecision          = ini_get( 'precision' );
			$originalSerializePrecision = ini_get( 'serialize_precision' );
			ini_set( 'precision', 17 );
			ini_set( 'serialize_precision', -1 );
		}

		$json = wp_json_encode( $data, $flags );

		if ( version_compare( PHP_VERSION, '7.1', '>=' ) ) {
			ini_set( 'precision', $originalPrecision );
			ini_set( 'serialize_precision', $originalSerializePrecision );
		}

		return $json;
	}
}