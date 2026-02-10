<?php
/**
 * Helper class for CMB2
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Admin;

use geoPHP\geoPHP;
use Openkaarten_Base_Functions\Openkaarten_Base_Functions;

/**
 * Helper class for CMB2
 */
class Cmb2 {

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Cmb2|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Cmb2 The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Cmb2();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'post_submitbox_start', [ 'Openkaarten_Base_Plugin\Admin\Cmb2', 'add_nonce_field' ] );
		add_action( 'cmb2_render_markerpreview', [ 'Openkaarten_Base_Plugin\Admin\Cmb2', 'cmb2_render_markerpreview_field_type' ], 10, 5 );
		add_action( 'cmb2_render_openstreetmap', [ 'Openkaarten_Base_Plugin\Admin\Cmb2', 'cmb2_render_openstreetmap_field_type' ], 10, 5 );
		add_action( 'cmb2_render_import_sync', [ 'Openkaarten_Base_Plugin\Admin\Cmb2', 'cmb2_render_import_sync_field_type' ], 10, 5 );
	}

	/**
	 * Add required field indicator
	 *
	 * @return string
	 */
	public static function required() {
		return ' <strong style="color:red">*</strong>';
	}

	/**
	 * Add a nonce field to the CMB2 form
	 *
	 * @return void
	 */
	public static function add_nonce_field() {
		wp_nonce_field( 'openkaarten_cmb2_nonce', 'openkaarten_cmb2_nonce' );
	}

	/**
	 * Render the marker preview field type.
	 *
	 * @param \CMB2_Field $field The CMB2 field.
	 * @param mixed       $escaped_value The escaped value.
	 * @param int         $object_id The object ID.
	 *
	 * @return void
	 */
	public static function cmb2_render_markerpreview_field_type( $field, $escaped_value, $object_id ) {
		if ( ! $field->group ) {
			echo esc_html__( 'No marker preview available', 'openkaarten-base' );
			return;
		}

		$group_index = $field->group->index;
		$markers     = get_post_meta( $object_id, 'markers', true );

		if ( ! $markers ) {
			echo esc_html__( 'No marker preview available', 'openkaarten-base' );
			return;
		}

		$marker = $markers[ $group_index ];

		if ( empty( $marker['marker_color'] ) || empty( $marker['marker_icon'] ) ) {
			echo esc_html__( 'No marker preview available', 'openkaarten-base' );
			return;
		}

		$color    = $marker['marker_color'];
		$icon     = $marker['marker_icon'];
		$icon_url = Locations::get_location_marker_url( $icon ) ? : '';

		echo "<div class='leaflet-custom-icon'><div style='background-color:" . esc_attr( $color ) . ";' class='marker-pin'></div><span class='marker-icon'><img src='" . esc_attr( $icon_url ) . "' /></span></div>";
	}

	/**
	 * Adds a custom field type for list items.
	 *
	 * @param  object $field             The CMB2_Field type object.
	 * @param  mixed  $escaped_value     The value of this field passed through the escaping filter.
	 * @param  int    $object_id         The ID of the object this field is saving to.
	 *
	 * @return string The field type HTML.
	 */
	public static function cmb2_render_openstreetmap_field_type( $field, $escaped_value, $object_id ) {

		$datalayer_url_type = get_post_meta( $object_id, 'datalayer_url_type', true );

		if ( 'live' === $datalayer_url_type ) {

			// Retrieve the objects from the source file.
			$datalayer_url        = get_post_meta( $object_id, 'datalayer_url', true );
			$datalayer_file_input = wp_remote_get( $datalayer_url );

			if ( is_wp_error( $datalayer_file_input ) ) {
				return;
			}

			// Get the feature collection from the source file.
			$feature_collection = wp_remote_retrieve_body( $datalayer_file_input );

		} else {

			// Get the feature collection from the datalayer.
			$feature_collection = Helper::get_feature_collection_from_datalayer( $object_id );

		}

		if ( empty( $feature_collection ) ) {
			echo esc_html__( 'No locations found for this datalayer.', 'openkaarten-base' );
			return;
		}

		// Set default min and max lat/lng.
		$min_lat  = null;
		$max_lat  = null;
		$min_long = null;
		$max_long = null;

		// Set settings options for the map.
		$center_lat   = get_option( 'openkaarten_base_default_lat', 52.0 );
		$center_long  = get_option( 'openkaarten_base_default_lng', 4.75 );
		$default_zoom = get_option( 'openkaarten_base_default_zoom', 8 );

		$locations  = [];
		$fit_bounds = false;

		try {
			// Parse the feature collection.
			$geom = geoPHP::load( $feature_collection );

			// Get the bounding box of the geometry.
			$bbox = $geom->getBBox();

			if ( ! empty( $bbox ) ) {
				// Set min and max values for the map.
				$min_lat  = $bbox['miny'];
				$max_lat  = $bbox['maxy'];
				$min_long = $bbox['minx'];
				$max_long = $bbox['maxx'];

				// Get average lat and long for the center of the map.
				$center_lat  = ( $min_lat + $max_lat ) / 2;
				$center_long = ( $min_long + $max_long ) / 2;

				// Loop through all the components of the geometry and add them to the locations array with the right properties.
				foreach ( $geom->getComponents() as $component ) {
					// Use json output to plot the geometry on the map.
					$location_output = $component->out( 'json' );
					$location_output = json_decode( $location_output, true );

					if ( isset( $location_output['geometry'] ) ) {
						$geometry = $location_output['geometry'];
					} else {
						$geometry = $location_output;
					}

					$location = [
						'feature' => $geometry,
						'content' => '',
						'icon'    => '',
						'color'   => '',
					];

					if ( ! empty( $location_output['properties'] ) ) {

						// Get location properties.
						$location_properties = $location_output['properties'];
						if ( 'live' === $datalayer_url_type ) {
							$location_data_for_marker = $location_output['properties'];
						} else {
							$location_data_for_marker = $location_output;
						}

						// Get marker information.
						$item_marker = Locations::get_location_marker( $object_id, false, $location_data_for_marker );
						$geom_marker = [
							'color' => $item_marker['color'],
							'icon'  => Locations::get_location_marker_url( $item_marker['icon'] ),
						];

						// Get title information based on title field mapping.
						$title_fields = get_post_meta( $object_id, 'title_field_mapping', true );
						$title        = Importer::create_title_from_mapping( $location_properties, $title_fields );

						$location['content'] = $title;
						$location['icon']    = $geom_marker['icon'] ?? '';
						$location['color']   = $geom_marker['color'] ?? '';
					}

					$locations[] = $location;
					$fit_bounds  = true;
				}
			}
		} catch ( \Exception $e ) {
			// Add error message via admin notice.
			echo esc_html__( 'The geometry is not valid and can\'t be parsed.', 'openkaarten-base' );
			return;
		}

		// Enqueue the OpenStreetMap script.
		wp_localize_script(
			'owc_ok-openstreetmap-base',
			'leaflet_vars',
			[
				'mapLocations' => wp_json_encode( $locations ),
				'minLat'       => esc_attr( $min_lat ),
				'maxLat'       => esc_attr( $max_lat ),
				'minLong'      => esc_attr( $min_long ),
				'maxLong'      => esc_attr( $max_long ),
				'centerLat'    => esc_attr( $center_lat ),
				'centerLong'   => esc_attr( $center_long ),
				'defaultZoom'  => $default_zoom,
				'fitBounds'    => $fit_bounds,
			]
		);

		echo '<div id="map-base" class="map-base"></div>';
	}

	/**
	 * Adds a custom field type for a button to sync or import data.
	 *
	 * @param  object $field             The CMB2_Field type object.
	 * @param  mixed  $escaped_value     The value of this field passed through the escaping filter.
	 * @param  int    $object_id         The ID of the object this field is saving to.
	 *
	 * @return void
	 */
	public static function cmb2_render_import_sync_field_type( $field, $escaped_value, $object_id ) {
		$last_updated = get_post_meta( $object_id, 'datalayer_last_import', true );

		// Get URL of edit post page.
		$edit_post_url = get_edit_post_link( $object_id );

		echo '<form method="post" style="margin-top:10px;">
                <input type="hidden" name="sync_import_file" value="1">
                <input type="hidden" name="post_id" value="' . esc_attr( $object_id ) . '">
                <input type="hidden" name="redirect_url" value="' . esc_attr( $edit_post_url ) . '">
                <input type="submit" name="submit_sync_import_file" class="button button-primary button-large" value="' . esc_html__( 'Sync data', 'openkaarten-base' ) . '">
            </form>';

		// translators: %s is the last updated date.
		echo '<p>' . sprintf( esc_html__( 'Last synced at: %s', 'openkaarten-base' ), esc_attr( $last_updated ) ) . '</p>';
	}
}
