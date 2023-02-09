<?php
namespace AIOSEO\Plugin\Lite\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIOSEO\Plugin\Common\Admin as CommonAdmin;

/**
 * Abstract class that Pro and Lite both extend.
 *
 * @since 4.0.0
 */
class PostSettings extends CommonAdmin\PostSettings {
	/**
	 * Initialize the admin.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Add upsell to terms.
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function init() {
		if ( is_admin() ) {
			$taxonomies = aioseo()->helpers->getPublicTaxonomies();
			foreach ( $taxonomies as $taxonomy ) {
				add_action( $taxonomy['name'] . '_edit_form', [ $this, 'addTaxonomyUpsell' ] );
				add_action( 'after-' . $taxonomy['name'] . '-table', [ $this, 'addTaxonomyUpsell' ] );
			}
		}
	}

	/**
	 * Add Taxonomy Upsell
	 *
	 * @since 4.0.0
	 *
	 * @return void
	 */
	public function addTaxonomyUpsell() {
		$screen = get_current_screen();
		if (
			! isset( $screen->parent_base ) ||
			'edit' !== $screen->parent_base ||
			empty( $screen->taxonomy )
		) {
			return;
		}

		include_once AIOSEO_DIR . '/app/Lite/Views/taxonomy-upsell.html';
	}
}