<?php
namespace AIOSEO\Plugin\Common\Standalone;

use AIOSEO\Plugin\Pro\Standalone as ProStandalone;

/**
 * Registers the standalone components.
 *
 * @since 4.2.2
 */
class UserProfileTab {
	/**
	 * Class constructor.
	 *
	 * @since 4.2.2
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueueScript' ] );
		add_action( 'profile_update', [ $this, 'updateUserSocialProfiles' ], 10, 1 );
	}

	/**
	 * Enqueues the script.
	 *
	 * @since 4.2.2
	 *
	 * @return void
	 */
	public function enqueueScript() {
		if ( apply_filters( 'aioseo_user_profile_tab_disable', false ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! in_array( $screen->id, [ 'user-edit', 'profile' ], true ) ) {
			if ( 'follow-up_page_followup-emails-reports' === $screen->id ) {
				aioseo()->core->assets->load( 'src/vue/standalone/user-profile-tab/follow-up-emails-nav-bar.js' );
			}

			return;
		}

		global $user_id;
		if ( ! intval( $user_id ) ) {
			return;
		}

		aioseo()->core->assets->load( 'src/vue/standalone/user-profile-tab/main.js', [], $this->getVueData() );
	}

	/**
	 * Returns the data Vue requires.
	 *
	 * @since 4.2.2
	 *
	 * @return array
	 */
	private function getVueData() {
		global $user_id;

		$socialProfiles = $this->getSocialProfiles();
		foreach ( $socialProfiles as $platformKey => $v ) {
			$metaName                        = 'aioseo_' . aioseo()->helpers->toSnakeCase( $platformKey );
			$socialProfiles[ $platformKey ] = get_user_meta( $user_id, $metaName, true );
		}

		$sameUsername = get_user_meta( $user_id, 'aioseo_profiles_same_username', true );
		if ( empty( $sameUsername ) ) {
			$sameUsername = [
				'enable'   => false,
				'username' => '',
				'included' => [ 'facebookPageUrl', 'twitterUrl', 'pinterestUrl', 'instagramUrl', 'youtubeUrl', 'linkedinUrl' ] // Same as in Options.php.
			];
		}

		$additionalurls = get_user_meta( $user_id, 'aioseo_profiles_additional_urls', true );

		$extraVueData = [
			'userProfile' => [
				'userData'                          => get_userdata( $user_id )->data,
				'profiles'                          => [
					'sameUsername'   => $sameUsername,
					'urls'           => $socialProfiles,
					'additionalUrls' => $additionalurls
				],
				'isWooCommerceFollowupEmailsActive' => aioseo()->helpers->isWooCommerceFollowupEmailsActive()
			]
		];

		$vueData = aioseo()->helpers->getVueData();
		$vueData = array_merge( $vueData, $extraVueData );

		return $vueData;
	}

	/**
	 * Updates the user social profile URLs when a user's profile is updated.
	 *
	 * @since 4.2.2
	 *
	 * @param  int  $userId The user ID.
	 * @return void
	 */
	public function updateUserSocialProfiles( $userId ) {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $userId ) ) {
			return;
		}

		if ( empty( $_POST['aioseo-user-social-profiles'] ) ) {
			return;
		}

		$data = json_decode( sanitize_text_field( wp_unslash( $_POST['aioseo-user-social-profiles'] ) ), true );
		if ( empty( $data ) ) {
			return;
		}

		$sanitizedIncluded = [];
		foreach ( $data['sameUsername']['included'] as $platformKey ) {
			$sanitizedIncluded[] = sanitize_text_field( $platformKey );
		}

		$sanitizedSameUsernameData = [
			'enable'   => (bool) $data['sameUsername']['enable'],
			'username' => sanitize_text_field( $data['sameUsername']['username'] ),
			'included' => array_filter( $sanitizedIncluded )
		];

		update_user_meta( $userId, 'aioseo_profiles_same_username', $sanitizedSameUsernameData );

		foreach ( $data['urls'] as $platformKey => $value ) {
			$value    = sanitize_text_field( $value );
			$metaName = 'aioseo_' . aioseo()->helpers->toSnakeCase( $platformKey );
			update_user_meta( $userId, $metaName, $value );
		}

		$additionalUrls          = sanitize_text_field( $data['additionalUrls'] );
		$sanitizedAdditionalUrls = preg_replace( '/\h/', "\n", $additionalUrls );
		update_user_meta( $userId, 'aioseo_profiles_additional_urls', $sanitizedAdditionalUrls );
	}

	/**
	 * Returns a list of supported social profiles.
	 *
	 * @since 4.2.2
	 *
	 * @return array
	 */
	public function getSocialProfiles() {
		return [
			'facebookPageUrl' => '',
			'twitterUrl'      => '',
			'instagramUrl'    => '',
			'pinterestUrl'    => '',
			'youtubeUrl'      => '',
			'linkedinUrl'     => '',
			'tumblrUrl'       => '',
			'yelpPageUrl'     => '',
			'soundCloudUrl'   => '',
			'wikipediaUrl'    => '',
			'myspaceUrl'      => ''
		];
	}
}