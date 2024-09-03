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
		add_action( 'after_setup_theme', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'after_setup_theme' ] );

		add_action( 'manage_owc_ok_location_posts_custom_column', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'location_posts_columns' ], 10, 2 );
		add_filter( 'manage_owc_ok_location_posts_columns', [ 'Openkaarten_Base_Plugin\Admin\Admin', 'manage_location_posts_columns' ] );
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

		// Get extra post types to use OpenKaarten from the settings.
		$openkaarten_post_types = apply_filters( 'openkaarten_base_post_types', [ 'owc_ok_location' ] );
		$openkaarten_post_types = array_merge( [ 'owc_ok_datalayer' ], $openkaarten_post_types );

		if ( ! in_array( $screen->id, $openkaarten_post_types, true ) ) {
			return;
		}

		wp_enqueue_script(
			'cmb2-conditional-logic',
			plugin_dir_url( __FILE__ ) . 'js/cmb2-conditional-logic.js',
			[ 'jquery', 'cmb2-scripts' ],
			filemtime( plugin_dir_path( __FILE__ ) . 'js/cmb2-conditional-logic.js' ),
			true
		);

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

		wp_enqueue_style(
			'owc_ok-font-awesome',
			'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',
			[],
			OWC_OPENKAARTEN_BASE_VERSION
		);

		wp_enqueue_style(
			'owc_ok-openstreetmap',
			self::mix( '/styles/openstreetmap.css' ),
			[],
			OWC_OPENKAARTEN_BASE_VERSION
		);

		wp_enqueue_script(
			'owc_ok-openstreetmap',
			self::mix( '/scripts/openstreetmap.js' ),
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
	 * Check if CMB2 plugin is installed and activated
	 *
	 * @return void
	 */
	public static function check_plugin_dependency() {
		if (
			! is_plugin_active( 'cmb2/init.php' )
			&& is_plugin_active( 'plugin-openkaarten-base/openkaarten-base.php' )
		) {
			set_transient( 'owc_ok_transient', __( 'The plugin OpenKaarten Base requires CMB2 plugin to be installed and activated. The plugin has been deactivated.', 'openkaarten-base' ), 100 );
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
		// Add location datalayer calue as a column.
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
}
