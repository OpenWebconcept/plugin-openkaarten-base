<?php
/**
 * Fetches roadworks and events from the NDW Melvin GeoJSON API.
 *
 * @package    Openkaarten_Base_Plugin
 * @subpackage Openkaarten_Base_Plugin/Admin
 */

namespace Openkaarten_Base_Plugin\Admin;

/**
 * Fetches roadworks and events from the NDW Melvin GeoJSON API.
 */
class Melvin {

	/**
	 * The Keycloak token endpoint. The keycloak.ndwcloud.nu host from the 2022 Melvin GeoJSON manual no longer serves a valid certificate.
	 *
	 * @var string
	 */
	const TOKEN_URL = 'https://iam.ndw.nu/auth/realms/ndw/protocol/openid-connect/token';

	/**
	 * The Melvin export endpoint.
	 *
	 * @var string
	 */
	const EXPORT_URL = 'https://melvin.ndw.nu/melvinservice/rest/export';

	/**
	 * The Keycloak client ID used for the password grant.
	 *
	 * @var string
	 */
	const CLIENT_ID = 'melvin-frontend-test';

	/**
	 * The number of days ahead to fetch situations for.
	 *
	 * @var int
	 */
	const PERIOD_DAYS = 7;

	/**
	 * The fetched data per datalayer, so an admin page load only calls Melvin once.
	 *
	 * @var array
	 */
	private static $data = [];

	/**
	 * Fetch the situations of a datalayer as a GeoJSON feature collection.
	 *
	 * @param int $datalayer_id The datalayer ID.
	 *
	 * @return string|\WP_Error
	 */
	public static function fetch_datalayer_data( $datalayer_id ) {
		if ( ! isset( self::$data[ $datalayer_id ] ) ) {
			self::$data[ $datalayer_id ] = self::fetch_situations( $datalayer_id );
		}

		return self::$data[ $datalayer_id ];
	}

	/**
	 * Fetch the situations from the Melvin export endpoint.
	 *
	 * @param int $datalayer_id The datalayer ID.
	 *
	 * @return string|\WP_Error
	 */
	private static function fetch_situations( $datalayer_id ) {
		$username = get_post_meta( $datalayer_id, 'melvin_username', true );
		// Re-index, since wp_parse_id_list() keeps the keys of duplicates and json_encode() would turn the gaps into an object.
		$area_ids = array_values( array_filter( wp_parse_id_list( get_post_meta( $datalayer_id, 'melvin_area_ids', true ) ) ) );

		if ( empty( $username ) || empty( $area_ids ) ) {
			return new \WP_Error( 'melvin_settings', __( 'Fill in the Melvin username, password and area IDs.', 'openkaarten-base' ) );
		}

		$token = self::get_access_token( $username, get_post_meta( $datalayer_id, 'melvin_password', true ) );

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			self::EXPORT_URL,
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				],
				'body'    => wp_json_encode(
					[
						'startPeriod'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
						'endPeriod'      => gmdate( 'Y-m-d\TH:i:s\Z', time() + self::PERIOD_DAYS * DAY_IN_SECONDS ),
						'areaIds'        => $area_ids,
						// Only what Melvin shows on its own public map: published, final situations.
						'published'      => true,
						'statuses'       => [ 'FINAL' ],
						'includeDetours' => false,
					]
				),
			]
		);

		if ( 401 === wp_remote_retrieve_response_code( $response ) ) {
			delete_transient( self::get_token_transient_key( $username ) );
		}

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'melvin_export', __( 'Can\'t fetch the situations from Melvin.', 'openkaarten-base' ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data['features'] ) || ! is_array( $data['features'] ) ) {
			return new \WP_Error( 'melvin_export', __( 'Melvin returned an invalid response.', 'openkaarten-base' ) );
		}

		$features = [];
		foreach ( $data['features'] as $feature ) {
			// Each situation also has separate features for its restrictions and detours, which share its ID.
			if ( empty( $feature['geometry'] ) || ! in_array( $feature['properties']['type'] ?? '', [ 'SITUATION', 'EXTERNAL_SITUATION' ], true ) ) {
				continue;
			}

			$feature['properties'] = self::flatten_properties( $feature['properties'] );
			$features[]            = $feature;
		}

		return wp_json_encode(
			[
				'type'     => 'FeatureCollection',
				'features' => $features,
			]
		);
	}

	/**
	 * Get an access token, reusing a cached one while it is valid.
	 *
	 * @param string $username The Melvin username.
	 * @param string $password The Melvin password.
	 *
	 * @return string|\WP_Error
	 */
	private static function get_access_token( $username, $password ) {
		$transient_key = self::get_token_transient_key( $username );
		$token         = get_transient( $transient_key );

		if ( $token ) {
			return $token;
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			[
				'timeout' => 15,
				'body'    => [
					'client_id'  => self::CLIENT_ID,
					'grant_type' => 'password',
					'username'   => $username,
					'password'   => $password,
				],
			]
		);

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['access_token'] ) ) {
			return new \WP_Error( 'melvin_login', __( 'Can\'t log in to Melvin. Check the username and password, and whether NDW has given this account API access.', 'openkaarten-base' ) );
		}

		set_transient( $transient_key, $data['access_token'], max( 60, (int) ( $data['expires_in'] ?? 0 ) - 60 ) );

		return $data['access_token'];
	}

	/**
	 * Get the transient key for the access token of a Melvin account.
	 *
	 * @param string $username The Melvin username.
	 *
	 * @return string
	 */
	private static function get_token_transient_key( $username ) {
		return 'openkaarten_melvin_token_' . md5( $username );
	}

	/**
	 * Flatten the situation properties, so they can be used in the title and field mapping.
	 *
	 * @param array $properties The situation properties.
	 *
	 * @return array
	 */
	private static function flatten_properties( $properties ) {
		$periods = $properties['periods'] ?? [];

		// createdBy and lastChangedBy hold the names of road authority staff, which don't belong on a public map.
		unset( $properties['createdBy'], $properties['lastChangedBy'], $properties['attachments'], $properties['periods'] );

		$flat = [];
		foreach ( $properties as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$flat[ $key . '_' . $sub_key ] = $sub_value;
				}
			} else {
				$flat[ $key ] = $value;
			}
		}

		$start_dates = array_filter( array_column( $periods, 'startDate' ) );
		$end_dates   = array_filter( array_column( $periods, 'endDate' ) );
		$format      = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		// The dates are ISO 8601 in UTC, so they sort as strings. A period without an end date is open-ended.
		$flat['startDate'] = $start_dates ? wp_date( $format, strtotime( min( $start_dates ) ) ) : '';
		$flat['endDate']   = $end_dates && count( $end_dates ) === count( $periods ) ? wp_date( $format, strtotime( max( $end_dates ) ) ) : '';

		return $flat;
	}
}
