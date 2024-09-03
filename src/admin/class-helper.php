<?php
/**
 * Helper class with several functions.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 * @author     Eyal Beker <eyal@acato.nl>
 */

namespace Openkaarten_Base_Plugin\Admin;

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
	 * Convert HEX color to KML color.
	 *
	 * @param string $hex     The HEX color.
	 * @param float  $opacity The opacity.
	 *
	 * @return string The KML color.
	 */
	public static function hex_to_kml_color( $hex, $opacity = 100 ) {

		// Opacity lookup: percentage to hex value.
		$alpha = [
			100 => 'FF',
			99  => 'FC',
			98  => 'FA',
			97  => 'F7',
			96  => 'F5',
			95  => 'F2',
			94  => 'F0',
			93  => 'ED',
			92  => 'EB',
			91  => 'E8',
			90  => 'E6',
			89  => 'E3',
			88  => 'E0',
			87  => 'DE',
			86  => 'DB',
			85  => 'D9',
			84  => 'D6',
			83  => 'D4',
			82  => 'D1',
			81  => 'CF',
			80  => 'CC',
			79  => 'C9',
			78  => 'C7',
			77  => 'C4',
			76  => 'C2',
			75  => 'BF',
			74  => 'BD',
			73  => 'BA',
			72  => 'B8',
			71  => 'B5',
			70  => 'B3',
			69  => 'B0',
			68  => 'AD',
			67  => 'AB',
			66  => 'A8',
			65  => 'A6',
			64  => 'A3',
			63  => 'A1',
			62  => '9E',
			61  => '9C',
			60  => '99',
			59  => '96',
			58  => '94',
			57  => '91',
			56  => '8F',
			55  => '8C',
			54  => '8A',
			53  => '87',
			52  => '85',
			51  => '82',
			50  => '80',
			49  => '7D',
			48  => '7A',
			47  => '78',
			46  => '75',
			45  => '73',
			44  => '70',
			43  => '6E',
			42  => '6B',
			41  => '69',
			40  => '66',
			39  => '63',
			38  => '61',
			37  => '5E',
			36  => '5C',
			35  => '59',
			34  => '57',
			33  => '54',
			32  => '52',
			31  => '4F',
			30  => '4D',
			29  => '4A',
			28  => '47',
			27  => '45',
			26  => '42',
			25  => '40',
			24  => '3D',
			23  => '3B',
			22  => '38',
			21  => '36',
			20  => '33',
			19  => '30',
			18  => '2E',
			17  => '2B',
			16  => '29',
			15  => '26',
			14  => '24',
			13  => '21',
			12  => '1F',
			11  => '1C',
			10  => '1A',
			9   => '17',
			8   => '14',
			7   => '12',
			6   => '0F',
			5   => '0D',
			4   => '0A',
			3   => '08',
			2   => '05',
			1   => '03',
			0   => '00',
		];

		// HEX cleanup.
		$hex = str_replace( '#', '', $hex );

		// Expand shorthand HEX.
		if ( 3 === strlen( $hex ) ) {
			$hex[5] = $hex[2]; // f60##0.
			$hex[4] = $hex[2]; // f60#00.
			$hex[3] = $hex[1]; // f60600.
			$hex[2] = $hex[1]; // f66600.
			$hex[1] = $hex[0]; // ff6600.
		}

		// Opacity cleanup.
		if ( $opacity < 1 ) {
			$opacity = round( $opacity * 100 );
		}

		// HEX spitting. KML colors go 'bgr' instead of 'rgb'.
		$b = substr( $hex, -2 );
		$g = substr( $hex, 2, 2 );
		$r = substr( $hex, 0, 2 );

		return strtolower( $alpha[ $opacity ] . $b . $g . $r );
	}

	/**
	 * Check if a string is a valid timestamp.
	 *
	 * @param string $timestamp The timestamp to check.
	 *
	 * @return bool True if the timestamp is valid, false otherwise.
	 */
	public static function is_valid_timestamp( $timestamp ) {
		return ( (string) (int) $timestamp === $timestamp )
				&& ( $timestamp <= PHP_INT_MAX )
				&& ( $timestamp >= ~PHP_INT_MAX );
	}

	/**
	 * Convert an array to GeoJSON.
	 *
	 * @param array $array The array to convert.
	 *
	 * @return string The GeoJSON.
	 */
	public static function array_to_geojson( $array ) {
		$geojson = [
			'type'     => 'FeatureCollection',
			'features' => [],
		];

		foreach ( $array as $array_item_values ) {
			$array_item_values['geometry'] = [
				'type'        => 'Point',
				'coordinates' => [
					5.6484,
					53.0424,
				],
			];

			$feature = [
				'type' => 'Feature',
			];

			if ( isset( $array_item_values['id'] ) ) {
				$feature['id'] = $array_item_values['id'];
				unset( $array_item_values['id'] );
			}

			if ( isset( $array_item_values['geometry'] ) ) {
				$feature['geometry']      = $array_item_values['geometry'];
				$feature['geometry_name'] = 'geom';
				unset( $array_item_values['geometry'] );
			}

			foreach ( $array_item_values as $key => $value ) {
				$feature['properties'][ $key ] = $value;
			}

			$geojson['features'][] = $feature;
		}

		return json_encode( $geojson );
	}
}
