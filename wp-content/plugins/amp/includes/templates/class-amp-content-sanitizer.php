<?php
/**
 * Class AMP_Content_Sanitizer
 *
 * @package AMP
 */

use AmpProject\Dom\Document;

/**
 * Class AMP_Content_Sanitizer
 *
 * @since 0.4.1
 * @internal
 */
class AMP_Content_Sanitizer {

	/**
	 * Sanitize _content_.
	 *
	 * @since 0.4.1
	 * @since 0.7 Passing return_styles=false in $global_args causes stylesheets to be returned instead of styles.
	 * @codeCoverageIgnore
	 * @deprecated Since 1.0
	 *
	 * @param string  $content HTML content string or DOM document.
	 * @param array[] $sanitizer_classes Sanitizers, with keys as class names and values as arguments.
	 * @param array   $global_args       Global args.
	 * @return array Tuple containing sanitized HTML, scripts array, and styles array (or stylesheets, if return_styles=false is passed in $global_args).
	 */
	public static function sanitize( $content, array $sanitizer_classes, $global_args = [] ) {
		$dom = AMP_DOM_Utils::get_dom_from_content( $content );

		// For back-compat.
		if ( ! isset( $global_args['return_styles'] ) ) {
			$global_args['return_styles'] = true;
		}

		$results = self::sanitize_document( $dom, $sanitizer_classes, $global_args );
		return [
			AMP_DOM_Utils::get_content_from_dom( $dom ),
			$results['scripts'],
			empty( $global_args['return_styles'] ) ? $results['stylesheets'] : $results['styles'],
		];
	}

	/**
	 * Sanitize document.
	 *
	 * @since 0.7
	 *
	 * @param Document $dom               HTML document.
	 * @param array[]  $sanitizer_classes Sanitizers, with keys as class names and values as arguments.
	 * @param array    $args              Global args passed into sanitizers.
	 * @return array {
	 *     Scripts and stylesheets needed by sanitizers.
	 *
	 *     @type array                $scripts     Scripts.
	 *     @type array                $stylesheets Stylesheets. If $args['return_styles'] is empty.
	 *     @type array                $styles      Styles. If $args['return_styles'] is not empty. For legacy purposes.
	 *     @type AMP_Base_Sanitizer[] $sanitizers  Sanitizers.
	 * }
	 */
	public static function sanitize_document( Document $dom, $sanitizer_classes, $args ) {
		$scripts     = [];
		$stylesheets = [];
		$styles      = [];

		$return_styles = ! empty( $args['return_styles'] );
		unset( $args['return_styles'] );

		/**
		 * Sanitizers.
		 *
		 * @var AMP_Base_Sanitizer[] $sanitizers
		 */
		$sanitizers = [];

		// Instantiate the sanitizers.
		foreach ( $sanitizer_classes as $sanitizer_class => $sanitizer_args ) {
			if ( ! class_exists( $sanitizer_class ) ) {
				/* translators: %s is sanitizer class */
				_doing_it_wrong( __METHOD__, sprintf( esc_html__( 'Sanitizer (%s) class does not exist', 'amp' ), esc_html( $sanitizer_class ) ), '0.4.1' );
				continue;
			}

			/**
			 * Sanitizer.
			 *
			 * @type AMP_Base_Sanitizer $sanitizer
			 */
			$sanitizer = new $sanitizer_class( $dom, array_merge( $args, $sanitizer_args ) );

			if ( ! $sanitizer instanceof AMP_Base_Sanitizer ) {
				_doing_it_wrong(
					__METHOD__,
					esc_html(
						sprintf(
							/* translators: 1: sanitizer class. 2: AMP_Base_Sanitizer */
							__( 'Sanitizer (%1$s) must extend `%2$s`', 'amp' ),
							esc_html( $sanitizer_class ),
							AMP_Base_Sanitizer::class
						)
					),
					'0.1'
				);
				continue;
			}

			$sanitizers[ $sanitizer_class ] = $sanitizer;
		}

		// Let the sanitizers know about each other prior to sanitizing.
		foreach ( $sanitizers as $sanitizer ) {
			$sanitizer->init( $sanitizers );
		}

		// Sanitize.
		$sanitizers_to_surface = [
			AMP_Style_Sanitizer::class,
			AMP_Tag_And_Attribute_Sanitizer::class,
		];
		foreach ( $sanitizers as $sanitizer_class => $sanitizer ) {
			/**
			 * Starts the server-timing measurement for an individual sanitizer.
			 *
			 * @since 2.0
			 * @internal
			 *
			 * @param string      $event_name        Name of the event to record.
			 * @param string|null $event_description Optional. Description of the event
			 *                                       to record. Defaults to null.
			 * @param string[]    $properties        Optional. Additional properties to add
			 *                                       to the logged record.
			 * @param bool        $verbose_only      Optional. Whether to only show the
			 *                                       event in verbose mode. Defaults to
			 *                                       false.
			 */
			do_action(
				'amp_server_timing_start',
				strtolower( $sanitizer_class ),
				'',
				[],
				! in_array( $sanitizer_class, $sanitizers_to_surface, true )
			);

			$sanitizer->sanitize();

			$scripts = array_merge( $scripts, $sanitizer->get_scripts() );
			if ( $return_styles ) {
				$styles = array_merge( $styles, $sanitizer->get_styles() );
			} else {
				$stylesheets = array_merge( $stylesheets, $sanitizer->get_stylesheets() );
			}

			/**
			 * Stops the server-timing measurement for an individual sanitizer.
			 *
			 * @since 2.0
			 * @internal
			 *
			 * @param string $event_name Name of the event to stop.
			 */
			do_action(
				'amp_server_timing_stop',
				strtolower( $sanitizer_class )
			);
		}

		return compact( 'scripts', 'styles', 'stylesheets', 'sanitizers' );
	}
}
