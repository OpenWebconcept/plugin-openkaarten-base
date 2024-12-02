<?php
/**
 * Imports Geo files.
 *
 * @link              https://www.openwebconcept.nl
 * @package           Openkaarten_Base_Plugin
 * @subpackage        Openkaarten_Base_Plugin/Admin
 */

namespace Openkaarten_Base_Plugin\Admin;

use geoPHP\Adapter\GeoJSON;
use geoPHP\Exception\IOException;
use geoPHP\geoPHP;
use Openkaarten_Base_Plugin\Conversion;

/**
 * Imports Geo files.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Acato <richardkorthuis@acato.nl>
 */
class Importer {
	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Importer|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Initialize the class and set its properties.
	 */
	private function __construct() {
		add_action( 'add_post_meta', [ 'Openkaarten_Base_Plugin\Admin\Importer', 'add_post_meta' ], 10, 3 );
		add_action( 'update_post_meta', [ 'Openkaarten_Base_Plugin\Admin\Importer', 'update_post_meta' ], 10, 4 );
	}

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Importer The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Importer();
		}

		return self::$instance;
	}

	/**
	 * Function which handles the add_post_meta action hook.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $meta_key   The meta key.
	 * @param string $meta_value The meta value.
	 *
	 * @return void
	 */
	public static function add_post_meta( $post_id, $meta_key, $meta_value ) {
		if ( 'title_field_mapping' !== $meta_key ) {
			return;
		}

		if ( empty( $meta_value ) ) {
			return;
		}

		self::import_geo_file( $post_id, $meta_key, $meta_value );
	}

	/**
	 * Function which handles the update_post_meta action hook.
	 *
	 * @param int    $meta_id    The meta ID.
	 * @param int    $post_id    The post ID.
	 * @param string $meta_key   The meta key.
	 * @param string $meta_value The meta value.
	 *
	 * @return void
	 */
	public static function update_post_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( 'title_field_mapping' !== $meta_key ) {
			return;
		}

		self::import_geo_file( $post_id, $meta_key, $meta_value );
	}

	/**
	 * Import the geo file.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $meta_key   The meta key.
	 * @param string $meta_value The meta value.
	 *
	 * @return void
	 */
	private static function import_geo_file( $post_id, $meta_key, $meta_value ) {
		if ( 'title_field_mapping' !== $meta_key ) {
			return;
		}

		// Check nonce validation.
		if ( ! isset( $_POST['openkaarten_cmb2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openkaarten_cmb2_nonce'] ) ), 'openkaarten_cmb2_nonce' ) ) {
			return;
		}

		if ( empty( $_POST['source_fields'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- We need to read the source fields.
		$source_fields          = wp_unslash( $_POST['source_fields'] );
		$original_source_fields = get_post_meta( $post_id, 'source_fields', true ) ?: [];
		if ( ! empty( $original_source_fields ) ) {
			foreach ( $original_source_fields as $field ) {
				$key = array_search( $field['field_label'], array_column( $source_fields, 'field_label' ), true );
				if ( false === $key ) {
					delete_post_meta( $post_id, 'field_' . $field['field_label'] . '_type' );
					delete_post_meta( $post_id, 'field_' . $field['field_label'] );
					delete_post_meta( $post_id, 'field_' . $field['field_label'] . '_show' );
					delete_post_meta( $post_id, 'field_' . $field['field_label'] . '_required' );
				} else {
					$source_fields[ $key ] = $field;
				}
			}
			delete_post_meta( $post_id, 'source_fields' );
		}

		add_post_meta( $post_id, 'source_fields', $source_fields );

		foreach ( $source_fields as $field ) {
			if ( empty( $original_source_fields ) || false === array_search( $field['field_label'], array_column( $original_source_fields, 'field_label' ), true ) ) {
				add_post_meta( $post_id, 'field_' . $field['field_label'] . '_type', $field['field_type'] );
				add_post_meta( $post_id, 'field_' . $field['field_label'], $field['field_label'] );
				add_post_meta( $post_id, 'field_' . $field['field_label'] . '_show', (bool) isset( $field['field_show'] ) && 'on' === $field['field_show'] );
				add_post_meta( $post_id, 'field_' . $field['field_label'] . '_required', (bool) isset( $field['field_required'] ) && 'on' === $field['field_required'] );
			} else {
				update_post_meta( $post_id, 'field_' . $field['field_label'] . '_type', $field['field_type'] );
				update_post_meta( $post_id, 'field_' . $field['field_label'], $field['field_label'] );
				update_post_meta( $post_id, 'field_' . $field['field_label'] . '_show', (bool) isset( $field['field_show'] ) && 'on' === $field['field_show'] );
				update_post_meta( $post_id, 'field_' . $field['field_label'] . '_required', (bool) isset( $field['field_required'] ) && 'on' === $field['field_required'] );
			}
		}

		self::import_locations( $post_id, $meta_value );
	}

	/**
	 * Import the locations.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $meta_value The meta value.
	 *
	 * @return void
	 */
	public static function import_locations( $post_id, $meta_value = false ) {
		$datalayer_type = get_post_meta( $post_id, 'datalayer_type', true );

		switch ( $datalayer_type ) {
			case 'fileinput':
			default:
				$file = get_attached_file( get_post_meta( $post_id, 'datalayer_file_id', true ) );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- We need to read the file contents.
				$data = file_get_contents( $file );

				break;
			case 'url':
				$data = Datalayers::fetch_datalayer_url_data( $post_id );

				break;
		}

		// Check if data is valid GeoJSON and if we can parse it. If not, a IOException will be thrown.
		try {
			$geom = geoPHP::load( $data );

			$components = $geom->getComponents();

			// Catch all the fields in brackets from the title fields and replace them with the actual values.
			$title_fields = $meta_value;
			if ( empty( $title_fields ) ) {
				$title_fields = '{' . array_key_first( $components[0]->getData() ) . '}';
			}

			if ( count( $components ) ) {
				global $wpdb;

				// Create a custom query to get the location ID's, where the location_datalayer_id is equal to the post ID.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom query is required here.
				$locations = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'owc_ok_location'
              AND p.post_status = 'publish'
              AND pm2.meta_key = 'location_datalayer_id'
              AND pm2.meta_value = %s
            ORDER BY p.post_title ASC",
						$post_id
					)
				);

				foreach ( $locations as $location ) {
					wp_delete_post( $location->ID, true );
				}

				foreach ( $components as $component ) {
					$properties = $component->getData();

					$title = $title_fields;
					foreach ( $properties as $key => $value ) {
						if ( is_array( $value ) || is_object( $value ) ) {
							continue;
						}
						$value = is_null( $value ) ? '' : $value;
						$title = str_replace( '{' . $key . '}', $value, $title );
					}

					$location    = [
						'post_title'  => $title,
						'post_type'   => 'owc_ok_location',
						'post_status' => 'publish',
					];
					$location_id = wp_insert_post( $location );

					update_post_meta( $location_id, 'location_datalayer_id', $post_id );

					foreach ( $properties as $key => $value ) {
						// Save true and false as text, so you can use it in customizing the markers.
						if ( is_bool( $value ) ) {
							$value = $value ? 'true' : 'false';
						}

						update_post_meta( $location_id, 'field_' . $key, $value );
					}

					$geometry = self::process_geometry( $component );

					update_post_meta( $location_id, 'geometry', wp_slash( $geometry ) );
				}
			}
		} catch ( IOException $e ) {
			// Add error message via admin notice.
			add_action(
				'admin_notices',
				function () use ( $e ) {
					?>
					<div class="notice notice-error">
						<p><?php echo esc_html__( 'The geo file is not valid and can\'t be parsed.', 'openkaarten-base' ); ?></p>
					</div>
					<?php
				}
			);

			return;
		}
	}

	/**
	 * Process the geometry.
	 *
	 * @param object $component The component.
	 *
	 * @return string The processed geometry.
	 */
	private static function process_geometry( $component ) {
		$json = $component->out( 'json' );
		$geom = json_decode( $json, true );
		$geom = Conversion::convert_coordinates( $geom, 'WGS84' );

		return wp_json_encode( $geom );
	}
}
