<?php
/**
 * Adds datalayers post type and support for mapping fieldlabels.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Richard Korthuis <richardkorthuis@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Admin;

use geoPHP\Exception\IOException;
use geoPHP\geoPHP;

/**
 * Adds datalayers post type and support for mapping fieldlabels.
 */
class Datalayers {

	/**
	 * The CMB2 object id.
	 *
	 * @var int
	 */
	private static $cmb_object_id;

	/**
	 * The datalayer type.
	 *
	 * @var string
	 */
	private static $datalayer_type;

	/**
	 * The datalayer URL type.
	 *
	 * @var string
	 */
	private static $datalayer_url_type;

	/**
	 * The datalayer URL.
	 *
	 * @var string
	 */
	private static $datalayer_url;

	/**
	 * Whether the datalayer fetch failed.
	 *
	 * @var bool
	 */
	private static $datalayer_fetch_error = false;

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
			self::$instance = new Datalayers();
		}

		return self::$instance;
	}

	/**
	 * Initialize the class and set its properties.
	 *
	 * @return void
	 */
	private function __construct() {
		// Define global $cmb object id.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is not needed here.
		if ( ! empty( $_POST['post_ID'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is not needed here.
			self::$cmb_object_id = intval( $_POST['post_ID'] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not needed here.
		if ( ! empty( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not needed here.
			self::$cmb_object_id = intval( $_GET['post'] );
		}

		add_action( 'init', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'register_datalayer_post_type' ] );
		add_action( 'wp_trash_post', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'delete_datalayer_locations' ], 10, 1 );

		// Check the post type of the CMB object, otherwise this code is also executed on other post types.
		$post_type = get_post_type( self::$cmb_object_id );
		if ( $post_type && 'owc_ok_datalayer' !== $post_type ) {
			return;
		}

		add_action( 'cmb2_admin_init', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'add_datalayer_metaboxes' ] );
		add_action( 'cmb2_admin_init', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'add_tooltip_metaboxes' ] );
		add_action( 'cmb2_admin_init', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'add_markers_metaboxes' ] );
		add_action( 'cmb2_after_form', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'cmb2_after_form_do_js_validation' ] );
		add_filter( 'cmb2_override_source_fields_meta_value', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'override_source_fields_meta_value' ], 10, 2 );
		add_action( 'cmb2_save_post_fields', [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'cmb2_save_datalayer_fields' ], 10, 4 );
	}

	/**
	 * Register the datalayer post type.
	 *
	 * @return void
	 */
	public static function register_datalayer_post_type() {
		$labels = [
			'name'               => _x( 'Datalayers', 'post type general name', 'openkaarten-base' ),
			'singular_name'      => _x( 'Datalayer', 'post type singular name', 'openkaarten-base' ),
			'menu_name'          => _x( 'Datalayers', 'admin menu', 'openkaarten-base' ),
			'name_admin_bar'     => _x( 'Datalayer', 'add new on admin bar', 'openkaarten-base' ),
			'add_new'            => _x( 'Add New', 'datalayer', 'openkaarten-base' ),
			'add_new_item'       => __( 'Add New Datalayer', 'openkaarten-base' ),
			'new_item'           => __( 'New Datalayer', 'openkaarten-base' ),
			'edit_item'          => __( 'Edit Datalayer', 'openkaarten-base' ),
			'view_item'          => __( 'View Datalayer', 'openkaarten-base' ),
			'all_items'          => __( 'All Datalayers', 'openkaarten-base' ),
			'search_items'       => __( 'Search Datalayers', 'openkaarten-base' ),
			'parent_item_colon'  => __( 'Parent Datalayers:', 'openkaarten-base' ),
			'not_found'          => __( 'No datalayers found.', 'openkaarten-base' ),
			'not_found_in_trash' => __( 'No datalayers found in Trash.', 'openkaarten-base' ),
		];

		$args = [
			'labels'             => $labels,
			'description'        => __( 'Description.', 'openkaarten-base' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-admin-site',
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => [ 'title' ],
			'taxonomies'         => [],
		];

		register_post_type( 'owc_ok_datalayer', $args );
	}

	/**
	 * Add the datalayer metaboxes.
	 *
	 * @return void
	 */
	public static function add_datalayer_metaboxes() {
		$cmb = new_cmb2_box(
			[
				'id'           => 'datalayer_metabox',
				'title'        => __( 'Datalayer', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_datalayer_metabox' ],
			]
		);

		$cmb->add_field(
			[
				'name'       => __( 'Datalayer Type', 'openkaarten-base' ),
				'id'         => 'datalayer_type',
				'type'       => 'radio_inline',
				'options'    => [
					'fileinput' => __( 'File', 'openkaarten-base' ),
					'url'       => __( 'URL', 'openkaarten-base' ),
				],
				'attributes' => [
					'data-validation' => 'required',
				],
				'default'    => 'fileinput',
			]
		);

		$cmb->add_field(
			[
				'name'       => __( 'Datalayer File', 'openkaarten-base' ),
				'id'         => 'datalayer_file',
				'type'       => 'file',
				'options'    => [
					'url' => false,
				],
				'attributes' => [
					'data-conditional-id'    => 'datalayer_type',
					'data-conditional-value' => 'fileinput',
				],
			]
		);

		// Check if datalayer type is set. If not, then check if the post is updated and get the datalayer type from the post.
		self::$datalayer_type = get_post_meta( self::$cmb_object_id, 'datalayer_type', true );

		if ( empty( self::$datalayer_type ) && isset( $_POST['datalayer_type'] ) && ! empty( sanitize_text_field( wp_unslash( $_POST['datalayer_type'] ) ) ) ) {
			// Check nonce.
			if ( ! isset( $_POST['openkaarten_cmb2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openkaarten_cmb2_nonce'] ) ), 'openkaarten_cmb2_nonce' ) ) {
				return;
			}

			if ( ! empty( sanitize_text_field( wp_unslash( $_POST['datalayer_type'] ) ) ) ) {
				self::$datalayer_type = sanitize_text_field( wp_unslash( $_POST['datalayer_type'] ) );
			}
		}

		// Check if datalayer type is set. If not, then check if the post is updated and get the datalayer type from the post.
		// If the datalayer type is set, then disable the field.
		self::$datalayer_url = get_post_meta( self::$cmb_object_id, 'datalayer_url', true );
		if ( empty( self::$datalayer_type ) && isset( $_POST['datalayer_url'] ) && ! empty( sanitize_text_field( wp_unslash( $_POST['datalayer_url'] ) ) ) ) {
			// Check nonce.
			if ( ! isset( $_POST['openkaarten_cmb2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['openkaarten_cmb2_nonce'] ) ), 'openkaarten_cmb2_nonce' ) ) {
				return;
			}

			self::$datalayer_url = sanitize_text_field( wp_unslash( $_POST['datalayer_url'] ) );
		}

		$cmb->add_field(
			[
				'name'       => __( 'Datalayer URL', 'openkaarten-base' ),
				'id'         => 'datalayer_url',
				'type'       => 'text_url',
				'desc'       => __( 'Insert a valid URL. The URL must be accessible by the server and the output has to be a supported format: JSON, geoJSON, KML or XML.', 'openkaarten-base' ) .
								'<br /><span style="color: red;"><strong>' . __( 'Be aware: updating this field will automatically reset the title field mapping and the field mapping and will remove and re-import all locations for the import URL type datalayers.', 'openkaarten-base' ) . '</strong></span>',
				'attributes' => [
					'data-conditional-id'    => 'datalayer_type',
					'data-conditional-value' => 'url',
				],
				'after'      => self::$datalayer_url ? '<p><a href="' . self::$datalayer_url . '" class="button" target="_blank">' . __( 'View source data', 'openkaarten-base' ) . '</a></p>' : '',
			]
		);

		$cmb->add_field(
			[
				'name'       => __( 'Datalayer URL type', 'openkaarten-base' ),
				'id'         => 'datalayer_url_type',
				'type'       => 'radio_inline',
				'desc'       => __( 'Select import to import locations: locations will be synced automatically every hour or manually with the sync button. Select live to retrieve the datalayers from the source directly without importing them.', 'openkaarten-base' ),
				'options'    => [
					'import' => __( 'Import', 'openkaarten-base' ),
					'live'   => __( 'Live', 'openkaarten-base' ),
				],
				'default'    => 'import',
				'attributes' => [
					'data-conditional-id'    => 'datalayer_type',
					'data-conditional-value' => 'url',
				],
			]
		);

		$cmb = new_cmb2_box(
			[
				'id'           => 'datalayer_import_metabox',
				'title'        => __( 'Datalayer Import', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_import_sync_metabox' ],
			]
		);

		$cmb->add_field(
			[
				'name' => __( 'Datalayer Import', 'openkaarten-base' ),
				'id'   => 'datalayer_import_sync',
				'type' => 'import_sync',
			]
		);

		if ( ! self::$cmb_object_id ) {
			return;
		}

		$cmb = new_cmb2_box(
			[
				'id'           => 'title_field_mapping_metabox',
				'title'        => __( 'Title field mapping', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_field_mapping_metabox' ],
			]
		);

		self::$datalayer_type     = get_post_meta( $cmb->object_id(), 'datalayer_type', true );
		self::$datalayer_url_type = get_post_meta( $cmb->object_id(), 'datalayer_url_type', true ) ? : 'import';

		$source_fields = self::get_datalayer_source_fields( self::$cmb_object_id );

		if ( is_wp_error( $source_fields ) ) {
			// Add error message via admin notice.
			Helper::add_error_message( $source_fields->get_error_message() );
			self::$datalayer_fetch_error = true;

			return;
		}

		// Stop here if we can't get the source fields.
		if ( empty( $source_fields ) ) {
			$source_fields = [ 'title' ];
		}

		$source_fields = array_map(
			function ( $field ) {
				return '{' . $field . '}';
			},
			$source_fields
		);

		$cmb = new_cmb2_box(
			[
				'id'           => 'title_field_mapping_metabox',
				'title'        => __( 'Title field mapping', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_title_mapping_metabox' ],
			]
		);

		$cmb->add_field(
			[
				'name'       => __( 'Title field mapping', 'openkaarten-base' ),
				'id'         => 'title_field_mapping',
				'type'       => 'text',
				// translators: %s: source fields.
				'desc'       => sprintf( __( 'Use the source fields to compose the title of a location. Place the fields in {brackets}. You can use the following fields for this datalayer:<br />%s.', 'openkaarten-base' ), implode( ', ', $source_fields ) ) .
								'<br /><span style="color: red;"><strong>' . __( 'Be aware: updating this field will automatically sync all items!', 'openkaarten-base' ) . '</strong></span>',
				'attributes' => [
					'required'               => 'required',
					'data-conditional-id'    => 'datalayer_type',
					'data-conditional-value' => wp_json_encode( [ 'fileinput', 'url' ] ),
				],
				'default'    => ! empty( $source_fields ) ? $source_fields[0] : '',
			]
		);

		$cmb = new_cmb2_box(
			[
				'id'           => 'field_mapping_metabox',
				'title'        => __( 'Field mapping', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_field_mapping_metabox' ],
			]
		);

		$group_field_id = $cmb->add_field(
			[
				'id'           => 'source_fields',
				'type'         => 'group',
				'repeatable'   => false,
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_field_mapping_metabox' ],
				'before_group' => '<p><input type="checkbox" id="show_check_all" name="show_check_all" value="1" /><label for="show_check_all">' . __( 'Check all show checkboxes', 'openkaarten-base' ) . '</label><br />
								   <input type="checkbox" id="required_check_all" name="required_check_all" value="1" /><label for="required_check_all">' . __( 'Check all required checkboxes', 'openkaarten-base' ) . '</label></p>',
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name'       => __( 'Label from JSON', 'openkaarten-base' ),
				'id'         => 'field_type',
				'type'       => 'select',
				'options'    => [
					'text'        => __( 'Text', 'openkaarten-base' ),
					'textarea'    => __( 'Textarea', 'openkaarten-base' ),
					'wysiwyg'     => __( 'WYSIWYG', 'openkaarten-base' ),
					'text_time'   => __( 'Timepicker', 'openkaarten-base' ),
					'text_date'   => __( 'Datepicker', 'openkaarten-base' ),
					'text_url'    => __( 'URL', 'openkaarten-base' ),
					'text_number' => __( 'Number', 'openkaarten-base' ),
				],
				'show_on_cb' => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_field_mapping_metabox' ],
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name'       => __( 'Label from source', 'openkaarten-base' ),
				'id'         => 'field_label',
				'type'       => 'text',
				'attributes' => [
					'readonly' => true,
				],
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name' => __( 'Display label', 'openkaarten-base' ),
				'id'   => 'field_display_label',
				'type' => 'text',
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name' => __( 'Show', 'openkaarten-base' ),
				'id'   => 'field_show',
				'type' => 'checkbox',
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name' => __( 'Required', 'openkaarten-base' ),
				'id'   => 'field_required',
				'type' => 'checkbox',
			]
		);
	}

	/**
	 * Add the tooltip metaboxes.
	 *
	 * @return void
	 */
	public static function add_tooltip_metaboxes() {
		$cmb = new_cmb2_box(
			[
				'id'           => 'tooltip_metabox',
				'title'        => __( 'Tooltip configuration', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_field_mapping_metabox' ],
			]
		);

		$cmb->add_field(
			[
				'id'         => 'tooltip',
				'type'       => 'flexible',
				'options'    => [
					'sortable' => true,
				],
				'show_on_cb' => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_field_mapping_metabox' ],
				'layouts'    => [
					'image'  => [
						'title'  => __( 'Image', 'openkaarten-base' ),
						'fields' => [
							[
								'type' => 'text',
								'name' => __( 'Image URL', 'openkaarten-base' ),
								'id'   => 'image_url',
							],
							[
								'type' => 'text',
								'name' => __( 'Image Alt Text', 'openkaarten-base' ),
								'id'   => 'image_alt_text',
							],
						],
					],
					'title'  => [
						'title'  => __( 'Title', 'openkaarten-base' ),
						'fields' => [
							[
								'type' => 'text',
								'name' => __( 'Title', 'openkaarten-base' ),
								'id'   => 'title',
							],
						],
					],
					'meta'   => [
						'title'  => __( 'Meta', 'openkaarten-base' ),
						'fields' => [
							[
								'type' => 'text',
								'name' => __( 'Meta', 'openkaarten-base' ),
								'id'   => 'meta',
							],
						],
					],
					'text'   => [
						'title'  => __( 'Text', 'openkaarten-base' ),
						'fields' => [
							[
								'type' => 'textarea',
								'name' => __( 'Text', 'openkaarten-base' ),
								'id'   => 'text',
							],
						],
					],
					'button' => [
						'title'  => __( 'Button', 'openkaarten-base' ),
						'fields' => [
							[
								'type' => 'text',
								'name' => __( 'Button Text', 'openkaarten-base' ),
								'id'   => 'button_text',
							],
							[
								'type' => 'text',
								'name' => __( 'Button URL', 'openkaarten-base' ),
								'id'   => 'button_url',
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Returns an array of predefined marker color options.
	 *
	 * This function provides an associative array of hex color codes as keys
	 * and their corresponding color names as values. Intended for use in CMB2
	 * select fields or other UI elements that require consistent color options.
	 *
	 * @return array Associative array of hex color codes and their names.
	 */
	public static function get_marker_color_options() {
		return [
			'marker-black'          => __( 'Black', 'openkaarten-base' ),
			'marker-blue'           => __( 'Blue', 'openkaarten-base' ),
			'marker-brown'          => __( 'Brown', 'openkaarten-base' ),
			'marker-darkgray'       => __( 'Dark Gray', 'openkaarten-base' ),
			'marker-deep-purple'    => __( 'Deep Purple', 'openkaarten-base' ),
			'marker-gray'           => __( 'Gray', 'openkaarten-base' ),
			'marker-green'          => __( 'Green', 'openkaarten-base' ),
			'marker-navy-blue'      => __( 'Navy Blue', 'openkaarten-base' ),
			'marker-orange'         => __( 'Orange', 'openkaarten-base' ),
			'marker-purple'         => __( 'Purple', 'openkaarten-base' ),
			'marker-red'            => __( 'Red', 'openkaarten-base' ),
			'marker-turquoise'      => __( 'Turquoise', 'openkaarten-base' ),
			'marker-yellow'         => __( 'Yellow', 'openkaarten-base' ),
		];
	}

	/**
	 * Add the markers metaboxes.
	 *
	 * @return void
	 */
	public static function add_markers_metaboxes() {
		if ( ! self::$cmb_object_id ) {
			return;
		}

		if ( self::$datalayer_fetch_error ) {
			return;
		}

		$prefix = 'datalayer_markers_';

		$cmb = new_cmb2_box(
			[
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Datalayer markers', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_markers_metabox' ],
			]
		);

		$cmb->add_field(
			[
				'name'    => __( 'Default marker color', 'openkaarten-base' ),
				'id'      => 'default_marker_color',
				'type'    => 'select',
				'default' => 'color-blue', // default 'Blue'.
				'options' => self::get_marker_color_options(),
			]
		);

		$cmb->add_field(
			[
				'name'       => __( 'Field to customize marker on', 'openkaarten-base' ),
				'desc'       => __( 'Select the field that determines what marker should be shown.', 'openkaarten-base' ),
				'id'         => 'marker_field',
				'type'       => 'select',
				'options_cb' => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'cmb2_get_source_fields_options' ],
			]
		);

		$group_field_id = $cmb->add_field(
			[
				'id'         => 'markers',
				'type'       => 'group',
				'repeatable' => true,
				'options'    => [
					'group_title'   => __( 'Marker {#}', 'openkaarten-base' ),
					'add_button'    => __( 'Add Marker', 'openkaarten-base' ),
					'remove_button' => __( 'Remove Marker', 'openkaarten-base' ),
					'sortable'      => true,
					'closed'        => true,
				],
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name' => __( 'Field value', 'openkaarten-base' ),
				'desc' => __( 'The value that should be matched to the field. If the value matches, this marker should be used.', 'openkaarten-base' ),
				'id'   => 'field_value',
				'type' => 'text',
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name'             => __( 'Icon', 'openkaarten-base' ),
				'desc'             => __( 'Select the icon for the marker.', 'openkaarten-base' ),
				'id'               => 'marker_icon',
				'type'             => 'select',
				'show_option_none' => true,
				'options_cb'       => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'cmb2_marker_icons_options' ],
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name'    => __( 'Marker color', 'openkaarten-base' ),
				'id'      => 'marker_color',
				'type'    => 'select',
				'default' => 'color-blue', // Default 'Blue'.
				'options' => self::get_marker_color_options(),
			]
		);

		$cmb->add_group_field(
			$group_field_id,
			[
				'name' => __( 'Marker preview', 'openkaarten-base' ),
				'id'   => 'marker_preview',
				'type' => 'markerpreview',
			]
		);

		$prefix = 'datalayer_locations_';

		$cmb = new_cmb2_box(
			[
				'id'           => $prefix . 'metabox',
				'title'        => __( 'Datalayer locations', 'openkaarten-base' ),
				'object_types' => [ 'owc_ok_datalayer' ],
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_locations_metabox' ],
			]
		);

		$cmb->add_field(
			[
				'name'         => __( 'Locations', 'openkaarten-base' ),
				'id'           => $prefix . 'locations',
				'type'         => 'openstreetmap',
				'none_value'   => __( 'No locations attached', 'openkaarten-base' ),
				'show_in_rest' => true,
				'before_field' => '<p><strong>' . __( 'The following locations are attached to this datalayer:', 'openkaarten-base' ) . '</strong></p>',
				'show_on_cb'   => [ 'Openkaarten_Base_Plugin\Admin\Datalayers', 'show_locations_metabox' ],
			]
		);
	}

	/**
	 * Get the OpenGemeenten icons.
	 *
	 * @return array The OpenGemeenten icons.
	 */
	public static function get_icons() {
		$icons = [];
		foreach ( glob( plugin_dir_path( dirname( __DIR__ ) ) . 'opengemeenten-iconenset/Regular/*.svg' ) as $icon ) {
			$icon           = basename( $icon );
			$icon           = str_replace( '.svg', '', $icon );
			$icons[ $icon ] = $icon;
		}
		return $icons;
	}

	/**
	 * Check if the current screen is the correct CMB2 screen.
	 *
	 * @return bool
	 */
	public static function is_correct_cmb2_screen() {
		// Get the current screen object.
		$screen = get_current_screen();

		// Check if we are on the edit screen of a specific custom post type.
		if ( 'owc_ok_datalayer' === $screen->post_type && 'post' === $screen->base ) {
			return true;
		}

		return false;
	}

	/**
	 * Show the datalayer metabox.
	 *
	 * @return bool
	 */
	public static function show_datalayer_metabox() {
		if ( ! self::is_correct_cmb2_screen() ) {
			return false;
		}

		return true;
	}

	/**
	 * Show the title mapping metabox.
	 *
	 * @param \CMB2 $cmb The CMB2 object.
	 *
	 * @return bool
	 */
	public static function show_title_mapping_metabox( $cmb ) {
		if ( ! self::is_correct_cmb2_screen() ) {
			return false;
		}

		$datalayer_file = get_post_meta( $cmb->object_id(), 'datalayer_file', true );
		$datalayer_url  = get_post_meta( $cmb->object_id(), 'datalayer_url', true );

		return ( ! empty( $datalayer_file ) && 'fileinput' === self::$datalayer_type ) ||
				( ! empty( $datalayer_url ) && 'url' === self::$datalayer_type );
	}

	/**
	 * Show the field mapping metabox.
	 *
	 * @param \CMB2 $cmb The CMB2 object.
	 *
	 * @return bool
	 */
	public static function show_field_mapping_metabox( $cmb ) {
		if ( ! self::is_correct_cmb2_screen() ) {
			return false;
		}

		$datalayer_file = get_post_meta( $cmb->object_id(), 'datalayer_file', true );
		$datalayer_url  = get_post_meta( $cmb->object_id(), 'datalayer_url', true );

		return ( ! empty( $datalayer_file ) && 'fileinput' === self::$datalayer_type ) ||
				( ! empty( $datalayer_url ) && 'url' === self::$datalayer_type );
	}

	/**
	 * Show the import sync metabox.
	 *
	 * @param \CMB2 $cmb The CMB2 object.
	 *
	 * @return bool
	 */
	public static function show_import_sync_metabox( $cmb ) {
		if ( ! self::is_correct_cmb2_screen() ) {
			return false;
		}

		if ( 'live' === self::$datalayer_url_type ) {
			return false;
		}

		// Check if title field mapping is done.
		$title_field_mapping = get_post_meta( $cmb->object_id(), 'title_field_mapping', true );

		return 'url' === self::$datalayer_type && 'import' === self::$datalayer_url_type && ! empty( $title_field_mapping );
	}

	/**
	 * Show the markers metabox.
	 *
	 * @param \CMB2 $cmb The CMB2 object.
	 *
	 * @return bool
	 */
	public static function show_markers_metabox( $cmb ) {
		if ( ! self::is_correct_cmb2_screen() ) {
			return false;
		}

		$datalayer_file = get_post_meta( $cmb->object_id(), 'datalayer_file', true );
		$datalayer_url  = get_post_meta( $cmb->object_id(), 'datalayer_url', true );

		return ( ! empty( $datalayer_file ) || ! empty( $datalayer_url ) );
	}

	/**
	 * Show the locations metabox.
	 *
	 * @param \CMB2 $cmb The CMB2 object.
	 *
	 * @return bool
	 */
	public static function show_locations_metabox( $cmb ) {
		if ( ! self::is_correct_cmb2_screen() ) {
			return false;
		}

		if ( self::$datalayer_fetch_error ) {
			return false;
		}

		if ( 'live' === self::$datalayer_url_type ) {
			return true;
		}

		$datalayer_file      = get_post_meta( $cmb->object_id(), 'datalayer_file', true );
		$datalayer_url       = get_post_meta( $cmb->object_id(), 'datalayer_url', true );
		$title_field_mapping = get_post_meta( $cmb->object_id(), 'title_field_mapping', true );

		return ( ! empty( $datalayer_file ) || ! empty( $datalayer_url ) ) && ! empty( $title_field_mapping );
	}

	/**
	 * Get the datalayers for the dropdown.
	 *
	 * @param string $value The value.
	 * @param int    $object_id The object ID.
	 *
	 * @return mixed
	 */
	public static function override_source_fields_meta_value( $value, $object_id ) {
		// Only set the default if the original value has not been overridden,
		// and if there is no existing value.
		if ( 'cmb2_field_no_override_val' !== $value ) {
			return $value;
		}

		$source_fields = get_post_meta( $object_id, 'source_fields', true );

		if ( $source_fields ) {
			return $value;
		}

		switch ( self::$datalayer_type ) {
			case 'fileinput':
			default:
				$datalayer_file = get_post_meta( $object_id, 'datalayer_file_id', true );
				if ( ! $datalayer_file ) {
					return $value;
				}
				$file = get_attached_file( $datalayer_file );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file_get_contents is allowed here.
				$file_contents = file_get_contents( $file );

				break;
			case 'url':
				$file_contents = self::fetch_datalayer_url_data( $object_id );

				break;
		}

		// Check if data is a WP_Error.
		if ( is_wp_error( $file_contents ) ) {
			// Add error message via admin notice.
			Helper::add_error_message( $file_contents->get_error_message() );
			self::$datalayer_fetch_error = true;

			return [];
		}

		try {
			$data = geoPHP::load( $file_contents );

			if ( ! $data->getComponents() && ! $data->getComponents()[0] ) {
				return $value;
			}

			$datalayer_properties = $data->getComponents()[0]->getData();

			if ( empty( $datalayer_properties ) ) {
				return $value;
			}

			$repeater = [];

			foreach ( $datalayer_properties as $key => $val ) {
				$repeater[] = [
					'field_label'         => $key,
					'field_display_label' => $key,
					'field_show'          => false,
				];
			}

			return $repeater;
		} catch ( IOException $e ) {
			// Add error message via admin notice.
			Helper::add_error_message( esc_html__( 'The geo file is not valid and can\'t be parsed.', 'openkaarten-base' ) );
			self::$datalayer_fetch_error = true;

			return $value;
		}
	}

	/**
	 * Documentation in the wiki:
	 *
	 * @link https://github.com/WebDevStudios/CMB2/wiki/Plugin-code-to-add-JS-validation-of-%22required%22-fields
	 *
	 * @return void
	 */
	public static function cmb2_after_form_do_js_validation() {
		static $added = false;

		// Only add this to the page once (not for every metabox).
		if ( $added ) {
			return;
		}

		$added = true;
		?>
		<script type="text/javascript">
			jQuery(document).ready(function ($) {

				$form = $(document.getElementById('post'));
				$htmlbody = $('html, body');
				$toValidate = $('[data-validation]');

				if (!$toValidate.length) {
					return;
				}

				function checkValidation(evt) {
					var labels = [];
					var $first_error_row = null;
					var $row = null;

					function add_required($row) {
						$row.css({'background-color': 'rgb(255, 170, 170)'});
						$first_error_row = $first_error_row ? $first_error_row : $row;
						labels.push($row.find('.cmb-th label').text());
					}

					function remove_required($row) {
						$row.css({background: ''});
					}

					$toValidate.each(function () {
						var $this = $(this);
						var val = $this.val();
						$row = $this.parents('.cmb-row');

						if ($this.is('[type="button"]') || $this.is('.cmb2-upload-file-id')) {
							return true;
						}

						if ('required' === $this.data('validation')) {
							if ($row.is('.cmb-type-file-list')) {

								var has_LIs = $row.find('ul.cmb-attach-list li').length > 0;

								if (!has_LIs) {
									add_required($row);
								} else {
									remove_required($row);
								}

							} else {
								if (!val) {
									add_required($row);
								} else {
									remove_required($row);
								}
							}
						}

					});

					if ($first_error_row) {
						evt.preventDefault();
						// phpcs:ignore WordPressVIPMinimum.JS.AlertDebug
						alert('<?php esc_html_e( 'The following fields are required and highlighted below:', 'openkaarten-base' ); ?> ' + labels.join(', '));
						$htmlbody.animate({
							scrollTop: ($first_error_row.offset().top - 200)
						}, 1000);
					}
				}

				$form.on('submit', checkValidation);
			});
		</script>
		<?php
	}

	/**
	 * Fetch the data from an external source URL.
	 *
	 * @param int    $datalayer_id The datalayer ID.
	 * @param string $override_datalayer_url The overridden datalayer URL. This is used when calling the function from the update_post_meta hook for the datalayer URL field.
	 *
	 * @return mixed
	 */
	public static function fetch_datalayer_url_data( $datalayer_id = false, $override_datalayer_url = '' ) {
		$url = $override_datalayer_url ? : self::$datalayer_url;

		// Get URL when running the cron.
		if ( defined( 'DOING_CRON' ) && DOING_CRON && $datalayer_id ) {
			$url = get_post_meta( $datalayer_id, 'datalayer_url', true );
		}

		$response = wp_remote_get( $url );

		// Check if response is a WP_Error.
		if ( is_wp_error( $response ) ) {
			// Return an error message.
			return $response->get_error_message();
		}

		// Check if response is not a 200 OK.
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Return an error message.
			return new \WP_Error( 'error', __( 'Can\'t fetch datalayer URL.', 'openkaarten-base' ) );
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! $body ) {
			return new \WP_Error( 'no_data', __( 'No data found.', 'openkaarten-base' ) );
		}

		return $body;
	}

	/**
	 * Get the source fields from the datalayer URL.
	 *
	 * @param int|string $object_id The object ID.
	 * @param string     $override_datalayer_type The overridden datalayer type. This is used when calling the function from the update_post_meta hook for the datalayer URL field.
	 * @param string     $override_datalayer_url_type The overridden datalayer URL type. This is used when calling the function from the update_post_meta hook for the datalayer URL field.
	 * @param string     $override_datalayer_url The overridden datalayer URL. This is used when calling the function from the update_post_meta hook for the datalayer URL field.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_datalayer_source_fields( $object_id, $override_datalayer_type = false, $override_datalayer_url_type = false, $override_datalayer_url = '' ) {
		$datalayer_type     = $override_datalayer_type ? : self::$datalayer_type;
		$datalayer_url_type = $override_datalayer_url_type ? : self::$datalayer_url_type;

		// First try to retrieve the source fields from the postmeta. But only do this if the datalayer type is not 'live'.
		if ( 'live' !== $datalayer_url_type ) {
			$source_fields = get_post_meta( $object_id, 'source_fields', true );

			if ( ! empty( $source_fields ) ) {
				// Get all field labels from the source_fields array.
				$source_fields = array_map(
					function ( $field ) {
						return $field['field_label'];
					},
					$source_fields
				);

				return $source_fields;
			}
		}

		// If no source fields are set, get the source fields from the datalayer file.
		switch ( $datalayer_type ) {
			case 'fileinput':
			default:
				$file = get_attached_file( get_post_meta( $object_id, 'datalayer_file_id', true ) );

				if ( empty( $file ) ) {
					return [];
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- We need to read the file contents.
				$data = file_get_contents( $file );

				break;
			case 'url':
				$data = self::fetch_datalayer_url_data( $object_id, $override_datalayer_url );

				break;
		}

		if ( ! $data ) {
			return [];
		}

		// Check if data is a WP_Error.
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$source_fields = [];

		// Check if data is valid GeoJSON and if we can parse it. If not, a IOException will be thrown.
		try {
			$geom = geoPHP::load( $data );

			if ( ! $geom->getComponents() && ! $geom->getComponents()[0] ) {
				return [];
			}

			if ( $geom->getComponents()[0]->getData() ) {
				foreach ( $geom->getComponents()[0]->getData() as $key => $val ) {
					$source_fields[] = $key;
				}
			}
		} catch ( IOException $e ) {
			return new \WP_Error( 'error', esc_html__( 'The geo file is not valid and can\'t be parsed.', 'openkaarten-base' ) );
		}

		return $source_fields;
	}

	/**
	 * Save the datalayer fields.
	 *
	 * @param int    $object_id The object ID.
	 * @param string $cmb_id The CMB ID.
	 * @param array  $updated The updated fields.
	 * @param \CMB2  $cmb The CMB2 object.
	 *
	 * @return void
	 */
	public static function cmb2_save_datalayer_fields( $object_id, $cmb_id, $updated, $cmb ) {
		if ( 'field_mapping_metabox' === $cmb_id ) {
			$source_fields = $cmb->get_field( 'source_fields' )->value();

			if ( ! empty( $source_fields ) ) {
				foreach ( $source_fields as $field ) {
					if ( ! empty( $field['field_label'] ) && ! empty( $field['field_display_label'] ) ) {
						update_post_meta( $object_id, 'field_' . $field['field_label'], $field['field_display_label'] );
					}
					if ( ! empty( $field['field_show'] ) ) {
						update_post_meta( $object_id, 'field_' . $field['field_label'] . '_show', 'on' === $field['field_show'] );
					}
					if ( ! empty( $field['field_required'] ) ) {
						update_post_meta( $object_id, 'field_' . $field['field_label'] . '_required', 'on' === $field['field_required'] );
					}
					if ( ! empty( $field['field_type'] ) ) {
						update_post_meta( $object_id, 'field_' . $field['field_label'] . '_type', $field['field_type'] );
					}
				}
			}
		}
	}

	/**
	 * Delete all locations attached to the datalayer.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public static function delete_datalayer_locations( $post_id ) {
		// Check if post type is datalayer.
		if ( 'owc_ok_datalayer' !== get_post_type( $post_id ) ) {
			return;
		}

		// Remove all fields attached to the datalayer.
		$fields = get_post_meta( $post_id );
		foreach ( $fields as $key => $value ) {
			if ( strpos( $key, 'field_' ) === 0 ) {
				delete_post_meta( $post_id, $key );
			}
		}

		// Remove all locations attached to the datalayer.
		$locations = get_posts(
			[
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_type'      => 'owc_ok_location',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Meta key is needed here.
				'meta_key'       => 'location_datalayer_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Meta value is needed here.
				'meta_value'     => $post_id,
			]
		);

		if ( ! $locations ) {
			return;
		}

		foreach ( $locations as $location ) {
			wp_delete_post( $location, true );

			// Remove all fields attached to the location.
			$fields = get_post_meta( $location );
			foreach ( $fields as $key => $value ) {
				if ( strpos( $key, 'field_' ) === 0 ) {
					delete_post_meta( $location, $key );
				}
			}
		}
	}

	/**
	 * Get the source fields options.
	 *
	 * @param \CMB2 $cmb The CMB2 object.
	 *
	 * @return array The source fields options.
	 */
	public static function cmb2_get_source_fields_options( $cmb ) {
		$source_fields = get_post_meta( $cmb->object_id(), 'source_fields', true );
		$options       = [];
		if ( $source_fields ) {
			foreach ( $source_fields as $field ) {
				$options[ $field['field_label'] ] = $field['field_display_label'];
			}
		}

		return $options;
	}

	/**
	 * Get the marker icons options.
	 *
	 * @return array The marker icons options.
	 */
	public static function cmb2_marker_icons_options() {
		$icons   = self::get_icons();
		$options = [];
		if ( $icons ) {
			foreach ( $icons as $icon ) {
				$options[ $icon ] = $icon;
			}
		}

		return $options;
	}

	/**
	 * Get the datalayers from the datalayer post type.
	 *
	 * @param array $query_args The query arguments.
	 *
	 * @return array
	 */
	public static function cmb2_dropdown_datalayers( $query_args ) {

		$args = wp_parse_args(
			$query_args,
			[
				'post_type'   => 'owc_ok_datalayer',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			]
		);

		$posts = get_posts( $args );

		$post_options = [];
		if ( $posts ) {
			foreach ( $posts as $post ) {
				$post_options[ $post->ID ] = $post->post_title;
			}
		}

		return $post_options;
	}
}
