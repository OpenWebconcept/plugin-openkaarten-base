<?php
/**
 * Imports Geo files.
 *
 * @link              https://www.openwebconcept.nl
 * @package           Openkaarten_Base_Plugin
 * @subpackage        Openkaarten_Base_Plugin/Admin
 */

namespace Openkaarten_Base_Plugin\Admin;

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
		add_action( 'update_post_meta', [ 'Openkaarten_Base_Plugin\Admin\Importer', 'update_post_meta' ], 5, 4 );
		add_action( 'admin_init', [ 'Openkaarten_Base_Plugin\Admin\Importer', 'handle_sync_import_file' ] );
		add_action( 'admin_notices', [ 'Openkaarten_Base_Plugin\Admin\Importer', 'show_sync_notice' ] );

		// Add cronjob which syncs the locations in the datalayer every hour.
		add_action( 'openkaarten_sync_datalayer_locations', [ 'Openkaarten_Base_Plugin\Admin\Importer', 'sync_datalayer_locations' ] );
		if ( ! wp_next_scheduled( 'openkaarten_sync_datalayer_locations' ) ) {
			wp_schedule_event( time(), 'hourly', 'openkaarten_sync_datalayer_locations' );
		}
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
		if ( 'title_field_mapping' === $meta_key ) {

			self::import_geo_file( $post_id, $meta_key, $meta_value );

		} elseif ( 'datalayer_url' === $meta_key ) {

			//phpcs:ignore WordPress.Security.NonceVerification.Missing -- We need to check the POST data.
			$datalayer_url_type = isset( $_POST['datalayer_url_type'] ) ? sanitize_text_field( wp_unslash( $_POST['datalayer_url_type'] ) ) : null;

			if ( empty( $datalayer_url_type ) ) {
				return;
			}

			// Delete title field mapping.
			delete_post_meta( $post_id, 'title_field_mapping' );

			// Delete last import date.
			delete_post_meta( $post_id, 'datalayer_last_import' );

			// Delete source fields.
			delete_post_meta( $post_id, 'source_fields' );

			// Delete locations.
			Datalayers::delete_datalayer_locations( $post_id );

			// Clear POST data.
			//phpcs:ignore WordPress.Security.NonceVerification.Missing -- We need to unset the POST data.
			if ( isset( $_POST['sync_import_file'] ) ) {
				unset( $_POST['sync_import_file'] );
			}
			//phpcs:ignore WordPress.Security.NonceVerification.Missing -- We need to unset the POST data.
			if ( isset( $_POST['source_fields'] ) ) {
				unset( $_POST['source_fields'] );
			}
			//phpcs:ignore WordPress.Security.NonceVerification.Missing -- We need to unset the POST data.
			if ( isset( $_POST['title_field_mapping'] ) ) {
				unset( $_POST['title_field_mapping'] );
			}

			// Retrieve the property for the title field mapping.
			$source_fields = Datalayers::get_datalayer_source_fields(
				$post_id,
				'url',
				$datalayer_url_type,
				$meta_value
			);

			if ( ! empty( $source_fields ) ) {
				$title_fields = '{' . $source_fields[0] . '}';
			} else {
				$title_fields = '{title}';
			}

			// Don't use update_post_meta but save it directly to the database.
			// This is because the update_post_meta function will trigger the import and we don't want that here.
			global $wpdb;

			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom query is required here.
			$wpdb->insert(
				$wpdb->postmeta,
				[
					//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- We need to insert the meta value.
					'meta_value' => $title_fields,
					'post_id'    => $post_id,
					//phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- We need to insert the meta key.
					'meta_key'   => 'title_field_mapping',
				]
			);

			// Update the field mapping and set all source fields to show.
			if ( ! empty( $source_fields ) ) {
				$source_fields_db = [];
				foreach ( $source_fields as $field ) {
					add_post_meta( $post_id, 'field_' . $field . '_type', 'text' );
					add_post_meta( $post_id, 'field_' . $field, $field );
					add_post_meta( $post_id, 'field_' . $field . '_show', '1' );
					add_post_meta( $post_id, 'field_' . $field . '_required', '0' );
					$source_fields_db[] = [
						'field_label'         => $field,
						'field_display_label' => $field,
						'field_type'          => 'text',
						'field_show'          => 1,
						'field_required'      => 0,
					];
				}
			}

			// Update the source fields.
			update_post_meta( $post_id, 'source_fields', $source_fields_db );

			// Import the locations.
			if ( 'import' === $datalayer_url_type ) {
				self::import_locations( $post_id, $title_fields );
			}

			// Force update the datalayer url, but without update_post_meta, because this will create an infinite loop.
			$wpdb->update(
				$wpdb->postmeta,
				[
					'meta_value' => $meta_value,
				],
				[
					'post_id'  => $post_id,
					'meta_key' => 'datalayer_url',
				]
			);

			// Redirect to avoid executing other actions.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- We need to check the POST data.
			if ( isset( $_POST['_wp_http_referer'] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- We need to check the POST data.
				wp_safe_redirect( sanitize_url( wp_unslash( $_POST['_wp_http_referer'] ) ) );
				exit;
			}
		}
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

		// Skip import if URL type is live instead of import.
		$datalayer_url_type = get_post_meta( $post_id, 'datalayer_url_type', true );
		if ( 'live' === $datalayer_url_type ) {
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
		$source_fields = wp_unslash( $_POST['source_fields'] );
		self::update_field_mapping( $post_id, $source_fields );
		self::import_locations( $post_id, $meta_value );
	}

	/**
	 * Update the field mapping.
	 *
	 * @param int   $post_id       The post ID.
	 * @param array $source_fields The source fields.
	 *
	 * @return bool
	 */
	public static function update_field_mapping( $post_id, $source_fields ) {
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

		return true;
	}

	/**
	 * Import the locations.
	 *
	 * @param int    $post_id    The post ID.
	 * @param string $title_field_value The title field value.
	 *
	 * @return bool
	 */
	public static function import_locations( $post_id, $title_field_value ) {
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

			// Check if the geo response is a single Geometry or a GeometryCollection.
			if ( 'GeometryCollection' === $geom->geometryType() ) {
				$components = $geom->getComponents();
			} else {
				$components = [ $geom ];
			}

			// Catch all the fields in brackets from the title fields and replace them with the actual values.
			$title_fields = $title_field_value;
			if ( empty( $title_fields ) ) {
				$title_fields = '{' . array_key_first( $components[0]->getData() ) . '}';
			}

			// If still empty, look for a title field.
			if ( empty( $title_fields ) ) {
				$title_fields = '{title}';
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

					$title = self::create_title_from_mapping( $properties, $title_fields );

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

					// Set date of last import.
					update_post_meta( $post_id, 'datalayer_last_import', current_time( 'mysql' ) );
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

			return false;
		}
	}

	/**
	 * Create the title from the mapping.
	 * This function replaces the fields in the title with the actual values.
	 *
	 * @param array  $properties The properties.
	 * @param string $title      The title.
	 *
	 * @return string The title.
	 */
	public static function create_title_from_mapping( $properties, $title ) {
		if ( empty( $properties ) ) {
			return $title;
		}

		foreach ( $properties as $key => $value ) {
			// Skip if it's an array or object.
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}

			$value = is_null( $value ) ? '' : $value;

			$title = str_replace( '{' . $key . '}', $value, $title );
		}

		return $title;
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

	/**
	 * Handle the sync import file.
	 *
	 * @return void
	 */
	public static function handle_sync_import_file() {
		// Check if the form has been submitted by looking for our hidden input.
		if ( isset( $_POST['sync_import_file'] ) && isset( $_POST['submit_sync_import_file'] ) ) {

			// Check nonce validation.
			if ( ! isset( $_POST['openkaarten_cmb2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openkaarten_cmb2_nonce'] ) ), 'openkaarten_cmb2_nonce' ) ) {
				return;
			}

			// Verify the user has permission to perform this action.
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}

			// Get the post ID from the hidden input.
			$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : null;

			if ( ! $post_id ) {
				return;
			}

			// Perform the import.
			$title_field_value = get_post_meta( $post_id, 'title_field_mapping', true );
			$response_import   = self::import_locations( $post_id, $title_field_value );

			// Redirect to avoid re-submission on page reload.
			if ( $response_import && isset( $_POST['redirect_url'] ) ) {
				wp_safe_redirect( add_query_arg( 'synced', '1', sanitize_url( wp_unslash( $_POST['redirect_url'] ) ) ) );
				exit;
			}
		}
	}

	/**
	 * Show the sync notice.
	 *
	 * @return void
	 */
	public static function show_sync_notice() {
		$screen = get_current_screen();

		// Check if on post edit screen and if 'synced' parameter is set.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- We need to check the $_GET parameter.
		if ( 'post' === $screen->base && isset( $_GET['synced'] ) ) {
			echo '<div class="notice notice-success is-dismissible">
	            <p>' . esc_html__( 'Sync completed successfully!', 'openkaarten-base' ) . '</p>
	        </div>';
		}
	}

	/**
	 * Sync the datalayer locations. Function is called by a cronjob.
	 *
	 * @return void
	 */
	public static function sync_datalayer_locations() {
		// Get all datalayers which are of type url and are of url type import.
		$args = [
			'post_type'      => 'owc_ok_datalayer',
			'posts_per_page' => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- We need to query on meta values.
			'meta_query'     => [
				[
					'key'     => 'datalayer_url_type',
					'value'   => 'live',
					'compare' => '!=',
				],
				[
					'key'     => 'datalayer_type',
					'value'   => 'url',
					'compare' => '=',
				],
			],
		];

		$datalayers = get_posts( $args );

		foreach ( $datalayers as $datalayer ) {
			$title_field_value = get_post_meta( $datalayer->ID, 'title_field_mapping', true );
			self::import_locations( $datalayer->ID, $title_field_value );
		}
	}
}
