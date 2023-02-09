<?php
namespace AIOSEO\Plugin\Common\Schema;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds our schema.
 *
 * @since 4.0.0
 */
class Schema {
	/**
	 * The graphs that need to be generated.
	 *
	 * @since 4.2.5
	 *
	 * @var array
	 */
	public $graphs = [];

	/**
	 * The context data.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 */
	public $context = [];

	/**
	 * Helpers class instance.
	 *
	 * @since 4.2.7
	 *
	 * @var Helpers
	 */
	public $helpers = null;

	/**
	 * The subdirectories that contain graph classes.
	 *
	 * @since 4.2.5
	 *
	 * @var array
	 */
	protected $graphSubDirectories = [
		'Article',
		'KnowledgeGraph',
		'WebPage'
	];

	/**
	 * All existing WebPage graphs.
	 *
	 * @since 4.0.0
	 *
	 * @var array
	 */
	public $webPageGraphs = [
		'WebPage',
		'AboutPage',
		'CheckoutPage',
		'CollectionPage',
		'ContactPage',
		'FAQPage',
		'ItemPage',
		'MedicalWebPage',
		'ProfilePage',
		'RealEstateListing',
		'SearchResultsPage'
	];

	/**
	 * Fields that can be 0 or null, which shouldn't be stripped when cleaning the data.
	 *
	 * @since 4.1.2
	 *
	 * @var array
	 */
	public $nullableFields = [
		'price', // Needs to be 0 if free for Software Application.
		'value' // Needs to be 0 if free for product shipping details.
	];

	/**
	 * List of mapped parents with properties that are allowed to contain a restricted set of HTML tags.
	 *
	 * @since 4.2.3
	 *
	 * @var array
	 */
	private $htmlAllowedFields = [
		// FAQPage
		'acceptedAnswer' => [
			'text'
		]
	];

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// No AJAX check since we need to be able to grab the schema output via the REST API.
		if ( wp_doing_cron() ) {
			return;
		}

		$this->helpers = new Helpers;
	}

	/**
	 * Returns the JSON schema output.
	 *
	 * @since 4.0.0
	 *
	 * @param  array  $graphs The graphs to output (optional - used for REST API).
	 * @return string         The JSON schema output.
	 */
	public function get() {
		// First, check if the schema is disabled.
		if ( ! $this->helpers->isEnabled() ) {
			return '';
		}

		$this->determineSmartGraphsAndContext();

		return $this->generateSchema();
	}

	/**
	 * Generates the JSON schema after the graphs/context have been determined.
	 *
	 * @since 4.2.5
	 *
	 * @return string The JSON schema output.
	 */
	protected function generateSchema() {
		// Now, filter the graphs.
		$this->graphs = apply_filters(
			'aioseo_schema_graphs',
			array_unique( array_filter( array_values( $this->graphs ) ) )
		);

		if ( ! $this->graphs ) {
			return '';
		}

		// Check if a WebPage graph is included. Otherwise add the default one.
		$webPageGraphFound = false;
		foreach ( $this->graphs as $graphName ) {
			if ( in_array( $graphName, $this->webPageGraphs, true ) ) {
				$webPageGraphFound = true;
				break;
			}
		}

		if ( ! $webPageGraphFound ) {
			$this->graphs[] = 'WebPage';
		}

		// Now that we've determined the graphs, start generating their data.
		$schema = [
			'@context' => 'https://schema.org',
			'@graph'   => []
		];

		// By determining the length of the array after every iteration, we are able to add additional graphs during runtime.
		// e.g. The Article graph may require a Person graph to be output for the author.
		for ( $i = 0; $i < count( $this->graphs ); $i++ ) {
			$namespace = $this->getGraphNamespace( $this->graphs[ $i ] );
			if ( $namespace ) {
				$schema['@graph'][] = ( new $namespace )->get();
			}
		}

		return aioseo()->schema->helpers->getOutput( $schema );
	}

	/**
	 * Gets the relevant namespace for the given graph.
	 *
	 * @since 4.2.5
	 *
	 * @param  string $graphName The graph name.
	 * @return string            The namespace.
	 */
	protected function getGraphNamespace( $graphName ) {
		$namespace = "\AIOSEO\Plugin\Common\Schema\Graphs\\{$graphName}";
		if ( class_exists( $namespace ) ) {
			return $namespace;
		}

		// If we can't find it in the root dir, check if we can find it in a sub dir.
		foreach ( $this->graphSubDirectories as $dirName ) {
			$namespace = "\AIOSEO\Plugin\Common\Schema\Graphs\\{$dirName}\\{$graphName}";
			if ( class_exists( $namespace ) ) {
				return $namespace;
			}
		}

		return '';
	}

	/**
	 * Determines the smart graphs that need to be output by default, as well as the current context for the breadcrumbs.
	 *
	 * @since 4.2.5
	 *
	 * @param  bool $isValidator Whether the current call is for the validator.
	 * @return void
	 */
	protected function determineSmartGraphsAndContext( $isValidator = false ) {
		$contextInstance = new Context;
		$this->graphs    = array_merge( $this->graphs, $this->getDefaultGraphs() );

		if ( aioseo()->helpers->isDynamicHomePage() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->home();

			return;
		}

		if ( is_home() || aioseo()->helpers->isWooCommerceShopPage() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->post();

			return;
		}

		if ( is_singular() ) {
			$this->determineContextSingular( $contextInstance, $isValidator );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->term();

			return;
		}

		if ( is_author() ) {
			$this->graphs[] = 'CollectionPage';
			$this->graphs[] = 'PersonAuthor';
			$this->context  = $contextInstance->author();
		}

		if ( is_post_type_archive() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->postArchive();

			return;
		}

		if ( is_date() ) {
			$this->graphs[] = 'CollectionPage';
			$this->context  = $contextInstance->date();

			return;
		}

		if ( is_search() ) {
			$this->graphs[] = 'SearchResultsPage';
			$this->context  = $contextInstance->search();

			return;
		}

		if ( is_404() ) {
			$this->context = $contextInstance->notFound();
		}
	}

	/**
	 * Determines the smart graphs and context for singular pages.
	 *
	 * @since 4.2.6
	 *
	 * @param  Context $contextInstance The Context class instance.
	 * @param  bool    $isValidator     Whether we're getting the output for the validator.
	 * @return void
	 */
	protected function determineContextSingular( $contextInstance, $isValidator ) {
		// Check if we're on a BuddyPress member page.
		if ( function_exists( 'bp_is_user' ) && bp_is_user() ) {
			$this->graphs[] = 'ProfilePage';
		}

		// If the current request is for the validator, we can't include the default graph here.
		// We need to include the default graph that the validator sent.
		// Don't do this if we're in Pro since we then need to get it from the post meta.
		if ( ! $isValidator ) {
			$this->graphs[] = $this->getDefaultPostGraph();
		}

		$this->context = $contextInstance->post();
	}

	/**
	 * Returns the default graph for the post type.
	 *
	 * @since 4.2.6
	 *
	 * @return string The default graph.
	 */
	protected function getDefaultPostGraph() {
		return $this->getDefaultPostTypeGraph();
	}

	/**
	 * Returns the default graph for the current post type.
	 *
	 * @since 4.2.5
	 *
	 * @param  null|WP_Post $post The post object.
	 * @return string             The default graph.
	 */
	public function getDefaultPostTypeGraph( $post = null ) {
		$post = $post ? $post : aioseo()->helpers->getPost();
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return '';
		}

		$dynamicOptions = aioseo()->dynamicOptions->noConflict();
		if ( ! $dynamicOptions->searchAppearance->postTypes->has( $post->post_type ) ) {
			return '';
		}

		$defaultType = $dynamicOptions->searchAppearance->postTypes->{$post->post_type}->schemaType;
		switch ( $defaultType ) {
			case 'Article':
				return $dynamicOptions->searchAppearance->postTypes->{$post->post_type}->articleType;
			case 'WebPage':
				return $dynamicOptions->searchAppearance->postTypes->{$post->post_type}->webPageType;
			default:
				return $defaultType;
		}
	}

	/**
	 * Returns the default graphs that should be output on every page, regardless of its type.
	 *
	 * @since 4.2.5
	 *
	 * @return array The default graphs.
	 */
	protected function getDefaultGraphs() {
		$siteRepresents = ucfirst( aioseo()->options->searchAppearance->global->schema->siteRepresents );

		return [
			'BreadcrumbList',
			'Kg' . $siteRepresents,
			'WebSite'
		];
	}
}