<?php
/**
 * The Openkaarten_Controller class.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Rest_Api
 * @author     Acato <eyal@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Rest_Api;

use geoPHP\Exception\IOException;
use geoPHP\geoPHP;
use Openkaarten_Base_Plugin\Admin\Helper;
use Openkaarten_Base_Plugin\Admin\Locations;
use Openkaarten_Base_Plugin\Conversion;

/**
 * The Openkaarten_Controller class.
 */
class Openkaarten_Controller extends \WP_REST_Posts_Controller {

	/**
	 * The post types returned by this API endpoint, mapped against their meta prefix.
	 *
	 * @var string[] $post_type_mappings
	 */
	private $post_type_mappings = [
		'owc_ok_datalayer' => [
			'rest_endpoint_items' => 'datasets',
		],
	];

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Openkaarten_Controller|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Openkaarten_Controller The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Openkaarten_Controller();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		add_filter(
			'rest_pre_serve_request',
			[
				'Openkaarten_Base_Plugin\Rest_Api\Openkaarten_Controller',
				'rest_change_output_format',
			],
			10,
			4
		);

		add_filter( 'wp_rest_cache/allowed_endpoints', [ $this, 'wprc_add_caching_endpoint' ], 10, 1 );
	}

	/**
	 * Initialize the controller.
	 *
	 * @return void
	 */
	public function init() {
		parent::__construct( 'owc_ok_datalayer' );

		$this->namespace = 'owc/openkaarten/v1';
		$this->rest_base = 'datasets';
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * Main endpoint.
	 *
	 * @link https://url/wp-json/owc/openkaarten/v1
	 *
	 * Endpoint of the openkaarten-datasets to retrieve all datasets.
	 * @link https://url/wp-json/owc/openkaarten/v1/datasets
	 *
	 * Endpoint to filter openkaarten-datasets on cmb2 custom fields.
	 * @link https://url/wp-json/owc/openkaarten/v1/datasets?{cmb2_field1}=value1&{cmb2_field2}=value2
	 *
	 * Endpoint of the openkaarten-dataset to retrieve a specific dataset by ID.
	 * @link https://url/wp-json/owc/openkaarten/v1/datasets/id/{id}
	 *
	 * Endpoint of the openkaarten-dataset to retrieve a specific dataset by slug.
	 * @link https://url/wp-json/owc/openkaarten/v1/datasets?slug={slug}
	 *
	 * Endpoint of the openkaarten-dataset to retrieve a specific dataset by id with a specific output format (geoJSON, KML, etc).
	 * @link https://url/wp-json/owc/openkaarten/v1/datasets/id/{id}/{output-format}
	 *
	 * Endpoint of the openkaarten-dataset to retrieve a specific dataset by id with a specific projection in the output (WGS84, RD).
	 * @link https://url/wp-json/owc/openkaarten/v1/datasets/id/{id}?projection={projection}
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/datasets',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => $this->get_items_collection_params(),
			]
		);

		register_rest_route(
			$this->namespace,
			'/datasets/id/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item_by_id' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [],
			]
		);

		register_rest_route(
			$this->namespace,
			'/datasets/id/(?P<id>\d+)/(?P<output_format>[a-zA-Z0-9-]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item_by_id' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [],
			]
		);
	}

	/**
	 * Check if a given request has permission to read items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return bool Whether the request has permission to read items.
	 */
	public function get_items_permissions_check( $request ) {
		return true;
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_items_collection_params();
		$args       = [];

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = [
			'author'         => 'author__in',
			'author_exclude' => 'author__not_in',
			'exclude'        => 'post__not_in',
			'include'        => 'post__in',
			'menu_order'     => 'menu_order',
			'offset'         => 'offset',
			'order'          => 'order',
			'orderby'        => 'orderby',
			'page'           => 'paged',
			'parent'         => 'post_parent__in',
			'parent_exclude' => 'post_parent__not_in',
			'search'         => 's',
			'slug'           => 'post_name__in',
			'status'         => 'post_status',
		];

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		// Ensure our per_page parameter overrides any provided posts_per_page filter.
		if ( isset( $registered['per_page'] ) ) {
			$args['posts_per_page'] = $request['per_page'];
		}

		$args = $this->prepare_tax_query( $args, $request );

		$args['post_type'] = 'owc_ok_datalayer';

		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new \WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = [];
		foreach ( $query_result as $post ) {
			$post_feature = $this->prepare_item_for_response( $post, $request );
			$post_item    = json_decode( $post_feature );

			// Add title and id to post item.
			$post_item->title = $post->post_title;
			$post_item->id    = $post->ID;

			// If $posts is a feature, i.o. a feature collection, add the features to a feature collection first.
			if ( ! empty( $post_item ) && isset( $post_item->type ) && 'Feature' === $post_item->type ) {
				$post_item = [
					'type'     => 'FeatureCollection',
					'features' => $post_item,
					'title'    => $post->post_title,
					'id'       => $post->ID,
				];
			}

			$posts[] = $post_item;
		}

		$response = [
			'type'       => 'DatasetCollection',
			'datasets'   => $posts,
			'pagination' => [
				'total' => $posts_query->found_posts,
				'limit' => (int) $posts_query->query_vars['posts_per_page'],
				'pages' => [
					'total'   => ceil( $posts_query->found_posts / (int) $posts_query->query_vars['posts_per_page'] ),
					'current' => (int) $query_args['paged'],
				],
			],
			'_links'     => [],
		];

		foreach ( $this->post_type_mappings as $type => $post_type_mapping ) {
			$response['_links'][ $type ] = rest_url() . $this->namespace . '/' . $post_type_mapping['rest_endpoint_items'] . '/id/{id}';
		}

		$response = rest_ensure_response( $response );

		return $response;
	}

	/**
	 * Get dataset item by id
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item_by_id( $request ) {
		$id = $request['id'];

		$post = get_post( $id );
		if ( ! $post ) {
			return new \WP_Error( 'rest_post_invalid_id', __( 'Invalid post ID.', 'openkaarten-base' ), [ 'status' => 404 ] );
		}

		// Check if output format exists in the processor types.
		$output_format = $request['output_format'];
		if ( ! empty( $output_format ) ) {
			$processor_types        = geoPHP::getAdapterMap();
			$processor_types_string = implode( ', ', array_keys( $processor_types ) );
			if ( ! isset( $processor_types[ $output_format ] ) ) {
				// translators: %s: List of valid output formats.
				return new \WP_Error( 'rest_post_invalid_output_format', sprintf( __( 'Invalid output format. Use one of the following output formats: %s', 'openkaarten-base' ), $processor_types_string ), [ 'status' => 404 ] );
			}
		}

		$response = $this->prepare_item_for_response( $post, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Retrieves the query params for the collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_items_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['type'] = [
			'description' => __( 'Limit response to posts within (a) specific post type(s).', 'openkaarten-base' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'string',
			],
			'default'     => [],
		];

		$query_params['after'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts published after a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		];

		$query_params['modified_after'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts modified after a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		];

		$query_params['author']         = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to posts assigned to specific authors.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'integer',
			],
			'default'     => [],
		];
		$query_params['author_exclude'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Ensure result set excludes posts assigned to specific authors.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'integer',
			],
			'default'     => [],
		];

		$query_params['before'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts published before a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		];

		$query_params['modified_before'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit response to posts modified before a given ISO8601 compliant date.' ),
			'type'        => 'string',
			'format'      => 'date-time',
		];

		$query_params['exclude'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Ensure result set excludes specific IDs.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'integer',
			],
			'default'     => [],
		];

		$query_params['include'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to specific IDs.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'integer',
			],
			'default'     => [],
		];

		$query_params['offset'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Offset the result set by a specific number of items.' ),
			'type'        => 'integer',
		];

		$query_params['order'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Order sort attribute ascending or descending.' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => [ 'asc', 'desc' ],
		];

		$query_params['orderby'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Sort collection by post attribute.' ),
			'type'        => 'string',
			'default'     => 'date',
			'enum'        => [
				'author',
				'date',
				'id',
				'include',
				'modified',
				'parent',
				'relevance',
				'slug',
				'include_slugs',
				'title',
			],
		];

		$query_params['parent']         = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to items with particular parent IDs.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'integer',
			],
			'default'     => [],
		];
		$query_params['parent_exclude'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to all items except those of a particular parent ID.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'integer',
			],
			'default'     => [],
		];

		$query_params['slug'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit result set to posts with one or more specific slugs.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'string',
			],
		];

		$query_params['search'] = [
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			'description' => __( 'Limit results to those matching a string.' ),
			'type'        => 'array',
			'items'       => [
				'type' => 'string',
			],
		];

		$query_params['projection'] = [
			'description' => __( 'Set the projection for the output.', 'openkaarten-base' ),
			'type'        => 'string',
			'default'     => 'WGS84',
			'enum'        => [ 'WGS84', 'RD' ],
		];

		return $query_params;
	}

	/**
	 * Prepares the 'tax_query' for a collection of posts.
	 *
	 * @param array            $args WP_Query arguments.
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return array Updated query arguments.
	 * @since 5.7.0
	 */
	public function prepare_tax_query( array $args, \WP_REST_Request $request ) {
		$relation = $request['tax_relation'];

		if ( $relation ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery -- This is a valid query.
			$args['tax_query'] = [ 'relation' => $relation ];
		}

		$taxonomies = wp_list_filter(
			get_object_taxonomies( $this->post_type, 'objects' ),
			[ 'show_in_rest' => true ]
		);

		foreach ( $taxonomies as $taxonomy ) {
			$base = ! empty( $taxonomy->rest_base ) ? $taxonomy->rest_base : $taxonomy->name;

			$tax_include = $request[ $base ];
			$tax_exclude = $request[ $base . '_exclude' ];

			if ( $tax_include ) {
				$terms            = [];
				$include_children = false;
				$operator         = 'IN';

				if ( rest_is_array( $tax_include ) ) {
					$terms = $tax_include;
				} elseif ( rest_is_object( $tax_include ) ) {
					$terms            = empty( $tax_include['terms'] ) ? [] : $tax_include['terms'];
					$include_children = ! empty( $tax_include['include_children'] );

					if ( isset( $tax_include['operator'] ) && 'AND' === $tax_include['operator'] ) {
						$operator = 'AND';
					}
				}

				if ( $terms ) {
					$args['tax_query'][] = [
						'taxonomy'         => $taxonomy->name,
						'field'            => 'term_id',
						'terms'            => $terms,
						'include_children' => $include_children,
						'operator'         => $operator,
					];
				}
			}

			if ( $tax_exclude ) {
				$terms            = [];
				$include_children = false;

				if ( rest_is_array( $tax_exclude ) ) {
					$terms = $tax_exclude;
				} elseif ( rest_is_object( $tax_exclude ) ) {
					$terms            = empty( $tax_exclude['terms'] ) ? [] : $tax_exclude['terms'];
					$include_children = ! empty( $tax_exclude['include_children'] );
				}

				if ( $terms ) {
					$args['tax_query'][] = [
						'taxonomy'         => $taxonomy->name,
						'field'            => 'term_id',
						'terms'            => $terms,
						'include_children' => $include_children,
						'operator'         => 'NOT IN',
					];
				}
			}
		}

		return $args;
	}

	/**
	 * Prepares the query for the collection of items.
	 *
	 * @param \WP_POST         $item The post object.
	 * @param \WP_REST_Request $request Full details about the request.
	 *
	 * @return array|string The item data.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$output_format = $request['output_format'] ?? 'geojson';

		$datalayer_url_type = get_post_meta( $item->ID, 'datalayer_url_type', true );

		if ( 'live' === $datalayer_url_type ) {
			// Retrieve the objects from the source file.
			$datalayer_url        = get_post_meta( $item->ID, 'datalayer_url', true );
			$datalayer_file_input = wp_remote_get( $datalayer_url );

			if ( is_wp_error( $datalayer_file_input ) ) {
				return [];
			}

			// Get the feature collection from the source file.
			$feature_collection = wp_remote_retrieve_body( $datalayer_file_input );

		} else {

			// Get the feature collection from the datalayer.
			$feature_collection = Helper::get_feature_collection_from_datalayer( $item->ID );

		}

		if ( empty( $feature_collection ) ) {
			return [];
		}

		// Parse the FeatureCollection to a geoPHP object in the requested output format.
		try {
			$geom = geoPHP::load( $feature_collection );

			// If the feature collection is a geojson, enrich the feature collection with the marker and tooltip information. But only for live datalayers.
			if ( 'live' === $datalayer_url_type && 'geojson' === $output_format ) {
				// Try to parse the feature collection.
				$geom_components = $geom->getComponents();

				// If the feature collection is a feature, add the marker and tooltip information.
				foreach ( $geom_components as $geom_component ) {
					// Get marker information.
					$item_marker = Locations::get_location_marker( $item->ID, false, $geom_component->getData() );
					$geom_marker = [
						'color' => $item_marker['color'],
						'icon'  => Locations::get_location_marker_url( $item_marker['icon'] ),
					];

					$geom_component->setData( 'marker', $geom_marker );
					$geom_component->setData( 'tooltip', Locations::get_location_tooltip( $item->ID, false, $geom_component->getData() ) );
				}

				// Create a new geometry collection.
				$geom = geoPHP::load( $geom_components );
			}

			return $geom->out( $output_format );

		} catch ( IOException $e ) {
			return [];
		}
	}

	/**
	 * Prepares the response for the collection of items.
	 *
	 * @param array $data The data.
	 *
	 * @return mixed The data.
	 */
	public function prepare_response_for_collection( $data ) {
		return $data;
	}

	/**
	 * Changes the output format of the REST API response.
	 *
	 * @param bool              $served Whether the request has already been served.
	 * @param \WP_REST_Response $result The response object.
	 * @param \WP_REST_Request  $request The request object.
	 * @param \WP_REST_Server   $server The server instance.
	 *
	 * @return bool Whether the request has already been served.
	 */
	public static function rest_change_output_format( $served, $result, $request, $server ) {
		// Bail if the result is not an instance of WP_REST_Response.
		if ( ! $result instanceof \WP_REST_Response ) {
			return $served;
		}

		$request_route = $request->get_route();

		// Check if the request route is the Openkaarten items route.
		if ( ! str_contains( $request_route, 'owc/openkaarten/v1/datasets/id' ) ) {
			return $served;
		}

		// Check if output format exists in the processor types.
		$output_format = $request['output_format'] ?? 'geojson';
		if ( ! empty( $output_format ) ) {
			$processor_types = geoPHP::getAdapterMap();
			if ( ! isset( $processor_types[ $output_format ] ) ) {
				return false;
			}
		}

		// Different output for json and geojson, otherwise it outputs with backslashes.
		if ( in_array( $output_format, [ 'json', 'geojson' ], true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The output is escaped in the WP_REST_Response class.
			echo $result->get_data();
			exit();
		}

		// Change the output headers for specific output formats.
		$server->send_header( 'Content-Type', 'application/' . $output_format );
		$server->send_header( 'Content-Disposition', 'attachment; filename=' . $request['id'] . '.' . $output_format );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The output is escaped in the WP_REST_Response class.
		echo $result->get_data();
		exit();
	}

	/**
	 * Register endpoints to cache.
	 *
	 * @param array $allowed_endpoints Array of allowed endpoints.
	 *
	 * @return array
	 */
	public function wprc_add_caching_endpoint( $allowed_endpoints ) {
		if ( ! isset( $allowed_endpoints['owc/openkaarten/v1'] ) ) {
			$allowed_endpoints['owc/openkaarten/v1'][] = 'datasets';
		}

		return $allowed_endpoints;
	}
}
