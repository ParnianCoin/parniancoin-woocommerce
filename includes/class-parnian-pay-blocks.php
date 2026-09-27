<?php
/**
 * Registers the gateway with the WooCommerce Checkout block.
 *
 * @package ParnianPay
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Parnian_Pay_Blocks extends AbstractPaymentMethodType {

	protected $name = 'parnian_pay';

	/** @var WC_Gateway_Parnian_Pay|null */
	private $gateway;

	public function initialize() {
		$this->settings = get_option( 'woocommerce_parnian_pay_settings', array() );
		$gateways       = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$this->gateway  = isset( $gateways['parnian_pay'] ) ? $gateways['parnian_pay'] : null;
	}

	public function is_active() {
		return $this->gateway && $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'parnian-pay-blocks',
			PARNIAN_PAY_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			PARNIAN_PAY_VERSION,
			true
		);
		return array( 'parnian-pay-blocks' );
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->gateway ? $this->gateway->get_title() : __( 'Pay with ParnianCoin (PARC)', 'parnian-pay' ),
			'description' => $this->gateway ? $this->gateway->get_description() : '',
			'icon'        => PARNIAN_PAY_URL . 'assets/icon.svg',
			'supports'    => $this->gateway ? array_values( array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ) ) : array( 'products' ),
		);
	}
}
