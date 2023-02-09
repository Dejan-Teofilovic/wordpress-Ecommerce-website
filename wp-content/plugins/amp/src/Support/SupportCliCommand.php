<?php
/**
 * CLI command for support request.
 *
 * @package AmpProject\AmpWP
 */

namespace AmpProject\AmpWP\Support;

use AmpProject\AmpWP\Infrastructure\CliCommand;
use AmpProject\AmpWP\Infrastructure\Injector;
use AmpProject\AmpWP\Infrastructure\Service;
use WP_CLI;
use function WP_CLI\Utils\get_flag_value;

/**
 * Service class for support.
 *
 * @internal
 * @since 2.2
 */
class SupportCliCommand implements Service, CliCommand {

	/**
	 * Injector.
	 *
	 * @var Injector
	 */
	private $injector;

	/**
	 * Class constructor.
	 *
	 * @param Injector $injector Injector.
	 */
	public function __construct( Injector $injector ) {

		$this->injector = $injector;
	}

	/**
	 * Get the name under which to register the CLI command.
	 *
	 * @return string The name under which to register the CLI command.
	 */
	public static function get_command_name() {

		return 'amp support';
	}

	/**
	 * Sends support data to endpoint.
	 *
	 * ## OPTIONS
	 *
	 * [--is-synthetic]
	 * : Whether or not it is synthetic data.
	 * ---
	 * default: false
	 * options:
	 *   - true
	 *   - false
	 *
	 * [--print]
	 * : To print support data.
	 * ---
	 * default: json-pretty
	 * options:
	 *   - json
	 *   - json-pretty
	 *
	 * [--endpoint=<string>]
	 * : Support endpoint. Where support data will send.
	 *
	 * [--urls=<urls>]
	 * : List of URL for which support data need to send. Use comma separator for multiple URLs.
	 *
	 * [--post_ids=<post_ids>]
	 * : List of Post for which support data need to send. Use comma separator for multiple post ids.
	 *
	 * [--term_ids=<term_ids>]
	 * : List of term for which support data need to send. Use comma separator for multiple term ids.
	 *
	 * ## EXAMPLES
	 *
	 *     wp amp support send-diagnostic
	 *
	 * @subcommand send-diagnostic
	 *
	 * @codeCoverageIgnore
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args Associative args.
	 */
	public function send_diagnostic( /** @noinspection PhpUnusedParameterInspection */ $args, $assoc_args ) {

		$is_print     = filter_var( get_flag_value( $assoc_args, 'print', false ), FILTER_SANITIZE_STRING );
		$is_synthetic = filter_var( get_flag_value( $assoc_args, 'is-synthetic', false ), FILTER_SANITIZE_STRING );
		$endpoint     = filter_var( get_flag_value( $assoc_args, 'endpoint', '' ), FILTER_SANITIZE_STRING );
		$endpoint     = untrailingslashit( $endpoint );

		$urls     = filter_var( get_flag_value( $assoc_args, 'urls', false ), FILTER_SANITIZE_STRING );
		$post_ids = filter_var( get_flag_value( $assoc_args, 'post_ids', false ), FILTER_SANITIZE_STRING );
		$term_ids = filter_var( get_flag_value( $assoc_args, 'term_ids', false ), FILTER_SANITIZE_STRING );

		$args = [
			'urls'         => ( ! empty( $urls ) ) ? explode( ',', $urls ) : [],
			'post_ids'     => ( ! empty( $post_ids ) ) ? explode( ',', $post_ids ) : [],
			'term_ids'     => ( ! empty( $term_ids ) ) ? explode( ',', $term_ids ) : [],
			'endpoint'     => $endpoint,
			'is_synthetic' => $is_synthetic,
		];

		$support_data = $this->injector->make( SupportData::class, [ 'args' => $args ] );
		$data         = $support_data->get_data();

		if ( $is_print ) {

			// Print the data.
			$print = strtolower( trim( $is_print ) );
			if ( 'json' === $print ) {
				echo wp_json_encode( $data ) . PHP_EOL;
			} else {
				echo wp_json_encode( $data, JSON_PRETTY_PRINT ) . PHP_EOL;
			}
		} else {

			$response = $support_data->send_data();

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				WP_CLI::warning( "Something went wrong: $error_message" );
			} elseif ( empty( $response['status'] ) || 'ok' !== $response['status'] ) {
				WP_CLI::warning( 'Failed to send diagnostic data.' );
			} elseif ( isset( $response['data']['uuid'] ) ) {
				WP_CLI::success( 'UUID : ' . $response['data']['uuid'] );
			}
		}

		/*
		 * Summary of data.
		 */
		$url_error_relationship = [];

		foreach ( $data['urls'] as $url ) {
			foreach ( $url['errors'] as $error ) {
				foreach ( $error['sources'] as $source ) {
					$url_error_relationship[] = $url['url'] . '-' . $error['error_slug'] . '-' . $source;
				}
			}
		}

		$plugin_count = count( $data['plugins'] );

		if ( $is_synthetic ) {
			$plugin_count_text = ( $plugin_count - 3 ) . " - Excluding common plugins of synthetic sites. ( $plugin_count - 3 )";
		} else {
			$plugin_count_text = $plugin_count;
		}

		$summary = [
			'Site URL'               => SupportData::get_home_url(),
			'Plugin count'           => $plugin_count_text,
			'Themes'                 => count( $data['themes'] ),
			'Errors'                 => count( array_values( $data['errors'] ) ),
			'Error Sources'          => count( array_values( $data['error_sources'] ) ),
			'Validated URL'          => count( array_values( $data['urls'] ) ),
			'URL Error Relationship' => count( array_values( $url_error_relationship ) ),
		];

		if ( $is_synthetic ) {
			$summary['Synthetic Data'] = 'Yes';
		}

		WP_CLI::log( sprintf( PHP_EOL . "%'=100s", '' ) );
		WP_CLI::log( 'Summary of AMP data' );
		WP_CLI::log( sprintf( "%'=100s", '' ) );
		foreach ( $summary as $key => $value ) {
			WP_CLI::log( sprintf( '%-25s : %s', $key, $value ) );
		}
		WP_CLI::log( sprintf( "%'=100s" . PHP_EOL, '' ) );
	}
}
