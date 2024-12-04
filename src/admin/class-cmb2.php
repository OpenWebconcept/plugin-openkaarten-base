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
				$datalayer_locations = wp_remote_retrieve_body( $datalayer_locations );

				break;
			case 'import':
			default:
				global $wpdb;

				// Create a custom query to get the location ID's, order by title ASC, where the geometry exists and the location_datalayer_id is equal to the object ID.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query is required here.
				$datalayer_locations_db = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'owc_ok_location'
              AND p.post_status = 'publish'
              AND pm.meta_key = 'geometry'
              AND pm.meta_value IS NOT NULL
              AND pm2.meta_key = 'location_datalayer_id'
              AND pm2.meta_value = %s
            ORDER BY p.post_title ASC",
						$object_id
					)
				);

				$datalayer_locations = [];

				foreach ( $datalayer_locations_db as $location ) {

					$geometry_object = get_post_meta( $location->ID, 'geometry', true );

					if ( ! $geometry_object ) {
						continue;
					}

					$datalayer_locations[] = $geometry_object;
				}

				break;
		}

		if ( ! $datalayer_locations ) {
			echo esc_html__( 'No locations found for this datalayer.', 'openkaarten-base' );
			return;
		}

		$datalayer_url_type = get_post_meta( $object_id, 'datalayer_url_type', true ) ? : 'import';

		$min_lat  = null;
		$max_lat  = null;
		$min_long = null;
		$max_long = null;

		$locations = [];

		try {
			$geom = geoPHP::load( $datalayer_locations );

			$bbox = $geom->getBBox();

			// Set min and max values for the map.
			$min_lat  = ( null === $min_lat || $bbox['miny'] < $min_lat ) ? $bbox['miny'] : $min_lat;
			$max_lat  = ( null === $max_lat || $bbox['maxy'] > $max_lat ) ? $bbox['maxy'] : $max_lat;
			$min_long = ( null === $min_long || $bbox['minx'] < $min_long ) ? $bbox['minx'] : $min_long;
			$max_long = ( null === $max_long || $bbox['maxx'] > $max_long ) ? $bbox['maxx'] : $max_long;

			// Get average lat and long for the center of the map.
			$center_lat  = ( $min_lat + $max_lat ) / 2;
			$center_long = ( $min_long + $max_long ) / 2;

			foreach ( $geom->getComponents() as $component ) {
				$location_output = $component->out( 'json' );
				$location_output = json_decode( $location_output, true );

				$locations[] = [
					'feature' => $location_output['geometry'] ?: [],
					'content' => '',
					'icon'    => '',
					'color'   => '',
				];
			}
		} catch ( \Exception $e ) {
			// Add error message via admin notice.
			echo esc_html__( 'The geometry is not valid and can\'t be parsed.', 'openkaarten-base' );
			return;
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
