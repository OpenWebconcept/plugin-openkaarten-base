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
		// Calculate WGS84 coördinates.
		$dx    = ( $x - 155000 ) * pow( 10, - 5 );
		$dy    = ( $y - 463000 ) * pow( 10, - 5 );
		$som_n = ( 3235.65389 * $dy ) + ( - 32.58297 * pow( $dx, 2 ) ) + ( - 0.2475 *
																			pow( $dy, 2 ) ) + ( - 0.84978 * pow( $dx, 2 ) *
																								$dy ) + ( - 0.0655 * pow( $dy, 3 ) ) + ( - 0.01709 *
																																		pow( $dx, 2 ) * pow( $dy, 2 ) ) + ( - 0.00738 *
																																											$dx ) + ( 0.0053 * pow( $dx, 4 ) ) + ( - 0.00039 *
																																																					pow( $dx, 2 ) * pow( $dy, 3 ) ) + ( 0.00033 * pow(
																																																						$dx,
																																																						4
																																																					) * $dy ) + ( - 0.00012 *
										$dx * $dy );
		$som_e = ( 5260.52916 * $dx ) + ( 105.94684 * $dx * $dy ) + ( 2.45656 *
																	$dx * pow( $dy, 2 ) ) + ( - 0.81885 * pow(
																		$dx,
																		3
																	) ) + ( 0.05594 *
									$dx * pow( $dy, 3 ) ) + ( - 0.05607 * pow(
										$dx,
										3
									) * $dy ) + ( 0.01199 *
										$dy ) + ( - 0.00256 * pow( $dx, 3 ) * pow(
											$dy,
											2
										) ) + ( 0.00128 *
									$dx * pow( $dy, 4 ) ) + ( 0.00022 * pow(
										$dy,
										2
									) ) + ( - 0.00022 * pow(
										$dx,
										2
									) ) + ( 0.00026 *
									pow( $dx, 5 ) );

		$latitude  = 52.15517 + ( $som_n / 3600 );
		$longitude = 5.387206 + ( $som_e / 3600 );

		return [ $longitude, $latitude ];
	}

	/**
	 * Convert WGS84 coordinates to RD.
	 *
	 * @param float $lat The latitude coordinate.
	 * @param float $lon The longitude coordinate.
	 *
	 * @return array The RD coordinates.
	 */
	private static function wgs2rd( $lat, $lon ) {
		// Fixed constants / coefficients.
		$x0      = 155000;
		$y0      = 463000;
		$k       = 0.9999079;
		$bigr    = 6382644.571;
		$m       = 0.003773954;
		$n       = 1.000475857;
		$lambda0 = 0.094032038;
		$phi0    = 0.910296727;
		$l0      = 0.094032038;
		$b0      = 0.909684757;
		$e       = 0.081696831;
		$a       = 6377397.155;

		// wgs84 to bessel.
		$dphi = $lat - 52;
		$dlam = $lon - 5;

		$phicor = ( -96.862 - $dphi * 11.714 - $dlam * 0.125 ) * 0.00001;
		$lamcor = ( $dphi * 0.329 - 37.902 - $dlam * 14.667 ) * 0.00001;

		$phibes = $lat - $phicor;
		$lambes = $lon - $lamcor;

		// bessel to rd.
		$phi    = $phibes / 180 * pi();
		$lambda = $lambes / 180 * pi();
		$qprime = log( tan( $phi / 2 + pi() / 4 ) );
		$dq     = $e / 2 * log( ( $e * sin( $phi ) + 1 ) / ( 1 - $e * sin( $phi ) ) );
		$q      = $qprime - $dq;

		$w  = $n * $q + $m;
		$b  = atan( exp( $w ) ) * 2 - pi() / 2;
		$dl = $n * ( $lambda - $lambda0 );

		$d_1 = sin( ( $b - $b0 ) / 2 );
		$d_2 = sin( $dl / 2 );

		$s2psihalf = $d_1 * $d_1 + $d_2 * $d_2 * cos( $b ) * cos( $b0 );
		$cpsihalf  = sqrt( 1 - $s2psihalf );
		$spsihalf  = sqrt( $s2psihalf );
		$tpsihalf  = $spsihalf / $cpsihalf;

		$spsi = $spsihalf * 2 * $cpsihalf;
		$cpsi = 1 - $s2psihalf * 2;

		$sa = sin( $dl ) * cos( $b ) / $spsi;
		$ca = ( sin( $b ) - sin( $b0 ) * $cpsi ) / ( cos( $b0 ) * $spsi );

		$r = $k * 2 * $bigr * $tpsihalf;
		$x = $r * $sa + $x0;
		$y = $r * $ca + $y0;

		return [ $x, $y ];
	}
}
