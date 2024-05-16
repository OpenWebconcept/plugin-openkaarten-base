<?php
/**
 * Convert geometry to a specific projection.
 *
 * @link              https://www.openwebconcept.nl
 * @package           Openkaarten_Base_Plugin
 */

namespace Openkaarten_Base_Plugin;

use PHPCoord\CoordinateReferenceSystem\Projected;
use PHPCoord\Point\GeographicPoint;
use PHPCoord\Point\ProjectedPoint;
use PHPCoord\UnitOfMeasure\Angle\Degree;
use PHPCoord\UnitOfMeasure\Length\Metre;

/**
 * Helper class to convert a geometry to a specific projection.
 *
 * @package    Openkaarten_Base_Plugin
 * @author     Acato <richardkorthuis@acato.nl>
 */
class Conversion {

	/**
	 * Convert the coordinates of a geometry to a specific projection.
	 *
	 * @param array  $geom       The geometry.
	 * @param string $projection The projection.
	 *
	 * @return array The geometry with converted coordinates.
	 */
	public static function convert_coordinates( $geom, $projection = 'WGS84' ) {
		if ( isset( $geom['geometry'] ) ) {
			$geom['geometry']['coordinates'] = self::maybe_convert_coordinates( $geom['geometry']['coordinates'], $projection );
		} elseif ( isset( $geom['geometries'] ) ) {
			foreach ( $geom['geometries'] as $key => $geometry ) {
				$geom['geometries'][ $key ]['coordinates'] = self::maybe_convert_coordinates( $geometry['coordinates'], $projection );
			}
		}
		return $geom;
	}

	/**
	 * Maybe convert the coordinates.
	 *
	 * @param array  $coordinates The coordinates.
	 * @param string $projection  The projection.
	 *
	 * @return array The converted coordinates.
	 */
	private static function maybe_convert_coordinates( $coordinates, $projection ) {
		if ( is_array( $coordinates[0] ) ) {
			foreach ( $coordinates as $key => $coordinate ) {
				$coordinates[ $key ] = self::maybe_convert_coordinates( $coordinate, $projection );
			}
		} elseif ( 'WGS84' === $projection && ( $coordinates[0] > 90 || $coordinates[0] < -90 || $coordinates[1] > 180 || $coordinates[1] < -180 ) ) {
			// Probably RD coordinates, convert to WGS84.
			$coordinates = self::rd2wgs( $coordinates[0], $coordinates[1] );
		} elseif ( 'RD' === $projection ) {
			$coordinates = self::wgs2rd( $coordinates[0], $coordinates[1] );
		}

		return $coordinates;
	}

	/**
	 * Convert RD coordinates to WGS84.
	 *
	 * @param float $x The x coordinate.
	 * @param float $y The y coordinate.
	 *
	 * @return array The WGS84 coordinates.
	 */
	private static function rd2wgs( $x, $y ) {
		$from   = ProjectedPoint::createFromEastingNorthing(
			Projected::fromSRID( Projected::EPSG_AMERSFOORT_RD_NEW ),
			new Metre( $x ),
			new Metre( $y )
		);
		$to_crs = Projected::fromSRID( Projected::EPSG_WGS_84_UTM_GRID_SYSTEM_NORTHERN_HEMISPHERE );
		$to     = $from->convert( $to_crs )->asGeographicPoint();

		return [ $to->getLatitude()->getValue(), $to->getLongitude()->getValue() ];
	}

	/**
	 * Convert WGS84 coordinates to RD.
	 *
	 * @param float $x The x coordinate.
	 * @param float $y The y coordinate.
	 *
	 * @return array The RD coordinates.
	 */
	private static function wgs2rd( $x, $y ) {
		$from_crs = Projected::fromSRID( Projected::EPSG_WGS_84_UTM_GRID_SYSTEM_NORTHERN_HEMISPHERE )->getBaseCRS();
		$from     = GeographicPoint::create(
			$from_crs,
			new Degree( $y ),
			new Degree( $x )
		);
		$to_crs   = Projected::fromSRID( Projected::EPSG_AMERSFOORT_RD_NEW );
		$to       = $from->convert( $to_crs, true );

		return [ $to->getEasting()->getValue(), $to->getNorthing()->getValue() ];
	}
}
