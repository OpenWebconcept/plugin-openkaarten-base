<?php
/**
 * Adds location post type and support for mapping fieldlabels.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Eyal Beker <eyal@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Admin;

/**
 * Adds location post type and support for mapping fieldlabels.
 */
class Locations {

	/**
	 * The object ID.
	 *
	 * @var int
	 */
	private static $object_id;

	/**
	 * The datalayer ID.
	 *
	 * @var int
	 */
	private static $datalayer;

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Datalayers|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Datalayers The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Locations();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'cmb2_init', [ 'Openkaarten_Base_Plugin\Admin\Locations', 'action_cmb2_init' ] );
		add_filter( 'cmb2_override_field_latitude_meta_value', [ 'Openkaarten_Base_Plugin\Admin\Locations', 'prefill_geometry_object' ], 10, 4 );
		add_filter( 'cmb2_override_field_longitude_meta_value', [ 'Openkaarten_Base_Plugin\Admin\Locations', 'prefill_geometry_object' ], 10, 4 );
		add_action( 'save_post_owc_ok_location', [ 'Openkaarten_Base_Plugin\Admin\Locations', 'save_geometry_object' ], 10, 1 );
	}

	/**
	 * Register the CMB2 metaboxes
	 *
	 * @return void
	 */
	public static function action_cmb2_init() {
		// Get the post ID, both on edit page and after submit.
		$post_id = '';

		// phpcs:ignore WordPress.Security.NonceVerification -- No nonce verification needed.
		if ( ! empty( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification -- No nonce verification needed.
			$post_id = sanitize_text_field( wp_unslash( $_GET['post'] ) );
			// phpcs:ignore WordPress.Security.NonceVerification -- No nonce verification needed.
		} elseif ( ! empty( $_POST['post_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification -- No nonce verification needed.
			$post_id = sanitize_text_field( wp_unslash( $_POST['post_ID'] ) );
		}

		self::cmb2_location_datalayer_field();

		if ( empty( $post_id ) ) {
			return;
		}

		self::$object_id = (int) $post_id;
		self::$datalayer = (int) get_post_meta( self::$object_id, 'location_datalayer_id', true );

		if ( empty( self::$datalayer ) ) {
			return;
		}

		self::cmb2_location_fixed_fields();
	}

	/**
	 * Register the CMB2 metaboxes for the datalayer field for the Location post type
	 *
	 * @return void
	 */
	public static function cmb2_location_datalayer_field() {
		$prefix = 'location_datalayer_';

		$cmb = new_cmb2_box(
			[
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Location datalayer', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_location' ],
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true,
				'show_in_rest' => true,
			]
		);

		$cmb->add_field(
			[
				'name'             => __( 'Datalayer', 'openkaarten-base' ) . Cmb2::required(),
				'id'               => $prefix . 'id',
				'type'             => 'select',
				'desc'             => __( 'Choose the datalayer of the location from the datalayer post type. Once a datalayer is selected, extra input fields from this datalayer will be included.', 'openkaarten-base' ),
				'attributes'       => [
					'required' => 'required',
				],
				'options_cb'       => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'cmb2_dropdown_datalayers' ],
				'show_option_none' => true,
				'show_in_rest'     => true,
			]
		);
	}

	/**
	 * Register the CMB2 metaboxes for the fixed fields for the Dataset post type
	 *
	 * @return void
	 */
	public static function cmb2_location_fixed_fields() {

		$source_fields = get_post_meta( self::$datalayer, 'source_fields', true );

		if ( empty( $source_fields ) ) {
			return;
		}

		$prefix = 'location_geometry_';

		$cmb = new_cmb2_box(
			[
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Location geometry object', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_location' ],
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true,
				'show_in_rest' => true,
			]
		);

		$cmb->add_field(
			[
				'name'         => 'Latitude',
				'id'           => 'field_latitude',
				'type'         => 'text',
				'description'  => __( 'The latitude of the location.', 'openkaarten-base' ),
				'show_in_rest' => true,
				// translators: %s: link to latlong.net.
				'before_row'   => sprintf( __( 'You can retrieve the latitude and longitude from an address via <a href="%s" target="_blank">this link</a>.', 'openkaarten-base' ), 'https://www.latlong.net/convert-address-to-lat-long.html' ),
			]
		);

		$cmb->add_field(
			[
				'name'         => 'Longitude',
				'id'           => 'field_longitude',
				'type'         => 'text',
				'description'  => __( 'The longitude of the location.', 'openkaarten-base' ),
				'show_in_rest' => true,
			]
		);

		$prefix = 'location_fixed_';

		$cmb = new_cmb2_box(
			[
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Location fixed fields', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_location' ],
				'context'      => 'normal',
				'priority'     => 'low',
				'show_names'   => true,
				'cmb_styles'   => true,
				'show_in_rest' => true,
			]
		);

		foreach ( $source_fields as $field ) {
			$required = $field['field_required'] ?? false;

			$field['attributes'] = [];

			if ( $required ) {
				$field['attributes'] = [
					'required' => 'required',
				];
			}

			if ( 'text_number' === $field['field_type'] ) {
				$field['field_type'] = 'text';
				$field['attributes'] = array_merge(
					$field['attributes'],
					[
						'type'    => 'number',
						'pattern' => '\d*',
						'min'     => 0,
						'step'    => 1,
					]
				);
			}

			$cmb->add_field(
				[
					'name'         => $field['field_display_label'] . ( $required ? Cmb2::required() : '' ),
					'id'           => 'field_' . $field['field_label'],
					'type'         => $field['field_type'],
					'attributes'   => $field['attributes'],
					'description'  => ( ! isset( $field['field_show'] ) || 'on' !== $field['field_show'] ) ? __( 'This field is hidden in the frontend.', 'openkaarten-base' ) : '',
					'show_in_rest' => true,
				]
			);
		}
	}

	/**
	 * Get the location marker for the location.
	 *
	 * @param int $datalayer_id The datalayer ID.
	 * @param int $location_id The location ID.
	 *
	 * @return array The color and icon of the marker.
	 */
	public static function get_location_marker( $datalayer_id, $location_id ) {
		$marker_field = get_post_meta( $datalayer_id, 'marker_field', true );
		$markers      = get_post_meta( $datalayer_id, 'markers', true );

		$color = get_post_meta( $datalayer_id, 'default_marker_color', true );
		$icon  = false;

		// Check marker customization and set correct marker icon and color.
		if ( ! empty( $markers ) && ! empty( $marker_field ) ) {
			foreach ( $markers as $marker_data ) {
				$location_marker_field = get_post_meta( $location_id, 'field_' . $marker_field, true );
				if ( isset( $marker_data['field_value'] ) && $location_marker_field === $marker_data['field_value'] ) {
					if ( ! empty( $marker_data['marker_color'] ) ) {
						$color = $marker_data['marker_color'];
					}
					if ( ! empty( $marker_data['marker_icon'] ) ) {
						$icon = $marker_data['marker_icon'];
					}
				}
			}
		}

		return [
			'color' => $color,
			'icon'  => $icon,
		];
	}

	/**
	 * Get the location marker URL.
	 *
	 * @param string $icon The icon name.
	 *
	 * @return string
	 */
	public static function get_location_marker_url( $icon ) {
		if ( ! $icon ) {
			return null;
		}

		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'local' === \WP_ENVIRONMENT_TYPE ) {
			return 'https://raw.githubusercontent.com/OpenGemeenten/Iconenset/64e1ee818d3339a54153d44d820381c804c24304/Regular/' . $icon . '.svg';
		}

		return plugin_dir_url( dirname( __DIR__ ) ) . 'opengemeenten-iconenset/Regular/' . $icon . '.svg';
	}

	/**
	 * Get the location marker path.
	 *
	 * @param string $icon The icon name.
	 *
	 * @return string
	 */
	public static function get_location_marker_path( $icon ) {
		return plugin_dir_path( dirname( __DIR__ ) ) . 'opengemeenten-iconenset/Regular/' . $icon . '.svg';
	}

	/**
	 * Prefill the geometry object.
	 *
	 * @param mixed  $override The override value.
	 * @param array  $args The arguments.
	 * @param array  $field_args The field arguments.
	 * @param object $field The field object.
	 *
	 * @return mixed
	 */
	public static function prefill_geometry_object( $override, $args, $field_args, $field ) {
		$geometry_object = get_post_meta( self::$object_id, 'geometry', true );
		if ( ! empty( $geometry_object ) ) {
			$geometry_object = json_decode( $geometry_object, true );
			if ( 'field_latitude' === $field->args['id'] && isset( $geometry_object['geometry']['coordinates'][1] ) ) {
				return $geometry_object['geometry']['coordinates'][1];
			}
			if ( 'field_longitude' === $field->args['id'] && isset( $geometry_object['geometry']['coordinates'][0] ) ) {
				return $geometry_object['geometry']['coordinates'][0];
			}
		}
	}

	/**
	 * Save the location geometry object.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public static function save_geometry_object( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check nonce.
		if ( ! isset( $_POST['openkaarten_cmb2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openkaarten_cmb2_nonce'] ) ), 'openkaarten_cmb2_nonce' ) ) {
			return;
		}

		if ( doing_action( 'save_post_event' ) ) {
			// We cannot do this now. The post we are trying to update doesn't exist yet.
			add_action(
				'shutdown',
				function () use ( $post_id ) {
					// Do the save-actions.
					$this->save_handler( $post_id );
					// And clear the wp-rest-cache.
					if ( class_exists( \Caching::class ) ) {
						\Caching::get_instance()->delete_cache_by_endpoint( '%/openkaarten/v1/datasets', \Caching::FLUSH_LOOSE, true );
					}
				}
			);

			return;
		}

		$dataset_id = get_post_meta( $post_id, 'location_datalayer_id', true );
		$properties = [];

		// Get all cmb2 fields for the dataset post type.
		$source_fields = get_post_meta( $dataset_id, 'source_fields', true );
		if ( ! empty( $source_fields ) ) {
			foreach ( $source_fields as $source_field ) {
				$properties[ $source_field['field_label'] ] = isset( $_POST[ 'field_' . $source_field['field_label'] ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'field_' . $source_field['field_label'] ] ) ) : '';
			}
		}

		// Make the geometry object.
		$geometry = [];
		if ( isset( $_POST['field_latitude'] ) && isset( $_POST['field_longitude'] ) ) {
			$latitude  = sanitize_text_field( wp_unslash( $_POST['field_latitude'] ) );
			$longitude = sanitize_text_field( wp_unslash( $_POST['field_longitude'] ) );
			$geometry  = [
				'type'        => 'Point',
				'coordinates' => [ (float) $longitude, (float) $latitude ],
			];
		}

		$component = [
			'type'       => 'Feature',
			'properties' => $properties,
			'geometry'   => $geometry,
		];
		$component = wp_json_encode( $component );

		// Check if post meta exists and update or add the post meta.
		if ( metadata_exists( 'post', $post_id, 'geometry' ) ) {
			update_post_meta( $post_id, 'geometry', wp_slash( $component ) );
		} else {
			add_post_meta( $post_id, 'geometry', wp_slash( $component ), true );
		}
	}
}
