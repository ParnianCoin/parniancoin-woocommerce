<?php
/**
 * Plugin Name:       Parnian Pay — ParnianCoin for WooCommerce
 * Plugin URI:        https://pay.parniancoin.com/docs
 * Description:       Accept ParnianCoin (PARC) payments. Non-custodial: buyers pay straight from their wallet into your own PARC account.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            ParnianCoin
 * Author URI:        https://parniancoin.com
 * License:           GPL-2.0-or-later
 * Text Domain:       parnian-pay
 * Domain Path:       /languages
 * WC requires at least: 7.6
 * WC tested up to:   10.2
 * Requires Plugins:  woocommerce
 *
 * @package ParnianPay
 */

defined( 'ABSPATH' ) || exit;

define( 'PARNIAN_PAY_VERSION', '1.1.0' );
define( 'PARNIAN_PAY_FILE', __FILE__ );
define( 'PARNIAN_PAY_URL', plugin_dir_url( __FILE__ ) );
define( 'PARNIAN_PAY_DIR', plugin_dir_path( __FILE__ ) );

// Compatible with High-Performance Order Storage and the Cart/Checkout blocks.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PARNIAN_PAY_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PARNIAN_PAY_FILE, true );
		}
	}
);

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'parnian-pay', false, dirname( plugin_basename( PARNIAN_PAY_FILE ) ) . '/languages' );
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>' . esc_html__( 'Parnian Pay requires WooCommerce to be installed and active.', 'parnian-pay' ) . '</p></div>';
				}
			);
			return;
		}

		require_once PARNIAN_PAY_DIR . 'includes/class-parnian-pay-api.php';
		require_once PARNIAN_PAY_DIR . 'includes/class-wc-gateway-parnian-pay.php';
		require_once PARNIAN_PAY_DIR . 'includes/admin.php';

		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = 'WC_Gateway_Parnian_Pay';
				return $gateways;
			}
		);
	},
	11
);

// Checkout block support.
add_action(
	'woocommerce_blocks_loaded',
	function () {
		if ( ! class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
			return;
		}
		require_once PARNIAN_PAY_DIR . 'includes/class-parnian-pay-blocks.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
				$registry->register( new Parnian_Pay_Blocks() );
			}
		);
	}
);

// "Settings" link on the Plugins screen.
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=parnian_pay' ) ) . '">' . esc_html__( 'Settings', 'parnian-pay' ) . '</a>' );
		return $links;
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		delete_transient( 'parnian_pay_status' );
	}
);
