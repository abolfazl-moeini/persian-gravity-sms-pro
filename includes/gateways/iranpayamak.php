<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Class Name : GFHANNANSMS_Pro_{strtoupper( php file name) }
 */
class GFHANNANSMS_Pro_IRANPAYAMAK {

	const API_BASE = 'https://api.iranpayamak.com';

	public static function name( $gateways ) {
		$name    = __( 'ایران پیامک', 'GF_SMS' );
		$gateway = array( strtolower( str_replace( 'GFHANNANSMS_Pro_', '', get_called_class() ) ) => $name );

		return array_unique( array_merge( $gateways, $gateway ) );
	}

	public static function options() {
		return array(
			'api_key' => __( 'کلید API', 'GF_SMS' ),
		);
	}

	public static function credit() {
		return true;
	}

	public static function process( $options, $action, $from, $to, $messages ) {
		if ( $action == 'credit' && ! self::credit() ) {
			return false;
		}

		$api_key = isset( $options['api_key'] ) ? trim( $options['api_key'] ) : '';
		if ( empty( $api_key ) ) {
			return __( 'کلید API ایران پیامک وارد نشده است.', 'GF_SMS' );
		}

		if ( $action == 'send' ) {
			return self::send_sms( $api_key, $from, $to, $messages );
		}

		if ( $action == 'credit' ) {
			return self::get_credit( $api_key );
		}

		if ( $action == 'range' ) {
			return array(
				'min' => 100,
				'max' => 200,
			);
		}

		return __( 'عملیات نامعتبر است.', 'GF_SMS' );
	}

	private static function send_sms( $api_key, $from, $to, $messages ) {
		if ( empty( $from ) ) {
			return __( 'شماره فرستنده معتبر نیست.', 'GF_SMS' );
		}

		$recipients = self::normalize_recipients( $to );
		if ( empty( $recipients ) ) {
			return __( 'شماره گیرنده معتبر نیست.', 'GF_SMS' );
		}

		$response = self::api_request(
			'POST',
			'/ws/v1/sms/simple',
			array(
				'text'          => $messages,
				'line_number'   => $from,
				'recipients'    => $recipients,
				'number_format' => 'english',
			),
			$api_key
		);

		if ( is_string( $response ) ) {
			return $response;
		}

		if ( isset( $response['status'] ) && $response['status'] === 'success' ) {
			return 'OK';
		}

		return self::format_error_message( $response );
	}

	private static function get_credit( $api_key ) {
		$response = self::api_request( 'GET', '/ws/v1/account/balance', array(), $api_key );

		if ( is_string( $response ) ) {
			return $response;
		}

		if ( isset( $response['status'] ) && $response['status'] === 'success' && isset( $response['data'] ) && is_array( $response['data'] ) ) {
			$balance_count = self::get_response_value( $response['data'], array( 'balance_count', 'balanceCount' ) );

			if ( $balance_count !== null && $balance_count !== '' ) {
				return (int) floor( (float) $balance_count ) . __( ' پیامک', 'GF_SMS' );
			}
		}

		return self::format_error_message( $response );
	}

	private static function api_request( $method, $endpoint, $data, $api_key ) {
		$url = self::API_BASE . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'Api-Key'      => $api_key,
			),
		);

		if ( $method === 'GET' && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
		} elseif ( $method !== 'GET' ) {
			$args['body'] = wp_json_encode( $data );
		}

		$http_response = ( $method === 'GET' )
			? wp_remote_get( $url, $args )
			: wp_remote_request( $url, $args );

		if ( is_wp_error( $http_response ) ) {
			return sprintf(
				__( 'خطا در ارتباط با ایران پیامک: %s', 'GF_SMS' ),
				$http_response->get_error_message()
			);
		}

		$body      = wp_remote_retrieve_body( $http_response );
		$http_code = wp_remote_retrieve_response_code( $http_response );
		$decoded   = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return sprintf(
				__( 'پاسخ نامعتبر از ایران پیامک (HTTP %d).', 'GF_SMS' ),
				$http_code
			);
		}

		if ( $http_code >= 400 || ( isset( $decoded['status'] ) && $decoded['status'] === 'error' ) ) {
			return self::format_error_message( $decoded, $http_code );
		}

		return $decoded;
	}

	private static function get_response_value( $data, $keys ) {
		foreach ( (array) $keys as $key ) {
			if ( isset( $data[ $key ] ) && $data[ $key ] !== '' ) {
				return $data[ $key ];
			}
		}

		return null;
	}

	private static function format_error_message( $response, $http_code = 0 ) {
		$message = '';

		if ( isset( $response['message'] ) ) {
			$message = $response['message'];
		} elseif ( isset( $response['messages'] ) ) {
			$message = $response['messages'];
		}

		if ( is_array( $message ) ) {
			$parts = array();
			foreach ( $message as $value ) {
				$parts[] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			}
			$message = implode( ', ', $parts );
		}

		if ( $message === '' || $message === null ) {
			$message = __( 'خطای ناشناخته.', 'GF_SMS' );
		}

		if ( $http_code ) {
			return sprintf( __( 'خطای ایران پیامک (HTTP %1$d): %2$s', 'GF_SMS' ), $http_code, $message );
		}

		return sprintf( __( 'خطای ایران پیامک: %s', 'GF_SMS' ), $message );
	}

	private static function normalize_recipients( $to ) {
		$numbers = array();

		foreach ( explode( ',', $to ) as $number ) {
			$number = trim( $number );
			if ( $number === '' ) {
				continue;
			}

			$normalized = self::normalize_iranian_mobile( $number );
			if ( ! empty( $normalized ) ) {
				$numbers[] = $normalized;
			}
		}

		return array_values( array_unique( $numbers ) );
	}

	private static function normalize_iranian_mobile( $number ) {
		$ret = trim( $number );

		if ( substr( $ret, 0, 5 ) == '%2B98' || substr( $ret, 0, 5 ) == '%2b98' ) {
			$ret = substr( $ret, 5 );
		}
		if ( substr( $ret, 0, 4 ) == '0098' ) {
			$ret = substr( $ret, 4 );
		}
		if ( substr( $ret, 0, 3 ) == '098' ) {
			$ret = substr( $ret, 3 );
		}
		if ( substr( $ret, 0, 3 ) == '+98' ) {
			$ret = substr( $ret, 3 );
		}
		if ( substr( $ret, 0, 2 ) == '98' ) {
			$ret = substr( $ret, 2 );
		}

		$ret = preg_replace( '/\D+/', '', $ret );

		if ( empty( $ret ) ) {
			return '';
		}

		if ( substr( $ret, 0, 1 ) !== '0' ) {
			$ret = '0' . $ret;
		}

		return $ret;
	}
}
