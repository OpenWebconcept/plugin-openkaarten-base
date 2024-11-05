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
		add_action( 'cmb2_render_geomap', [ 'Openkaarten_Base_Functions\Openkaarten_Base_Functions', 'cmb2_render_geomap_field_type' ], 10, 5 );
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

		$marker   = $markers[ $group_index ];
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

		switch ( $datalayer_url_type ) {
			case 'live':
				// Retrieve the objects from the source file.
				$datalayer_url = get_post_meta( $object_id, 'datalayer_url', true );

				$datalayer_locations = wp_remote_get( $datalayer_url );
				$datalayer_locations = json_decode( wp_remote_retrieve_body( $datalayer_locations ), true );

				$array_keys_to_look_for = [ 'data', 'results' ];
				// Check if the data is an array with a key from the $array_keys_to_look_for and if so, use that data.
				foreach ( $array_keys_to_look_for as $key ) {
					if ( isset( $datalayer_locations[ $key ] ) ) {
						$datalayer_locations = $datalayer_locations[ $key ];
						break;
					}
				}

				break;
			case 'import':
			default:
				$meta_query = [
					'relation' => 'AND',
					[
						'key'     => 'geometry',
						'compare' => 'EXISTS',
					],
				];

				$meta_query = array_merge(
					$meta_query,
					[
						[
							'key'     => 'location_datalayer_id',
							'value'   => $object_id,
							'compare' => '=',
						],
					]
				);

				$args = wp_parse_args(
					[
						'post_type'   => 'owc_ok_location',
						'numberposts' => -1,
						'orderby'     => 'title',
						'order'       => 'ASC',
						// phpcs:ignore WordPress.DB.SlowDBQuery -- This query is needed to get the locations.
						'meta_query'  => $meta_query,
					]
				);

				$datalayer_locations = get_posts( $args );
				break;
		}

		if ( ! $datalayer_locations ) {
			echo esc_html__( 'No locations found for this datalayer.', 'openkaarten-base' );
			return;
		}

		$min_lat  = null;
		$max_lat  = null;
		$min_long = null;
		$max_long = null;

		$locations = [];
		if ( $datalayer_locations ) {
			foreach ( $datalayer_locations as $location ) {

				$datalayer_url_type = get_post_meta( $object_id, 'datalayer_url_type', true ) ? : 'import';

				if ( 'import' === $datalayer_url_type ) {
					$geometry_object = get_post_meta( $location->ID, 'geometry' );
				} else {
					$geometry_object = Helper::array_search_recursive( 'geometry', $location ) ? : false;
				}

				if ( ! $geometry_object ) {
					continue;
				}

				if ( 'import' === $datalayer_url_type ) {
					$geometry_array = json_decode( $geometry_object[0], true );
					if ( empty( $geometry_array['geometry']['coordinates'] ) ) {
						continue;
					}
				} else {
					$geometry_array = $geometry_object;
				}

				// Set min and max values for the map.
				$geom = geoPHP::load( wp_json_encode( $geometry_array ) );
				$bbox = $geom->getBBox();

				$min_lat  = ( null === $min_lat || $bbox['miny'] < $min_lat ) ? $bbox['miny'] : $min_lat;
				$max_lat  = ( null === $max_lat || $bbox['maxy'] > $max_lat ) ? $bbox['maxy'] : $max_lat;
				$min_long = ( null === $min_long || $bbox['minx'] < $min_long ) ? $bbox['minx'] : $min_long;
				$max_long = ( null === $max_long || $bbox['maxx'] > $max_long ) ? $bbox['maxx'] : $max_long;

				// Get average lat and long for the center of the map.
				$center_lat  = ( $min_lat + $max_lat ) / 2;
				$center_long = ( $min_long + $max_long ) / 2;

				// Set the content for the marker popup.
				if ( 'import' === $datalayer_url_type ) {
					$marker_content  = get_the_title( $location->ID ) . '<br /><a href="' . get_edit_post_link( $location->ID ) . '" target="_blank">' . __( 'Edit location', 'openkaarten-base' ) . '</a>';
					$location_marker = Locations::get_location_marker( $object_id, $location->ID );
				} else {
					$title_fields    = get_post_meta( $object_id, 'title_field_mapping', true );
					$title           = Importer::create_title_from_mapping( $location, $title_fields );
					$marker_content  = $title;
					$location_marker = Locations::get_location_marker( $object_id, false, $location );
				}

				$locations[] = [
					'feature' => $geometry_array,
					'content' => $marker_content,
					'icon'    => $location_marker['icon'] ? Locations::get_location_marker_url( $location_marker['icon'] ) : '',
					'color'   => $location_marker['color'],
				];
			}
		}

		wp_localize_script(
			'owc_ok-openstreetmap',
			'leaflet_vars',
			[
				'mapLocations' => wp_json_encode( $locations ),
				'minLat'       => esc_attr( $min_lat ),
				'maxLat'       => esc_attr( $max_lat ),
				'minLong'      => esc_attr( $min_long ),
				'maxLong'      => esc_attr( $max_long ),
				'centerLat'    => esc_attr( $center_lat ),
				'centerLong'   => esc_attr( $center_long ),
				'defaultZoom'  => 10,
				'fitBounds'    => true,
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
	public function cmb2_render_import_sync_field_type( $field, $escaped_value, $object_id ) {
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
