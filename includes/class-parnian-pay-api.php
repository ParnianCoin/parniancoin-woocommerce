<?php
/**
 * Minimal client for the Parnian Pay REST API (https://pay.parniancoin.com/docs).
 *
 * @package ParnianPay
 */

defined( 'ABSPATH' ) || exit;

class Parnian_Pay_API {

	/** @var string */
	private $base;
	/** @var string */
	private $key;

	public function __construct( $base_url, $api_key ) {
		$this->base = rtrim( $base_url, '/' );
		$this->key  = trim( (string) $api_key );
	}

	/**
	 * Merchant status for this key (used by the settings page "connection" check).
	 *
	 * @return array|WP_Error
	 */
	public function me() {
		return $this->request( 'GET', '/api/v1/me' );
	}

	/**
	 * @param array  $body            amount, order_id, description, fiat_amount, fiat_currency, return_url, expires_in.
	 * @param string $idempotency_key Same key => same invoice (safe retries).
	 * @return array|WP_Error
	 */
	public function create_invoice( array $body, $idempotency_key ) {
		return $this->request( 'POST', '/api/v1/invoices', $body, array( 'Idempotency-Key' => $idempotency_key ) );
	}

	/** @return array|WP_Error */
	public function get_invoice( $invoice_id ) {
		if ( ! preg_match( '/^inv_[A-Za-z0-9_-]{8,64}$/', (string) $invoice_id ) ) {
			return new WP_Error( 'parnian_bad_id', 'Invalid invoice id' );
		}
		return $this->request( 'GET', '/api/v1/invoices/' . rawurlencode( $invoice_id ) );
	}

	private function request( $method, $path, $body = null, $headers = array() ) {
		if ( '' === $this->key ) {
			return new WP_Error( 'parnian_no_key', 'API key is not configured' );
		}
		$args = array(
			'method'      => $method,
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => array_merge(
				array(
					'Authorization' => 'Bearer ' . $this->key,
					'Accept'        => 'application/json',
					'User-Agent'    => 'ParnianPay-WooCommerce/' . PARNIAN_PAY_VERSION,
				),
				$headers
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$res = wp_remote_request( $this->base . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
			$err = is_array( $json ) && isset( $json['error'] ) ? $json['error'] : array();
			return new WP_Error(
				'parnian_' . ( isset( $err['code'] ) ? sanitize_key( $err['code'] ) : 'http_' . $code ),
				isset( $err['message'] ) ? (string) $err['message'] : 'HTTP ' . $code,
				array( 'status' => $code )
			);
		}
		return $json;
	}

	/**
	 * Verifies a webhook: header "Parnian-Signature: t=<unix>,v1=<hex>",
	 * v1 = HMAC-SHA256(secret, t + "." + raw_body), t within 5 minutes.
	 */
	public static function verify_signature( $raw_body, $header, $secret, $tolerance = 300, $now = null ) {
		if ( '' === (string) $secret || '' === (string) $header ) {
			return false;
		}
		$parts = array();
		foreach ( explode( ',', (string) $header ) as $kv ) {
			$pair = explode( '=', trim( $kv ), 2 );
			if ( 2 === count( $pair ) ) {
				$parts[ $pair[0] ] = $pair[1];
			}
		}
		if ( empty( $parts['t'] ) || empty( $parts['v1'] ) || ! ctype_digit( $parts['t'] ) ) {
			return false;
		}
		$now = null === $now ? time() : (int) $now;
		if ( abs( $now - (int) $parts['t'] ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $parts['t'] . '.' . $raw_body, $secret );
		return hash_equals( $expected, strtolower( $parts['v1'] ) );
	}

	/** "12.5000" -> 125000 TAR */
	public static function tar( $parc ) {
		if ( ! preg_match( '/^(\d+)(?:\.(\d{1,4}))?$/', (string) $parc, $m ) ) {
			return null;
		}
		return (int) $m[1] * 10000 + (int) str_pad( isset( $m[2] ) ? $m[2] : '', 4, '0' );
	}
}
