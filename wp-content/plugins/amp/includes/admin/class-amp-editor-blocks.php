<?php
/**
 * AMP Editor Blocks extending.
 *
 * @package AMP
 * @since 1.0
 */

/**
 * Class AMP_Editor_Blocks
 *
 * @todo Remove this when AMP-specific blocks are removed. They have been deprecated as of <https://github.com/ampproject/amp-wp/issues/4556>.
 * @internal
 */
class AMP_Editor_Blocks {

	/**
	 * List of AMP scripts that need to be printed when AMP components are used in non-AMP document context ("dirty AMP").
	 *
	 * @var array
	 */
	public $content_required_amp_scripts = [];

	/**
	 * AMP components that have blocks.
	 *
	 * @var string[]
	 */
	const AMP_BLOCKS = [
		'amp-mathml',
		'amp-timeago',
		'amp-o2-player',
		'amp-ooyala-player',
		'amp-reach-player',
		'amp-springboard-player',
		'amp-jwplayer',
		'amp-brid-player',
		'amp-ima-video',
	];

	/**
	 * Init.
	 */
	public function init() {
		if ( function_exists( 'register_block_type' ) ) {
			add_filter( 'wp_kses_allowed_html', [ $this, 'include_block_atts_in_wp_kses_allowed_html' ], 10, 2 );

			/*
			 * Dirty AMP is required when a site is in AMP-first mode but not all templates are being served
			 * as AMP. In particular, if a single post is using AMP-specific Gutenberg Blocks which make
			 * use of AMP components, and the singular template is served as AMP but the blog page is not,
			 * then the non-AMP blog page need to load the AMP runtime scripts so that the AMP components
			 * in the posts displayed there will be rendered properly. This is only relevant on AMP-first
			 * sites because the AMP Gutenberg blocks are only made available in that mode; they are not
			 * presented in the Gutenberg inserter in transitional mode. In general, using AMP components in
			 * non-AMP documents is still not officially supported, so it's occurrence is being minimized
			 * as much as possible. For more, see <https://github.com/ampproject/amp-wp/issues/1192>.
			 */
			if ( amp_is_canonical() ) {
				add_filter( 'the_content', [ $this, 'tally_content_requiring_amp_scripts' ] );
				add_action( 'wp_print_footer_scripts', [ $this, 'print_dirty_amp_scripts' ] );
			}
		}
	}

	/**
	 * Allowlist elements and attributes used for AMP.
	 *
	 * This prevents AMP markup from being deleted when the user doesn't have the `unfiltered_html` capability.
	 *
	 * @param array  $tags    Array of allowed post tags.
	 * @param string $context Context.
	 * @return mixed Modified array.
	 */
	public function include_block_atts_in_wp_kses_allowed_html( $tags, $context ) {
		if ( 'post' !== $context ) {
			return $tags;
		}

		foreach ( self::AMP_BLOCKS as $amp_block ) {
			if ( ! isset( $tags[ $amp_block ] ) ) {
				$tags[ $amp_block ] = [];
			}

			// @todo The global attributes included here should be matched up with what is actually used by each block.
			$tags[ $amp_block ] = array_merge(
				array_fill_keys(
					[
						'layout',
						'width',
						'height',
						'class',
					],
					true
				),
				$tags[ $amp_block ]
			);

			$amp_tag_specs = AMP_Allowed_Tags_Generated::get_allowed_tag( $amp_block );
			foreach ( $amp_tag_specs as $amp_tag_spec ) {
				if ( ! isset( $amp_tag_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ] ) ) {
					continue;
				}
				$tags[ $amp_block ] = array_merge(
					$tags[ $amp_block ],
					array_fill_keys( array_keys( $amp_tag_spec[ AMP_Rule_Spec::ATTR_SPEC_LIST ] ), true )
				);
			}
		}

		return $tags;
	}

	/**
	 * Tally the AMP component scripts that are needed in a dirty AMP document.
	 *
	 * @param string $content Content.
	 * @return string Content (unmodified).
	 */
	public function tally_content_requiring_amp_scripts( $content ) {
		if ( ! amp_is_request() ) {
			$pattern = sprintf( '/<(%s)\b.*?>/s', implode( '|', self::AMP_BLOCKS ) );
			if ( preg_match_all( $pattern, $content, $matches ) ) {
				$this->content_required_amp_scripts = array_merge(
					$this->content_required_amp_scripts,
					$matches[1]
				);
			}
		}
		return $content;
	}

	/**
	 * Print AMP scripts required for AMP components used in a non-AMP document (dirty AMP).
	 */
	public function print_dirty_amp_scripts() {
		if ( ! amp_is_request() && ! empty( $this->content_required_amp_scripts ) ) {
			wp_scripts()->do_items( $this->content_required_amp_scripts );
		}
	}
}
