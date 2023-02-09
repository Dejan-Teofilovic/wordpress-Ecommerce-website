<?php
namespace AIOSEO\Plugin\Common\Schema;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains helper methods for our schema classes.
 *
 * @since 4.2.5
 */
class Helpers {
	/**
	 * Checks whether the schema markup feature is enabled.
	 *
	 * @since 4.2.5
	 *
	 * @return bool Whether the schema markup feature is enabled or not.
	 */
	public function isEnabled() {
		$isEnabled = ! in_array( 'enableSchemaMarkup', aioseo()->internalOptions->deprecatedOptions, true ) || aioseo()->options->deprecated->searchAppearance->global->schema->enableSchemaMarkup;

		return ! apply_filters( 'aioseo_schema_disable', ! $isEnabled );
	}

	/**
	 * Strips HTML and removes all blank properties in each of our graphs.
	 * Also parses properties that might contain smart tags.
	 *
	 * @since   4.0.13
	 * @version 4.2.5
	 *
	 * @param  array  $data      The graph data.
	 * @param  string $parentKey The key of the group parent (optional).
	 * @return array             The cleaned graph data.
	 */
	public function cleanAndParseData( $data, $parentKey = '' ) {
		foreach ( $data as $k => &$v ) {
			if ( is_array( $v ) ) {
				$v = $this->cleanAndParseData( $v, $k );
			} elseif ( is_numeric( $v ) || is_bool( $v ) ) {
				// Do nothing.
			} else {
				// Check if the prop can contain some HTML tags.
				if (
					isset( aioseo()->schema->htmlAllowedFields[ $parentKey ] ) &&
					in_array( $k, aioseo()->schema->htmlAllowedFields[ $parentKey ], true )
				) {
					$v = trim( wp_kses_post( $v ) );
				} else {
					$v = trim( wp_strip_all_tags( $v ) );
				}

				$v = aioseo()->tags->replaceTags( $v, get_the_ID() );
			}

			if ( empty( $v ) && ! in_array( $k, aioseo()->schema->nullableFields, true ) ) {
				unset( $data[ $k ] );
			} else {
				$data[ $k ] = $v;
			}
		}

		return $data;
	}

	/**
	 * Sorts the schema data and then returns it as JSON.
	 * We temporarily change the floating point precision in order to prevent rounding errors.
	 * Otherwise e.g. 4.9 could be output as 4.90000004.
	 *
	 * @since 4.2.7
	 *
	 * @param  array  $schema      The schema data.
	 * @param  bool   $isValidator Whether we're grabbing the output for the validator.
	 * @return string              The schema as JSON.
	 */
	public function getOutput( $schema, $isValidator = false ) {
		$schema['@graph'] = apply_filters( 'aioseo_schema_output', $schema['@graph'] );
		$schema['@graph'] = $this->cleanAndParseData( $schema['@graph'] );

		// Sort the graphs alphabetically.
		usort( $schema['@graph'], function ( $a, $b ) {
			return strcmp( $a['@type'], $b['@type'] );
		} );

		$json = isset( $_GET['aioseo-dev'] ) || $isValidator
			? aioseo()->helpers->wpJsonEncode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			: aioseo()->helpers->wpJsonEncode( $schema );

		return $json;
	}
}