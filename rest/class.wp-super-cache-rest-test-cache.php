<?php

class WP_Super_Cache_Rest_Test_Cache extends WP_REST_Controller {

	/**
	 * Get a collection of items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function callback( $request ) {
		global $cache_path;

		$url = trailingslashit( get_bloginfo( 'url' ) );

		$response = array( 'status' => 'UNKNOWN' );
		$has_errors = false;

		$attempts = array( 'prime', 'first', 'second' );

		$c = 0;
		foreach ( $attempts as $attempt_name ) {
			$attempt = array();
			$page[ $c ] = wp_remote_get( $url, array('timeout' => 60, 'blocking' => true ) );

			if ( ! is_wp_error( $page[ $c ] ) ) {
				$fp = fopen( $cache_path . $c . ".html", "w" );
				fwrite( $fp, $page[ $c ][ 'body' ] );
				fclose( $fp );
			}

			if ( is_wp_error( $page[ $c ] ) ) {
				$has_errors = true;
				$attempt['status'] = false;
				$attempt['errors'] = $this->format_error( $page[ $c ] );

			} elseif ( $page[ $c ]['response']['code'] != 200 ) {
				$has_errors = true;
				$attempt['status'] = false;
				$attempt['errors'] = array( $page[ $c ]['response']['message'] );

			// Don't run this step on prime cache.
			} elseif ( 0 !== $c && 0 === preg_match( '/(Cached page generated by WP-Super-Cache on) (\d*-\d*-\d* \d*:\d*:\d*)/', $page[ $c ]['body'], $matches2 ) ) {
				$has_errors = true;
				$attempt['status'] = false;
				$attempt['errors'] = array( __( 'Timestamps not found', 'wp-super-cache' ) );

			} else {
				$attempt['status'] = true;
			}


			$response[ 'attempts' ][ $attempt_name ] = $attempt;
			$c++;
		}

		if (
			false == $has_errors &&
			preg_match( '/(Cached page generated by WP-Super-Cache on) (\d*-\d*-\d* \d*:\d*:\d*)/', $page[ 1 ][ 'body' ], $matches1 ) &&
			preg_match( '/(Cached page generated by WP-Super-Cache on) (\d*-\d*-\d* \d*:\d*:\d*)/', $page[ 2 ][ 'body' ], $matches2 ) &&
			$matches1[2] == $matches2[2]
		) {
			$response[ 'status' ] = true;
		} else {
			$response[ 'status' ] = false;
			$response[ 'error' ] = array( __( 'Timestamps do not match', 'wp-super-cache' ) );
		}

		$error = '';
		if ( $response[ 'status' ] == false ) {
			if ( isset( $response[ 'error' ] ) ) {
				$error = $response[ 'error' ];
			} else {
				foreach( $response[ 'attempts' ] as $attempt ) {
					$error .= $attempt[ 'errors' ] . "\n";
				}
			}
			return new WP_Error( 'test_error', $error, array( 'status' => 500 ) );
		}
		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_Error $error
	 * @return array
	 */
	protected function format_error( WP_Error $error ) {
		$messages = array();
		foreach ( $error->get_error_codes() as $code ) {
			foreach ( $error->get_error_messages( $code ) as $err ) {
				$messages[] = $err;
			}
		}

		return $messages;
	}
}
