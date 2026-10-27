<?php
/*
 * Class Name : GFHANNANSMS_Pro_{strtoupper( php file name) }	
 */
class GFHANNANSMS_Pro_Kavenegar {

	/*
	* Gateway title	
	*/
	public static function name($gateways) {
	
		$name = __('کاوه نگار', 'GF_SMS' );
		
		$gateway = array( strtolower( str_replace( 'GFHANNANSMS_Pro_', '', get_called_class())) => $name );
		return array_unique( array_merge( $gateways , $gateway ) );
	}
	
	
	/*
	* Gateway parameters
	*/
	public static function options(){
		return array(
			'username'  => __('شناسه API_KEY','GF_SMS'),
		);
	}

	/*
	* Gateway credit
	*/
	public static function credit(){
		return true;
	}
	

	/*
	* Gateway action
	*/	
	public static function process( $options, $action, $from, $to, $messages ){
	
	if ( $action == 'credit' && !self::credit() ) {
			return false;
		}
		$username = $options['username'];
if ($action == "credit") {
$url = "https://api.kavenegar.com/v1/$username/account/info.json";
		if ( extension_loaded( 'curl' ) ) {
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			$credit_response = curl_exec( $ch );
			curl_close( $ch );
		} else {
			$credit_response = @file_get_contents( $url );
		}

		
		$arr = json_decode($credit_response, true);

		if ( false !== $credit_response ) {
			$json_response = json_decode( $credit_response );
			if ( ! empty( $json_response->return->status ) && $json_response->return->status == 200 ) {
				return $arr['entries']['remaincredit'];
			}
		}

		if ( $json_response !== true ) {
			$json_response = $credit_response;
		}

}
		if ($action == "send") {

		$messg= rawurlencode($messages);
		
		$url = "https://api.kavenegar.com/v1/$username/sms/send.json?receptor=$to&sender=$from&message=$messg";
				
		if ( extension_loaded( 'curl' ) ) {
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			$sms_response = curl_exec( $ch );
			curl_close( $ch );
		} else {
			$sms_response = @file_get_contents( $url );
		}
		
			$arr = json_decode($sms_response, true);
			if ( isset( $arr['return']['status'] ) && $arr['return']['status'] == '200' ) {
				return 'OK';
			} else {
				$fault = ! empty( $arr['return']['message'] ) ? $arr['return']['message'] : ( ! empty( $arr['return']['status'] ) ? (string) $arr['return']['status'] : __( 'Unknown Error', 'GF_SMS' ) );
				return $fault;
			}
		
		}
		

	}
}