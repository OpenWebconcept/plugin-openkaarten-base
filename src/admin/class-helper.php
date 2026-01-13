<?php
/**
 * Helper class with several functions.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Eyal Beker <eyal@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Admin;

use Openkaarten_Base_Plugin\Conversion;

/**
 * Helper class with several functions.
 */
class Helper {

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Helper|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Helper The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Helper();
		}

		return self::$instance;
	}

	/**
	 * Add an error message to the admin.
	 *
	 * @param string $message The error message.
	 *
	 * @return void
	 */
	public static function add_error_message( $message ) {
		add_action(
			'admin_notices',
			function () use ( $message ) {
				?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $message ); ?></p>
			</div>
				<?php
			}
		);
	}

	/**
	 * Get the feature collection from a datalayer.
	 *
	 * @param int $datalayer_id The datalayer ID.
	 *
	 * @return string The feature collection.
	 */
	public static function get_feature_collection_from_datalayer( $datalayer_id ) {
		global $wpdb;

		$datalayer          = get_post( $datalayer_id );
		$datalayer_type     = get_post_meta( $datalayer_id, 'datalayer_type', true );
		$datalayer_url_type = get_post_meta( $datalayer_id, 'datalayer_url_type', true );

		if ( 'url' === $datalayer_type && 'live' === $datalayer_url_type ) {

			$datalayer_url = get_post_meta( $datalayer_id, 'datalayer_url', true );

		} else {

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
					$datalayer_id
				)
			);
		}

		$datalayer_locations = [];

		foreach ( $datalayer_locations_db as $location_db ) {
			$location = self::prepare_location( $location_db->ID );
			if ( $location ) {
				$datalayer_locations[] = $location;
			}
		}

		// Create a feature collection to parse the locations to a geoPHP object.
		$feature_collection = [
			'type'     => 'FeatureCollection',
			'id'       => $datalayer_id,
			'title'    => $datalayer->post_title,
			'features' => $datalayer_locations,
		];

		$feature_collection = wp_json_encode( $feature_collection );

		return $feature_collection;
	}

	/**
	 * Prepare a location for the feature collection.
	 *
	 * @param int    $location_id The location ID.
	 * @param string $projection  The projection.
	 *
	 * @return array The location data.
	 */
	public static function prepare_location( $location_id, $projection = false ) {
		$location = get_post( $location_id );

		// Retrieve the geometry based on the type of request.
		$geometry_json = get_post_meta( $location_id, 'geometry', true );

		if ( ! $geometry_json ) {
			return [];
		}

		$item_data          = json_decode( $geometry_json, true );
		$item_data['id']    = $location_id;
		$item_data['title'] = get_the_title( $location_id );

		unset( $item_data['properties'] );

		$dataset_id = get_post_meta( $location_id, 'location_datalayer_id', true );

		// Add location title based on the post title.
		$location_title                   = $location->post_title;
		$item_data['properties']['title'] = $location_title;

		// Get all cmb2 fields for the dataset post type.
		$source_fields = get_post_meta( $dataset_id, 'source_fields', true );
		if ( ! empty( $source_fields ) ) {
			foreach ( $source_fields as $source_field ) {
				// Include only fields that are set to show.
				if ( ! isset( $source_field['field_show'] ) || 'on' !== $source_field['field_show'] ) {
					continue;
				}

				$item_data['properties'][ $source_field['field_display_label'] ] = get_post_meta( $location_id, 'field_' . $source_field['field_label'], true );
			}
		}

		// Get tooltip information.
		$item_data['properties']['tooltip'] = Locations::get_location_tooltip( $dataset_id, $location_id );

		// Check if the post has a featured image and add it to the item data.
		if ( get_the_post_thumbnail_url( $location_id, 'large' ) ) {
			$thumb_image                               = get_the_post_thumbnail_url( $location_id, 'large' );
			$thumb_id                                  = get_post_thumbnail_id( $location_id );
			$item_data['properties']['post_thumbnail'] = self::create_image_output( $thumb_id, $thumb_image );
		}

		// Get marker information.
		$item_marker                                = Locations::get_location_marker( $dataset_id, $location_id );
		$item_data['properties']['marker']['color'] = $item_marker['color'];
		$item_data['properties']['marker']['icon']  = Locations::get_location_marker_url( $item_marker['icon'] );
		// In new versions of the Frontend plugin, we use icon_name, but for backwards compatibility we also keep icon.
		$item_data['properties']['marker']['icon_name'] = $item_marker['icon'];

		if ( isset( $projection ) ) {
			$item_data = Conversion::convert_coordinates( $item_data, $projection );
		}

		return $item_data;
	}

	/**
	 * Create image output.
	 *
	 * @param int    $id The attachment ID.
	 * @param string $image The image URL.
	 *
	 * @return array The image data.
	 */
	public static function create_image_output( $id, $image ) {
		$attachment_meta = wp_get_attachment_metadata( $id );
		$attachment      = get_post( $id );

		if ( ! $attachment_meta || ! $attachment ) {
			return [];
		}

		$output = [
			'id'          => $id,
			'url'         => $image,
			'width'       => $attachment_meta['width'],
			'height'      => $attachment_meta['height'],
			'filesize'    => $attachment_meta['filesize'],
			'alt'         => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'     => wp_get_attachment_caption( $id ),
			'description' => $attachment->post_content,
		];

		return $output;
	}
}
