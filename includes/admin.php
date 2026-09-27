<?php
/**
 * Order screen: invoice details + "Check ParnianCoin payment" order action.
 *
 * @package ParnianPay
 */

defined( 'ABSPATH' ) || exit;

function parnian_pay_gateway() {
	$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
	return isset( $gateways['parnian_pay'] ) ? $gateways['parnian_pay'] : null;
}

add_action(
	'woocommerce_admin_order_data_after_billing_address',
	function ( $order ) {
		$inv = $order->get_meta( WC_Gateway_Parnian_Pay::META_INVOICE );
		if ( ! $inv ) {
			return;
		}
		$gw  = parnian_pay_gateway();
		$url = ( $gw ? $gw->gateway_url : 'https://pay.parniancoin.com' ) . '/pay/' . rawurlencode( $inv );
		echo '<p><strong>' . esc_html__( 'ParnianCoin invoice', 'parnian-pay' ) . ':</strong><br>';
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $inv ) . '</a><br>';
		echo esc_html( $order->get_meta( WC_Gateway_Parnian_Pay::META_AMOUNT ) ) . ' PARC';
		$rate = $order->get_meta( WC_Gateway_Parnian_Pay::META_RATE );
		if ( $rate ) {
			/* translators: 1: rate, 2: currency */
			echo '<br><small>' . esc_html( sprintf( __( 'Rate: 1 PARC = %1$s %2$s', 'parnian-pay' ), $rate, $order->get_currency() ) ) . '</small>';
		}
		echo '</p>';
	}
);

add_filter(
	'woocommerce_order_actions',
	function ( $actions, $order = null ) {
		if ( $order instanceof WC_Order && $order->get_meta( WC_Gateway_Parnian_Pay::META_INVOICE ) ) {
			$actions['parnian_pay_check'] = __( 'Check ParnianCoin payment status', 'parnian-pay' );
		}
		return $actions;
	},
	10,
	2
);

add_action(
	'woocommerce_order_action_parnian_pay_check',
	function ( $order ) {
		$gw = parnian_pay_gateway();
		if ( ! $gw ) {
			return;
		}
		$inv = $gw->api()->get_invoice( $order->get_meta( WC_Gateway_Parnian_Pay::META_INVOICE ) );
		if ( is_wp_error( $inv ) ) {
			/* translators: %s: error message */
			$order->add_order_note( sprintf( __( 'ParnianCoin status check failed: %s', 'parnian-pay' ), $inv->get_error_message() ) );
			return;
		}
		/* translators: 1: status, 2: amount paid */
		$order->add_order_note( sprintf( __( 'ParnianCoin invoice status: %1$s (received %2$s PARC).', 'parnian-pay' ), $inv['status'], $inv['paid'] ) );
		$gw->sync_order( $order, $inv );
	}
);
