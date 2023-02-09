<?php
/**
 * Class AMP_Img_Sanitizer.
 *
 * @package AMP
 */

use AmpProject\AmpWP\ValidationExemption;
use AmpProject\DevMode;
use AmpProject\Extension;
use AmpProject\Dom\Element;
use AmpProject\Html\Attribute;
use AmpProject\Html\Tag;
use AmpProject\Layout;

/**
 * Class AMP_Img_Sanitizer
 *
 * Converts <img> tags to <amp-img> or <amp-anim>
 *
 * @internal
 */
class AMP_Img_Sanitizer extends AMP_Base_Sanitizer {
	use AMP_Noscript_Fallback;

	/**
	 * Value used for width attribute when $attributes['width'] is empty.
	 *
	 * @since 0.2
	 *
	 * @const int
	 */
	const FALLBACK_WIDTH = 600;

	/**
	 * Value used for height attribute when $attributes['height'] is empty.
	 *
	 * @since 0.2
	 *
	 * @const int
	 */
	const FALLBACK_HEIGHT = 400;

	/**
	 * Tag.
	 *
	 * @var string HTML <img> tag to identify and replace with AMP version.
	 *
	 * @since 0.2
	 */
	public static $tag = 'img';

	/**
	 * Default args.
	 *
	 * @var array
	 */
	protected $DEFAULT_ARGS = [
		'add_noscript_fallback' => true,
		'native_img_used'       => false,
		'allow_picture'         => false,
	];

	/**
	 * Animation extension.
	 *
	 * @var string
	 */
	private static $anim_extension = '.gif';

	/**
	 * Get mapping of HTML selectors to the AMP component selectors which they may be converted into.
	 *
	 * @return array Mapping.
	 */
	public function get_selector_conversion_mapping() {
		if ( $this->args['native_img_used'] ) {
			return [];
		}
		return [
			Tag::IMG => [
				'amp-img',
				'amp-anim',
			],
		];
	}

	/**
	 * Convert picture element into image element or mark as px verified.
	 *
	 * @return void
	 */
	protected function process_picture_elements() {

		$picture_img_query = $this->dom->xpath->query( '//picture/img' );

		/** @var Element $img_element */
		foreach ( $picture_img_query as $img_element ) {
			/** @var Element $picture_element */
			$picture_element = $img_element->parentNode;

			if ( true === $this->args['allow_picture'] ) {
				ValidationExemption::mark_node_as_px_verified( $picture_element );
				foreach ( $picture_element->getElementsByTagName( Tag::SOURCE ) as $source_element ) {
					ValidationExemption::mark_node_as_px_verified( $source_element );

					// Mark width/height attributes as PX-verified as well since they aren't known yet in the validator. See <https://github.com/whatwg/html/pull/5894>.
					foreach ( [ Attribute::WIDTH, Attribute::HEIGHT ] as $dimension_attr ) {
						$attr_node = $source_element->getAttributeNode( $dimension_attr );
						if ( $attr_node instanceof DOMAttr ) {
							ValidationExemption::mark_node_as_px_verified( $attr_node );
						}
					}
				}
			} else {
				$picture_element->removeChild( $img_element );
				$picture_element->parentNode->replaceChild( $img_element, $picture_element );
			}
		}
	}

	/**
	 * Sanitize the <img> elements from the HTML contained in this instance's Dom\Document.
	 *
	 * @since 0.2
	 */
	public function sanitize() {

		$this->process_picture_elements();

		/**
		 * Node list.
		 *
		 * @var DOMNodeList $nodes
		 */
		$nodes           = $this->dom->getElementsByTagName( self::$tag );
		$need_dimensions = [];

		$num_nodes = $nodes->length;

		if ( 0 === $num_nodes ) {
			return;
		}

		if ( $this->args['add_noscript_fallback'] && ! $this->args['native_img_used'] ) {
			$this->initialize_noscript_allowed_attributes( self::$tag );
		}

		for ( $i = $num_nodes - 1; $i >= 0; $i-- ) {
			$node = $nodes->item( $i );
			if ( ! $node instanceof Element || DevMode::hasExemptionForNode( $node ) ) {
				continue;
			}

			// Skip element if already inside of an AMP element as a noscript fallback or is a child of `amp-story-player`.
			if (
				$this->is_inside_amp_noscript( $node )
				||
				(
					$node->parentNode instanceof Element
					&&
					(
						Tag::A === $node->parentNode->tagName
						&&
						$node->parentNode->parentNode instanceof Element
						&&
						Extension::STORY_PLAYER === $node->parentNode->parentNode->tagName
					)
				)
			) {
				continue;
			}

			if ( ! $node->hasAttribute( Attribute::SRC ) || '' === trim( $node->getAttribute( Attribute::SRC ) ) ) {
				$this->remove_invalid_child(
					$node,
					[
						'code'       => AMP_Tag_And_Attribute_Sanitizer::ATTR_REQUIRED_BUT_MISSING,
						'attributes' => [ Attribute::SRC ],
						'spec_name'  => 'amp-img',
					]
				);
				continue;
			}

			// Replace img with amp-pixel when dealing with tracking pixels.
			if ( self::is_tracking_pixel_url( $node->getAttribute( Attribute::SRC ) ) ) {
				$attributes = [
					Attribute::SRC    => $node->getAttribute( Attribute::SRC ),
					Attribute::LAYOUT => Layout::NODISPLAY,
				];
				foreach ( [ Attribute::REFERRERPOLICY ] as $allowed_attribute ) {
					if ( $node->hasAttribute( $allowed_attribute ) ) {
						$attributes[ $allowed_attribute ] = $node->getAttribute( $allowed_attribute );
					}
				}
				$amp_pixel_node = AMP_DOM_Utils::create_node(
					$this->dom,
					Extension::PIXEL,
					$attributes
				);
				$node->parentNode->replaceChild( $amp_pixel_node, $node );
				continue;
			}

			// Short-circuit emoji images from needing to make requests out to https://s.w.org/.
			if ( 'wp-smiley' === $node->getAttribute( Attribute::CLASS_ ) ) {
				$node->setAttribute( Attribute::WIDTH, '72' );
				$node->setAttribute( Attribute::HEIGHT, '72' );
				if ( ! $this->args['native_img_used'] ) {
					$node->setAttribute( Attribute::NOLOADING, '' );
				}
			}

			if ( $node->hasAttribute( 'data-amp-layout' ) ) {
				$layout = $node->getAttribute( 'data-amp-layout' );
			} elseif ( $node->hasAttribute( Attribute::LAYOUT ) ) {
				$layout = $node->getAttribute( Attribute::LAYOUT );
			} else {
				$layout = Layout::INTRINSIC;
			}

			$has_width  = is_numeric( $node->getAttribute( Attribute::WIDTH ) );
			$has_height = is_numeric( $node->getAttribute( Attribute::HEIGHT ) );

			// Determine which images need their dimensions determined/extracted.
			$missing_dimensions = (
				( ! $has_height && Layout::FIXED_HEIGHT === $layout )
				||
				(
					( ! $has_width || ! $has_height )
					&&
					in_array( $layout, [ Layout::FIXED, Layout::RESPONSIVE, Layout::INTRINSIC ], true )
				)
			);
			if ( $missing_dimensions ) {
				$need_dimensions[ $node->getAttribute( Attribute::SRC ) ][] = $node;
			} else {
				$this->adjust_and_replace_node( $node );
			}
		}

		$this->determine_dimensions( $need_dimensions );
		$this->adjust_and_replace_nodes_in_array_map( $need_dimensions );
	}

	/**
	 * "Filter" HTML attributes for <amp-anim> elements.
	 *
	 * @since 0.2
	 *
	 * @param string[] $attributes {
	 *      Attributes.
	 *
	 *      @type string $src Image URL - Pass along if found
	 *      @type string $alt <img> `alt` attribute - Pass along if found
	 *      @type string $class <img> `class` attribute - Pass along if found
	 *      @type string $srcset <img> `srcset` attribute - Pass along if found
	 *      @type string $sizes <img> `sizes` attribute - Pass along if found
	 *      @type string $on <img> `on` attribute - Pass along if found
	 *      @type string $attribution <img> `attribution` attribute - Pass along if found
	 *      @type int $width <img> width attribute - Set to numeric value if px or %
	 *      @type int $height <img> width attribute - Set to numeric value if px or %
	 * }
	 * @return array Returns HTML attributes; removes any not specifically declared above from input.
	 */
	private function filter_attributes( $attributes ) {
		$out = [];

		foreach ( $attributes as $name => $value ) {
			switch ( $name ) {
				case Attribute::WIDTH:
				case Attribute::HEIGHT:
					$out[ $name ] = $this->sanitize_dimension( $value, $name );
					break;

				case 'data-amp-layout':
					$out['layout'] = $value;
					break;

				case 'data-amp-noloading':
					$out['noloading'] = $value;
					break;

				// Skip directly copying new web platform attributes from img to amp-img which are largely handled by AMP already.
				case Attribute::INTRINSICSIZE: // Responsive images handled by amp-img directly.
					break;

				case Attribute::LOADING: // Lazy-loading handled by amp-img natively.
					if ( 'lazy' !== strtolower( $value ) ) {
						$out[ $name ] = $value;
					}
					break;

				case Attribute::DECODING: // Async decoding handled by AMP.
					if ( 'async' !== strtolower( $value ) ) {
						$out[ $name ] = $value;
					}
					break;

				default:
					$out[ $name ] = $value;
					break;
			}
		}

		return $out;
	}

	/**
	 * Determine width and height attribute values for images without them.
	 *
	 * Attempt to determine actual dimensions, otherwise set reasonable defaults.
	 *
	 * @param Element[][] $need_dimensions Map <img> @src URLs to node for images with missing dimensions.
	 */
	private function determine_dimensions( $need_dimensions ) {

		$dimensions_by_url = AMP_Image_Dimension_Extractor::extract( array_keys( $need_dimensions ) );

		foreach ( $dimensions_by_url as $url => $dimensions ) {
			foreach ( $need_dimensions[ $url ] as $node ) {
				if ( ! $node instanceof Element ) {
					continue;
				}
				$class = $node->getAttribute( Attribute::CLASS_ );
				if ( ! $class ) {
					$class = '';
				}
				if ( ! $dimensions ) {
					$class .= ' amp-wp-unknown-size';
				}

				$width  = isset( $this->args['content_max_width'] ) ? $this->args['content_max_width'] : self::FALLBACK_WIDTH;
				$height = self::FALLBACK_HEIGHT;
				if ( ! empty( $dimensions['width'] ) ) {
					$width = $dimensions['width'];
				}
				if ( ! empty( $dimensions['height'] ) ) {
					$height = $dimensions['height'];
				}

				if ( ! is_numeric( $node->getAttribute( Attribute::WIDTH ) ) ) {

					// Let width have the right aspect ratio based on the height attribute.
					if (
						is_numeric( $node->getAttribute( Attribute::HEIGHT ) )
						&&
						! empty( $dimensions['height'] )
						&&
						! empty( $dimensions['width'] )
					) {
						$width = ( (float) $node->getAttribute( Attribute::HEIGHT ) * $dimensions['width'] ) / $dimensions['height'];
					}

					$node->setAttribute( Attribute::WIDTH, $width );
					if ( empty( $dimensions['width'] ) ) {
						$class .= ' amp-wp-unknown-width';
					}
				}
				if ( ! is_numeric( $node->getAttribute( Attribute::HEIGHT ) ) ) {

					// Let height have the right aspect ratio based on the width attribute.
					if (
						is_numeric( $node->getAttribute( Attribute::WIDTH ) )
						&&
						! empty( $dimensions['height'] )
						&&
						! empty( $dimensions['width'] )
					) {
						$height = ( (float) $node->getAttribute( Attribute::WIDTH ) * $dimensions['height'] ) / $dimensions['width'];
					}

					$node->setAttribute( Attribute::HEIGHT, $height );
					if ( empty( $dimensions['height'] ) ) {
						$class .= ' amp-wp-unknown-height';
					}
				}
				$node->setAttribute( Attribute::CLASS_, trim( $class ) );
			}
		}
	}

	/**
	 * Now that all images have width and height attributes, make final tweaks and replace original image nodes
	 *
	 * @param DOMNodeList[] $node_lists Img DOM nodes (now with width and height attributes).
	 */
	private function adjust_and_replace_nodes_in_array_map( $node_lists ) {
		foreach ( $node_lists as $node_list ) {
			foreach ( $node_list as $node ) {
				$this->adjust_and_replace_node( $node );
			}
		}
	}

	/**
	 * Make final modifications to DOMNode
	 *
	 * @param Element $node The img element to adjust and replace.
	 */
	private function adjust_and_replace_node( Element $node ) {
		if ( $this->args['native_img_used'] || ( $node->parentNode instanceof Element && Tag::PICTURE === $node->parentNode->tagName ) ) {
			$attributes = $this->maybe_add_lightbox_attributes( [], $node ); // @todo AMP doesn't support lightbox on <img> yet.

			// Set decoding=async by default. See <https://core.trac.wordpress.org/ticket/53232>.
			if ( ! $node->hasAttribute( Attribute::DECODING ) ) {
				$attributes[ Attribute::DECODING ] = 'async';
			}

			// @todo This class should really only be added if we actually have to provide dimensions.
			$attributes[ Attribute::CLASS_ ] = (string) $node->getAttribute( Attribute::CLASS_ );
			if ( ! empty( $attributes[ Attribute::CLASS_ ] ) ) {
				$attributes[ Attribute::CLASS_ ] .= ' ';
			}
			$attributes[ Attribute::CLASS_ ] .= 'amp-wp-enforced-sizes';

			foreach ( $attributes as $name => $value ) {
				$node->setAttribute( $name, $value );
			}
			return;
		}

		$amp_data       = $this->get_data_amp_attributes( $node );
		$old_attributes = AMP_DOM_Utils::get_node_attributes_as_assoc_array( $node );
		$old_attributes = $this->filter_data_amp_attributes( $old_attributes, $amp_data );
		$old_attributes = $this->maybe_add_lightbox_attributes( $old_attributes, $node );

		$new_attributes = $this->filter_attributes( $old_attributes );
		$layout         = isset( $amp_data[ Attribute::LAYOUT ] ) ? $amp_data[ Attribute::LAYOUT ] : false;
		$new_attributes = $this->filter_attachment_layout_attributes( $node, $new_attributes, $layout );

		$this->add_or_append_attribute( $new_attributes, Attribute::CLASS_, 'amp-wp-enforced-sizes' );
		if ( empty( $new_attributes[ Attribute::LAYOUT ] ) && ! empty( $new_attributes[ Attribute::HEIGHT ] ) && ! empty( $new_attributes[ Attribute::WIDTH ] ) ) {
			// Use responsive images when a theme supports wide and full-bleed images.
			if (
				! empty( $this->args['align_wide_support'] )
				&& $node->parentNode instanceof Element
				&& 'figure' === $node->parentNode->nodeName
				&& preg_match( '/(^|\s)(alignwide|alignfull)(\s|$)/', $node->parentNode->getAttribute( Attribute::CLASS_ ) )
			) {
				$new_attributes[ Attribute::LAYOUT ] = Layout::RESPONSIVE;
			} else {
				$new_attributes[ Attribute::LAYOUT ] = Layout::INTRINSIC;
			}
		}

		if ( isset( $new_attributes[ Attribute::SIZES ] ) ) {
			$new_attributes[ Attribute::DISABLE_INLINE_WIDTH ] = '';
		}

		if ( $this->is_gif_url( $new_attributes['src'] ) ) {
			$this->did_convert_elements = true;

			$new_tag = 'amp-anim';
		} else {
			$new_tag = 'amp-img';
		}

		// Remove ID since it would be a duplicate and because if it is not removed before replacing the element with
		// another element that has the same ID, the removed element would still get returned by getElementById even
		// when it is no longer in the Document.
		$node->removeAttribute( Attribute::ID );

		$img_node = AMP_DOM_Utils::create_node( $this->dom, $new_tag, $new_attributes );
		$node->parentNode->replaceChild( $img_node, $node );

		/*
		 * Prevent inline style on an image from rendering the amp-img invisible or conflicting with the required display.
		 * This could eventually be expanded to fixup inline styles for elements other than images, but the reality
		 * is that this is not going to completely solve the problem for images as well, since it will not handle the
		 * case where an image gets a display:inline style via a style rule.
		 * See <https://github.com/ampproject/amp-wp/issues/1803>.
		 */
		if ( $img_node->hasAttribute( Attribute::STYLE ) ) {
			$layout = $img_node->getAttribute( Attribute::LAYOUT );
			if ( in_array( $layout, [ Layout::FIXED_HEIGHT, Layout::RESPONSIVE, Layout::FILL, Layout::FLEX_ITEM ], true ) ) {
				$required_display = 'block';
			} elseif ( Layout::NODISPLAY === $layout ) {
				$required_display = 'none';
			} else {
				// This is also the default for any AMP element (.i-amphtml-element).
				$required_display = 'inline-block';
			}
			$img_node->setAttribute(
				Attribute::STYLE,
				preg_replace(
					'/\bdisplay\s*:\s*[a-z\-]+\b/',
					"display:$required_display",
					$img_node->getAttribute( Attribute::STYLE )
				)
			);
		}

		if ( $this->args['add_noscript_fallback'] ) {
			// Preserve original node in noscript for no-JS environments.
			$this->append_old_node_noscript( $img_node, $node, $this->dom );
		}
	}

	/**
	 * Set lightbox attributes.
	 *
	 * @param array   $attributes Array of attributes.
	 * @param DomNode $node Array of AMP attributes.
	 * @return array Updated attributes.
	 */
	private function maybe_add_lightbox_attributes( $attributes, $node ) {
		$parent_node = $node->parentNode;
		if ( ! ( $parent_node instanceof Element ) || ! ( $parent_node->parentNode instanceof Element ) ) {
			return $attributes;
		}

		$is_file_url                        = preg_match( '/\.\w+$/', wp_parse_url( $parent_node->getAttribute( Attribute::HREF ), PHP_URL_PATH ) );
		$is_node_wrapped_in_media_file_link = (
			(
				'a' === $parent_node->tagName
				||
				( 'figure' === $parent_node->tagName || 'figure' === $parent_node->parentNode->tagName )
			)
			&&
			$is_file_url // This should be a link to the media file, not the attachment page.
		);

		if ( 'figure' !== $parent_node->tagName && ! $is_node_wrapped_in_media_file_link ) {
			return $attributes;
		}

		// Account for blocks that include alignment or images that are wrapped in <a>.
		// With alignment, the structure changes from figure.wp-block-image > img
		// to div.wp-block-image > figure > img and the amp-lightbox attribute
		// can be found on the wrapping div instead of the figure element.
		$grand_parent = $parent_node->parentNode;
		if ( $this->does_node_have_block_class( $grand_parent ) ) {
			$parent_node = $grand_parent;
		} elseif ( isset( $grand_parent->parentNode ) && $this->does_node_have_block_class( $grand_parent->parentNode ) ) {
			$parent_node = $grand_parent->parentNode;
		}

		$parent_attributes = AMP_DOM_Utils::get_node_attributes_as_assoc_array( $parent_node );

		if ( isset( $parent_attributes['data-amp-lightbox'] ) && true === filter_var( $parent_attributes['data-amp-lightbox'], FILTER_VALIDATE_BOOLEAN ) ) {
			$attributes['data-amp-lightbox']   = '';
			$attributes[ Attribute::LIGHTBOX ] = '';

			/*
			 * Removes the <a> if the image is wrapped in one, as it can prevent the lightbox from working.
			 * But this only removes the <a> if it links to the media file, not the attachment page.
			 */
			if ( $is_node_wrapped_in_media_file_link ) {
				$node->parentNode->parentNode->replaceChild( $node, $node->parentNode );
			}
		}

		return $attributes;
	}

	/**
	 * Gets whether a node has the class 'wp-block-image', meaning it is a wrapper for an Image block.
	 *
	 * @param Element $node A node to evaluate.
	 * @return bool Whether the node has the class 'wp-block-image'.
	 */
	private function does_node_have_block_class( $node ) {
		if ( $node instanceof Element ) {
			$classes = preg_split( '/\s+/', $node->getAttribute( Attribute::CLASS_ ) );
			if ( in_array( 'wp-block-image', $classes, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determines if a URL is considered a GIF URL
	 *
	 * @since 0.2
	 *
	 * @param string $url URL to inspect for GIF vs. JPEG or PNG.
	 *
	 * @return bool Returns true if $url ends in `.gif`
	 */
	private function is_gif_url( $url ) {
		$ext  = self::$anim_extension;
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return substr( $path, -strlen( $ext ) ) === $ext;
	}

	/**
	 * Determines if a URL is a known tracking pixel URL.
	 *
	 * Currently, only Facebook tracking pixel URL is detected.
	 *
	 * @since 2.2.2
	 *
	 * @param string $url URL to inspect.
	 *
	 * @return bool Returns true if $url is a tracking pixel URL.
	 */
	private static function is_tracking_pixel_url( $url ) {
		$parsed_url = wp_parse_url( $url );

		return (
			isset( $parsed_url['host'], $parsed_url['path'] )
			&&
			'facebook.com' === str_replace( 'www.', '', $parsed_url['host'] )
			&&
			'/tr' === $parsed_url['path']
		);
	}
}
