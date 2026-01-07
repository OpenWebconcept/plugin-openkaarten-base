<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.openwebconcept.nl
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 */

namespace Openkaarten_Base_Plugin\Admin;

use WP_Rest_Cache_Plugin\Includes\Caching\Caching;

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Acato <eyal@acato.nl>
 */
class Admin {

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Admin|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Admin The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Admin();
		}

		return self::$instance;
	}
	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'init', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'register_post_types' ] );
		add_action( 'admin_enqueue_scripts', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'admin_enqueue_scripts' ] );
		add_action( 'admin_notices', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'admin_notices' ] );
		add_action( 'admin_init', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'check_plugin_dependency' ] );
		add_action( 'admin_menu', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'add_admin_menu' ], 50 );
		add_action( 'admin_init', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'register_plugin_settings' ] );
		add_action( 'after_setup_theme', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'after_setup_theme' ] );

		add_action( 'manage_owc_ok_location_posts_custom_column', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'location_posts_columns' ], 10, 2 );
		add_filter( 'manage_owc_ok_location_posts_columns', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'manage_location_posts_columns' ] );

		add_action( 'manage_owc_ok_datalayer_posts_custom_column', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'datalayer_posts_columns' ], 10, 2 );
		add_filter( 'manage_owc_ok_datalayer_posts_columns', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'manage_datalayer_posts_columns' ] );

		add_action( 'save_post', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'flush_cache_for_specific_endpoints' ], 10, 1 );
	}

	/**
	 * Just a little helper to get filenames from the mix-manifest file.
	 *
	 * @param string $path to file.
	 *
	 * @return string|null
	 */
	private static function mix( string $path ): ?string {
		static $manifest;
		if ( empty( $manifest ) ) {
			$manifest = OWC_OPENKAARTEN_BASE_ABSPATH . '/build/mix-manifest.json';

			if ( ! self::has_resource( $manifest ) ) {
				return OWC_OPENKAARTEN_BASE_ASSETS_URL . $path;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- We need to read the file.
			$manifest = json_decode( file_get_contents( $manifest ), true );
		}

		// We need to set the `/` in front of the `$path` due to how the mix-manifest.json file is saved.
		if ( ! str_starts_with( $path, '/' ) ) {
			$path = '/' . $path;
		}

		return ! empty( $manifest[ $path ] ) ? untrailingslashit( OWC_OPENKAARTEN_BASE_ASSETS_URL ) . $manifest[ $path ] : null;
	}

	/**
	 * Checks if file exists and if the file is populated, so we don't enqueue empty files.
	 *
	 * @param string $path ABSPATH to file.
	 *
	 * @return bool|mixed
	 */
	private static function has_resource( $path ) {

		static $resources = null;

		if ( isset( $resources[ $path ] ) ) {
			return $resources[ $path ];
		}

		// Check if resource exists and has content.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$resources[ $path ] = @file_exists( $path ) && 0 < (int) @filesize( $path );

		return $resources[ $path ];
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @return void
	 */
	public static function admin_enqueue_scripts() {
		// Only include the script on the kaarten edit pages.
		$screen = get_current_screen();

		if ( ! in_array( $screen->id, [ 'owc_ok_datalayer' ], true ) ) {
			return;
		}

		wp_enqueue_script(
			'owc_ok-cmb2-custom-js',
			plugin_dir_url( __FILE__ ) . 'js/cmb2-custom.js',
			[ 'jquery' ],
			filemtime( plugin_dir_path( __FILE__ ) . 'js/cmb2-custom.js' ),
			true
		);

		wp_enqueue_script(
			'owc_ok-custom-js',
			plugin_dir_url( __FILE__ ) . 'js/custom.js',
			[ 'jquery' ],
			filemtime( plugin_dir_path( __FILE__ ) . 'js/custom.js' ),
			true
		);

		wp_enqueue_script(
			'cmb2-conditional-logic',
			plugin_dir_url( __FILE__ ) . 'js/cmb2-conditional-logic.js',
			array( 'jquery', 'cmb2-scripts' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/cmb2-conditional-logic.js' ),
			true
		);

		wp_enqueue_style(
			'owc_ok-font-awesome',
			'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
			[],
			OWC_OPENKAARTEN_BASE_VERSION
		);

		wp_enqueue_style(
			'owc_ok-openstreetmap-base',
			self::mix( '/styles/openstreetmap-base.css' ),
			[],
			OWC_OPENKAARTEN_BASE_VERSION
		);

		wp_enqueue_script(
			'owc_ok-openstreetmap-base',
			self::mix( '/scripts/openstreetmap-base.js' ),
			[],
			OWC_OPENKAARTEN_BASE_VERSION,
			true
		);

		wp_enqueue_style(
			'owc_ok-openstreetmap-geodata',
			self::mix( '/styles/openstreetmap-geodata.css' ),
			[],
			OWC_OPENKAARTEN_BASE_VERSION
		);

		wp_enqueue_script(
			'owc_ok-openstreetmap-geodata',
			self::mix( '/scripts/openstreetmap-geodata.js' ),
			[],
			OWC_OPENKAARTEN_BASE_VERSION,
			true
		);
	}

	/**
	 * Show admin notices
	 *
	 * @return void
	 */
	public static function admin_notices() {
		$error_message = get_transient( 'owc_ok_transient' );

		if ( $error_message ) {
			echo "<div class='error'><p>" . esc_html( $error_message ) . '</p></div>';
		}
	}

	/**
	 * This function is used to create the settings page for Owc_Openkaarten_Base_Plugin
	 *
	 * @return  void
	 */
	public static function add_admin_menu() {
		add_options_page(
			__( 'OpenKaarten Settings', 'openkaarten-base' ),
			__( 'OpenKaarten', 'openkaarten-base' ),
			'manage_options',
			'openkaarten-base-settings',
			[ self::class, 'settings_page' ]
		);
	}

	/**
	 * This function is used to create the settings group
	 *
	 * @return  void
	 */
	public static function register_plugin_settings() {
		$args     = [
			'type'              => 'float',
			'sanitize_callback' => 'floatval',
		];
		$args_lat = array_merge(
			$args,
			[
				'default' => 52.0, // Default to the Netherlands area.
			]
		);
		$args_lon = array_merge(
			$args,
			[
				'default' => 4.75, // Default to the Netherlands area.
			]
		);
		register_setting( 'openkaarten-base-settings-group', 'openkaarten_base_default_lat', $args_lat );
		register_setting( 'openkaarten-base-settings-group', 'openkaarten_base_default_lon', $args_lon );
		register_setting(
			'openkaarten-base-settings-group',
			'openkaarten_base_default_zoom',
			[
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
				'default'           => 8,
			]
		);
	}

	/**
	 * This function add the html for the options page
	 *
	 * @return  void
	 */
	public static function settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'OpenKaarten Base Settings', 'openkaarten-base' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'openkaarten-base-settings-group' ); ?>
				<?php do_settings_sections( 'openkaarten-base-settings-group' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="openkaarten_base_default_lat"><?php esc_html_e( 'Default Latitude', 'openkaarten-base' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="openkaarten_base_default_lat"
								name="openkaarten_base_default_lat"
								value="<?php echo esc_attr( get_option( 'openkaarten_base_default_lat', 52.0 ) ); ?>"
							/>
							<p class="description"><?php esc_html_e( 'Set the default latitude for maps.', 'openkaarten-base' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="openkaarten_base_default_lon"><?php esc_html_e( 'Default Longitude', 'openkaarten-base' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="openkaarten_base_default_lon"
								name="openkaarten_base_default_lon"
								value="<?php echo esc_attr( get_option( 'openkaarten_base_default_lon', 4.75 ) ); ?>"
							/>
							<p class="description"><?php esc_html_e( 'Set the default longitude for maps.', 'openkaarten-base' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="openkaarten_base_default_zoom"><?php esc_html_e( 'Default Zoom Level', 'openkaarten-base' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="openkaarten_base_default_zoom"
								name="openkaarten_base_default_zoom"
								value="<?php echo esc_attr( get_option( 'openkaarten_base_default_zoom', 8 ) ); ?>"
								min="0"
								max="19"
							/>
							<p class="description"><?php esc_html_e( 'Set the default zoom level for maps (0-19).', 'openkaarten-base' ); ?></p>
						</td>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Check if CMB2 plugin is installed and activated
	 *
	 * @return void
	 */
	public static function check_plugin_dependency() {
		if (
			(
				! is_plugin_active( 'cmb2/init.php' )
				|| ! is_plugin_active( 'cmb2-flexible-content/cmb2-flexible-content-field.php' )
			)
			&& is_plugin_active( 'plugin-openkaarten-base/openkaarten-base.php' )
		) {
			set_transient( 'owc_ok_transient', __( 'The plugin OpenKaarten Base requires CMB2 plugin and CMB2 Field Type: Flexible Content plugin to be installed and activated. The plugin has been deactivated.', 'openkaarten-base' ), 100 );
			deactivate_plugins( 'plugin-openkaarten-base/openkaarten-base.php' );
		} else {
			delete_transient( 'owc_ok_transient' );
		}
	}

	/**
	 * Register the Event and Location post types.
	 *
	 * @return void
	 */
	public static function register_post_types() {
		$labels = [
			'name'               => __( 'Locations', 'openkaarten-base' ),
			'singular_name'      => __( 'Location', 'openkaarten-base' ),
			'menu_name'          => __( 'Locations', 'openkaarten-base' ),
			'name_admin_bar'     => __( 'Location', 'openkaarten-base' ),
			'add_new'            => __( 'Add New', 'openkaarten-base' ),
			'add_new_item'       => __( 'Add New Location', 'openkaarten-base' ),
			'new_item'           => __( 'New Location', 'openkaarten-base' ),
			'edit_item'          => __( 'Edit Location', 'openkaarten-base' ),
			'view_item'          => __( 'View Location', 'openkaarten-base' ),
			'all_items'          => __( 'All Locations', 'openkaarten-base' ),
			'search_items'       => __( 'Search Locations', 'openkaarten-base' ),
			'parent_item_colon'  => __( 'Parent Locations:', 'openkaarten-base' ),
			'not_found'          => __( 'No maps found.', 'openkaarten-base' ),
			'not_found_in_trash' => __( 'No maps found in Trash.', 'openkaarten-base' ),
		];

		$args = [
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-location',
			'hierarchical' => true,
			'supports'     => [ 'title', 'excerpt', 'thumbnail' ],
			'taxonomies'   => [],
			'has_archive'  => false,
			'rewrite'      => [ 'slug' => 'location' ],
			'show_in_rest' => true,
		];

		register_post_type( 'owc_ok_location', $args );
	}

	/**
	 * Add theme support for post thumbnails
	 *
	 * @return void
	 */
	public static function after_setup_theme() {
		add_theme_support( 'post-thumbnails' );
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param array $columns The columns.
	 *
	 * @return array Modified columns.
	 */
	public static function manage_location_posts_columns( $columns ) {
		// Add location datalayer as a column.
		$new_columns = [
			'location_datalayer' => __( 'Datalayer', 'openkaarten-base' ),
		];

		return array_merge( $columns, $new_columns );
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param string $column_key The column key.
	 * @param int    $post_id    The post ID.
	 *
	 * @return void
	 */
	public static function location_posts_columns( $column_key, $post_id ) {
		// Add location datalayer value as a column.
		if ( 'location_datalayer' === $column_key ) {
			$datalayer = get_post_meta( $post_id, 'location_datalayer_id', true );
			if ( $datalayer ) {
				$datalayer_name = get_the_title( $datalayer );
				printf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( get_edit_post_link( $datalayer ) ),
					esc_html( $datalayer_name )
				);
			}
		}
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param array $columns The columns.
	 *
	 * @return array Modified columns.
	 */
	public static function manage_datalayer_posts_columns( $columns ) {
		// Add datalayer type as a column.
		$new_columns = [
			'datalayer_type'        => __( 'Datalayer type', 'openkaarten-base' ),
			'datalayer_last_import' => __( 'Last import', 'openkaarten-base' ),
		];

		return array_merge( $columns, $new_columns );
	}

	/**
	 * Add custom column to the post list.
	 *
	 * @param string $column_key The column key.
	 * @param int    $post_id    The post ID.
	 *
	 * @return void
	 */
	public static function datalayer_posts_columns( $column_key, $post_id ) {
		// Add location datalayer calue as a column.
		if ( 'datalayer_type' === $column_key ) {
			$datalayer_type     = get_post_meta( $post_id, 'datalayer_type', true );
			$datalayer_url_type = get_post_meta( $post_id, 'datalayer_url_type', true );
			if ( $datalayer_type ) {
				echo esc_html( $datalayer_type );
			}
			if ( 'url' === $datalayer_type && $datalayer_url_type ) {
				echo ' (' . esc_html( $datalayer_url_type ) . ')';
			}
		} elseif ( 'datalayer_last_import' === $column_key ) {
			$datalayer_url_type = get_post_meta( $post_id, 'datalayer_url_type', true );

			if ( 'live' === $datalayer_url_type ) {
				return;
			}

			$datalayer_last_synced = get_post_meta( $post_id, 'datalayer_last_import', true );
			if ( $datalayer_last_synced ) {
				echo esc_html( $datalayer_last_synced );
			}
		}
	}

	/**
	 * Remove the cashed post from the cache.
	 *
	 * @param int $post_id The current post_id.
	 *
	 * @return void
	 */
	public static function flush_cache_for_specific_endpoints( $post_id = null ) {
		// Check if the post is saved or updated.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! is_plugin_active( 'wp-rest-cache/wp-rest-cache.php' ) ) {
			return;
		}

		// Check post type.
		$post_type = get_post_type( $post_id );

		switch ( $post_type ) {
			case 'owc_ok_location':
				// Get datalayer ID.
				$datalayer_id = get_post_meta( $post_id, 'location_datalayer_id', true );

				if ( ! $datalayer_id ) {
					return;
				}

				Caching::get_instance()->delete_cache_by_endpoint( '%/owc/openkaarten/v1/datasets', Caching::FLUSH_LOOSE, true );
				Caching::get_instance()->delete_cache_by_endpoint( '%/owc/openkaarten/v1/datasets/id/' . $datalayer_id, Caching::FLUSH_LOOSE, true );
				break;
			case 'owc_ok_datalayer':
				Caching::get_instance()->delete_cache_by_endpoint( '%/owc/openkaarten/v1/datasets', Caching::FLUSH_LOOSE, true );
				Caching::get_instance()->delete_cache_by_endpoint( '%/owc/openkaarten/v1/datasets/id/' . $post_id, Caching::FLUSH_LOOSE, true );
				break;
			default:
				break;
		}
	}
}
