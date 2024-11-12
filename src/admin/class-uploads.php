<?php
/**
 * Filters that assist in uploading additional filetypes.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Remon Pel <remon@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Admin;

/**
 * Central spot to manage the uploads.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 */
class Uploads {

	/**
	 * The singleton instance of this class.
	 *
	 * @access private
	 * @var    Uploads|null $instance The singleton instance of this class.
	 */
	private static $instance = null;

	/**
	 * Initialize the class and set its properties.
	 */
	private function __construct() {
		add_filter( 'upload_mimes', [ 'Openkaarten_Base_Plugin\Admin\Uploads', 'filter_upload_mimes' ] );
		add_filter( 'site_option_upload_filetypes', [ 'Openkaarten_Base_Plugin\Admin\Uploads', 'filter_site_option_upload_filetypes' ] );
		add_filter( 'wp_check_filetype_and_ext', [ 'Openkaarten_Base_Plugin\Admin\Uploads', 'determine_proper_filetype_and_ext' ], 10, 5 );
	}

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return Uploads The singleton instance of this class.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Uploads();
		}

		return self::$instance;
	}

	/**
	 * Array of allowed mime types keyed by the file extension.
	 * The first in the list is used when any of the mimes are found for that extension.
	 *
	 * @var array[]
	 */
	public static $mimes = [
		'geojson' => [ 'application/json', 'text/json', 'text/plain' ],
		'json'    => [ 'application/json', 'text/json' ],
		'kml'     => [ 'application/vnd.google-earth.kml+xml', 'application/xml', 'text/xml' ],
		'gml'     => [ 'application/gml+xml', 'application/xml', 'text/xml' ],
	];

	/**
	 * Filter: upload_mimes; add additional filetypes to the allowed upload filetypes.
	 *
	 * @param string[] $mimes Array of allowed mime types keyed by the file extension.
	 *
	 * @return array
	 */
	public static function filter_upload_mimes( array $mimes ): array {
		$additional_mimes = self::$mimes;

		// Reduce the array to only the first mime type.
		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- This is a valid use case.
		array_walk( $additional_mimes, fn( &$mime ) => $mime = reset( $mime ) );

		// Merge the additional mimes with the existing mimes.
		$mimes = array_merge( $mimes, $additional_mimes );

		return $mimes;
	}

	/**
	 * Filter: site_option_upload_filetypes; add additional filetypes to the allowed upload filetypes.
	 *
	 * @param string $option_value Space separated list of allowed filetypes (extensions).
	 *
	 * @return string
	 */
	public static function filter_site_option_upload_filetypes( string $option_value ): string {
		$option_value = explode( ' ', $option_value );
		$option_value = array_merge( $option_value, array_keys( self::$mimes ) );
		$option_value = array_unique( $option_value );
		$option_value = implode( ' ', $option_value );

		return $option_value;
	}

	/**
	 * Filter: wp_check_filetype_and_ext; determine the proper filetype based on extension and a list of allowed mime types.
	 * WordPress only allows one filetype per extension, this filter implementation allows us to support multiple filetypes per extension.
	 *
	 * @param array    $file_data An array of data for a single file, as determined by WordPress during upload.
	 * @param string   $file      Unused in this implementation. The path to the uploaded file.
	 * @param string   $filename  The name of the uploaded file.
	 * @param string[] $mimes     Unused in this implementation.
	 * @param string   $real_mime The mime-type as determined by PHP's Fileinfo extension.
	 *
	 * @return mixed
	 */
	public static function determine_proper_filetype_and_ext( $file_data, $file, $filename, $mimes, $real_mime ) {
		$file_ext = pathinfo( $filename, PATHINFO_EXTENSION );
		foreach ( self::$mimes as $ext => $mime ) {
			if ( $ext === $file_ext && in_array( $real_mime, self::$mimes[ $ext ], true ) ) {
				$file_data['ext']  = $ext;
				$file_data['type'] = reset( self::$mimes[ $ext ] );
			}
		}

		return $file_data;
	}
}
