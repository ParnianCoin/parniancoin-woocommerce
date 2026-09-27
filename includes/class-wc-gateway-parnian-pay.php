<?php
/**
 * WooCommerce payment gateway: ParnianCoin (PARC) via Parnian Pay.
 *
 * Flow: checkout -> invoice created server-to-server -> buyer redirected to the hosted
 * payment page -> payment confirmed by a signed webhook (or by re-checking the invoice
 * when the buyer returns). The buyer's return alone never marks an order paid.
 *
 * @package ParnianPay
 */

defined( 'ABSPATH' ) || exit;

class WC_Gateway_Parnian_Pay extends WC_Payment_Gateway {

	const META_INVOICE = '_parnian_invoice_id';
	const META_AMOUNT  = '_parnian_amount';
	const META_URL     = '_parnian_pay_url';
	const META_RATE    = '_parnian_rate';
	const META_FIAT    = '_parnian_fiat';
	const META_SEEN    = '_parnian_seen';

	/** @var string */
	public $api_key;
	/** @var string */
	public $webhook_secret;
	/** @var int */
	public $expires;
	/** @var string */
	public $gateway_url;
	/** @var bool */
	public $debug;

	public function __construct() {
		$this->id                 = 'parnian_pay';
		$this->icon               = PARNIAN_PAY_URL . 'assets/icon.svg';
		$this->has_fields         = false;
		$this->method_title       = __( 'ParnianCoin (Parnian Pay)', 'parnian-pay' );
		$this->method_description = __( 'Accept ParnianCoin (PARC). Payments go directly from the buyer’s wallet to your own PARC account.', 'parnian-pay' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->api_key        = trim( (string) $this->get_option( 'api_key' ) );
		$this->webhook_secret = trim( (string) $this->get_option( 'webhook_secret' ) );
		$this->expires        = max( 5, min( 1440, (int) $this->get_option( 'expires', 30 ) ) );
		$this->gateway_url    = rtrim( (string) $this->get_option( 'gateway_url', 'https://pay.parniancoin.com' ), '/' );
		$this->debug          = 'yes' === $this->get_option( 'debug' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'check_connection' ), 20 );
		add_action( 'woocommerce_api_parnian_pay', array( $this, 'handle_webhook' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
	}

	/** Webhook endpoint to paste into the Parnian Pay dashboard. */
	public static function webhook_url() {
		return add_query_arg( 'wc-api', 'parnian_pay', home_url( '/' ) );
	}

	public function init_form_fields() {
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable/Disable', 'parnian-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable ParnianCoin payments', 'parnian-pay' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'   => __( 'Title', 'parnian-pay' ),
				'type'    => 'text',
				'default' => __( 'Pay with ParnianCoin (PARC)', 'parnian-pay' ),
			),
			'description'    => array(
				'title'   => __( 'Description', 'parnian-pay' ),
				'type'    => 'textarea',
				'default' => __( 'You will be taken to the secure Parnian Pay page to pay from your ParnianCoin wallet.', 'parnian-pay' ),
			),
			'connection'     => array(
				'title'       => __( 'Connection', 'parnian-pay' ),
				'type'        => 'title',
				/* translators: %s: webhook URL */
				'description' => sprintf( __( 'Create an API key in your Parnian Pay dashboard and set this Webhook URL there: %s', 'parnian-pay' ), '<code>' . esc_html( self::webhook_url() ) . '</code>' )
					/* translators: %s: store currency code */
					. '<br>' . sprintf( __( 'The PARC price is set in the Parnian Pay dashboard (Conversion rates) — add a rate for %s there.', 'parnian-pay' ), '<code>' . esc_html( $currency ) . '</code>' ),
			),
			'api_key'        => array(
				'title'       => __( 'API key', 'parnian-pay' ),
				'type'        => 'password',
				'description' => __( 'Starts with pk_live_. Kept on your server only.', 'parnian-pay' ),
			),
			'webhook_secret' => array(
				'title'       => __( 'Webhook signing secret', 'parnian-pay' ),
				'type'        => 'password',
				'description' => __( 'Starts with whsec_. Shown in the Webhook section of your Parnian Pay dashboard.', 'parnian-pay' ),
			),
			'expires'        => array(
				'title'             => __( 'Payment window (minutes)', 'parnian-pay' ),
				'type'              => 'number',
				'default'           => 30,
				'custom_attributes' => array( 'min' => 5, 'max' => 1440, 'step' => 1 ),
			),
			'gateway_url'    => array(
				'title'   => __( 'Gateway address', 'parnian-pay' ),
				'type'    => 'text',
				'default' => 'https://pay.parniancoin.com',
			),
			'debug'          => array(
				'title'   => __( 'Debug log', 'parnian-pay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Log requests and webhooks (WooCommerce → Status → Logs, source "parnian-pay")', 'parnian-pay' ),
				'default' => 'no',
			),
		);
	}

	const STATUS_CACHE = 'parnian_pay_status';

	public function is_available() {
		if ( ! parent::is_available() || '' === $this->api_key ) {
			return false;
		}
		// The settings screens always list the gateway; the checkout only offers it when it can be used.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return true;
		}
		$status = $this->remote_status();
		if ( null === $status ) {
			return true; // Gateway unreachable right now: keep the option rather than lose the sale.
		}
		return ! empty( $status['active'] ) && ! empty( $status['rates'][ get_woocommerce_currency() ] );
	}

	/**
	 * Merchant status and rates from the gateway, cached for 10 minutes (2 minutes after a failure).
	 *
	 * @param bool $refresh Ignore the cache.
	 * @return array{active:bool,rates:array}|null Null when the gateway could not be reached.
	 */
	public function remote_status( $refresh = false ) {
		$cached = $refresh ? false : get_transient( self::STATUS_CACHE );
		if ( is_array( $cached ) ) {
			return empty( $cached['error'] ) ? $cached : null;
		}
		$me = $this->api()->me();
		if ( is_wp_error( $me ) ) {
			$this->log( 'status check failed: ' . $me->get_error_message(), 'error' );
			set_transient( self::STATUS_CACHE, array( 'error' => true ), 2 * MINUTE_IN_SECONDS );
			return null;
		}
		$status = array(
			'active' => ! empty( $me['active'] ),
			'rates'  => isset( $me['rates'] ) && is_array( $me['rates'] ) ? $me['rates'] : array(),
			'name'   => isset( $me['name'] ) ? (string) $me['name'] : '',
			'state'  => isset( $me['status'] ) ? (string) $me['status'] : '',
		);
		set_transient( self::STATUS_CACHE, $status, 10 * MINUTE_IN_SECONDS );
		return $status;
	}

	public function api() {
		return new Parnian_Pay_API( $this->gateway_url, $this->api_key );
	}

	public function log( $message, $level = 'info' ) {
		if ( $this->debug || 'error' === $level ) {
			wc_get_logger()->log( $level, $message, array( 'source' => 'parnian-pay' ) );
		}
	}

	/** After saving settings: ask the gateway who we are and whether the gateway is active. */
	public function check_connection() {
		$this->init_settings();
		$gw = new self();
		if ( '' === $gw->api_key ) {
			return;
		}
		delete_transient( self::STATUS_CACHE );
		$me = $gw->api()->me();
		if ( is_wp_error( $me ) ) {
			/* translators: %s: error message */
			WC_Admin_Settings::add_error( sprintf( __( 'Parnian Pay: connection failed — %s', 'parnian-pay' ), $me->get_error_message() ) );
			return;
		}
		$gw->remote_status( true ); // Warm the checkout cache with the fresh answer.
		if ( empty( $me['active'] ) ) {
			/* translators: 1: merchant name, 2: status */
			WC_Admin_Settings::add_error( sprintf( __( 'Parnian Pay: connected as "%1$s", but the gateway is not active yet (status: %2$s). Payments will be refused until an administrator approves it.', 'parnian-pay' ), $me['name'], $me['status'] ) );
		} else {
			/* translators: %s: merchant name */
			WC_Admin_Settings::add_message( sprintf( __( 'Parnian Pay: connected as "%s" — gateway active.', 'parnian-pay' ), $me['name'] ) );
		}
		$currency = get_woocommerce_currency();
		if ( empty( $me['rates'][ $currency ] ) ) {
			/* translators: %s: currency code */
			WC_Admin_Settings::add_error( sprintf( __( 'Parnian Pay: no conversion rate is set for %s in your Parnian Pay dashboard — checkout with ParnianCoin will fail until you add one.', 'parnian-pay' ), $currency ) );
		} else {
			/* translators: 1: price, 2: currency code */
			WC_Admin_Settings::add_message( sprintf( __( 'Parnian Pay: current rate 1 PARC = %1$s %2$s.', 'parnian-pay' ), $me['rates'][ $currency ], $currency ) );
		}
		if ( '' === $gw->webhook_secret ) {
			WC_Admin_Settings::add_error( __( 'Parnian Pay: the webhook signing secret is empty — payments will only be confirmed when buyers return to your site.', 'parnian-pay' ) );
		}
	}

	// ------------------------------------------------------------------ checkout

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$fiat  = wc_format_decimal( $order->get_total(), wc_get_price_decimals() );
		if ( (float) $fiat <= 0 ) {
			wc_add_notice( __( 'This order cannot be paid with ParnianCoin.', 'parnian-pay' ), 'error' );
			return array( 'result' => 'failure' );
		}
		$fiat_key = $fiat . ' ' . $order->get_currency();

		// Reuse a still-open invoice for the same total (buyer went back and pressed "Place order" again).
		$existing = $order->get_meta( self::META_INVOICE );
		if ( $existing && $order->get_meta( self::META_FIAT ) === $fiat_key ) {
			$inv = $this->api()->get_invoice( $existing );
			if ( ! is_wp_error( $inv ) && in_array( $inv['status'], array( 'pending', 'confirming' ), true ) ) {
				return array( 'result' => 'success', 'redirect' => $inv['pay_url'] );
			}
		}

		// The gateway converts the total to PARC with the merchant's own rate for this currency.
		$body = array(
			'order_id'      => (string) $order->get_id(),
			/* translators: 1: order number, 2: site name */
			'description'   => mb_substr( sprintf( __( 'Order #%1$s — %2$s', 'parnian-pay' ), $order->get_order_number(), get_bloginfo( 'name' ) ), 0, 200 ),
			'fiat_amount'   => $fiat,
			'fiat_currency' => $order->get_currency(),
			'return_url'    => $this->get_return_url( $order ),
			'expires_in'    => $this->expires * 60,
		);
		// Same order + same amount + same attempt window => same invoice on retries.
		$idem = 'wc-' . $order->get_id() . '-' . substr( md5( $fiat_key . '|' . $order->get_order_key() . '|' . (string) $existing ), 0, 16 );
		$inv  = $this->api()->create_invoice( $body, $idem );

		if ( is_wp_error( $inv ) ) {
			$this->log( 'create_invoice failed for order ' . $order->get_id() . ': ' . $inv->get_error_code() . ' ' . $inv->get_error_message(), 'error' );
			if ( in_array( $inv->get_error_code(), array( 'parnian_merchant_not_approved', 'parnian_rate_not_set' ), true ) ) {
				delete_transient( self::STATUS_CACHE );
			}
			$msg = in_array( $inv->get_error_code(), array( 'parnian_merchant_not_approved', 'parnian_rate_not_set' ), true )
				? __( 'ParnianCoin payments are temporarily unavailable in this store.', 'parnian-pay' )
				: __( 'Could not start the ParnianCoin payment. Please try again in a moment.', 'parnian-pay' );
			wc_add_notice( $msg, 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( self::META_INVOICE, $inv['id'] );
		$order->update_meta_data( self::META_AMOUNT, $inv['amount'] );
		$order->update_meta_data( self::META_URL, $inv['pay_url'] );
		$order->update_meta_data( self::META_RATE, isset( $inv['rate'] ) ? (string) $inv['rate'] : '' );
		$order->update_meta_data( self::META_FIAT, $fiat_key );
		$order->delete_meta_data( self::META_SEEN );
		/* translators: 1: amount in PARC, 2: invoice id */
		$order->add_order_note( sprintf( __( 'ParnianCoin invoice created: %1$s PARC (%2$s).', 'parnian-pay' ), $inv['amount'], $inv['id'] ) );
		$order->update_status( 'pending' );
		$order->save();
		$this->log( 'invoice ' . $inv['id'] . ' for order ' . $order->get_id() . ' amount ' . $inv['amount'] );

		return array( 'result' => 'success', 'redirect' => $inv['pay_url'] );
	}

	// ------------------------------------------------------------------ confirmation

	/**
	 * Applies the gateway's invoice state to the order. Only an invoice that belongs to
	 * this order (id stored on the order, same order_id) and that covers the expected
	 * amount can complete it.
	 */
	public function sync_order( WC_Order $order, array $inv ) {
		if ( empty( $inv['id'] ) || $order->get_meta( self::META_INVOICE ) !== $inv['id'] ) {
			$this->log( 'invoice ' . ( $inv['id'] ?? '?' ) . ' does not belong to order ' . $order->get_id(), 'error' );
			return;
		}
		if ( (string) ( $inv['order_id'] ?? '' ) !== (string) $order->get_id() ) {
			$this->log( 'order_id mismatch for invoice ' . $inv['id'], 'error' );
			return;
		}
		$expected = Parnian_Pay_API::tar( $order->get_meta( self::META_AMOUNT ) );
		$paid     = isset( $inv['paid_tar'] ) ? (int) $inv['paid_tar'] : 0;
		$link     = $this->gateway_url . '/pay/' . rawurlencode( $inv['id'] );

		switch ( $inv['status'] ) {
			case 'paid':
				if ( null === $expected || $paid < $expected || (int) $inv['amount_tar'] !== $expected ) {
					/* translators: %s: invoice id */
					$order->update_status( 'on-hold', sprintf( __( 'ParnianCoin invoice %s is marked paid but the amount does not match the order. Please check.', 'parnian-pay' ), $inv['id'] ) );
					return;
				}
				if ( $order->needs_payment() ) {
					/* translators: 1: amount, 2: invoice link */
					$order->add_order_note( sprintf( __( 'ParnianCoin payment confirmed: %1$s PARC. %2$s', 'parnian-pay' ), $inv['paid'], $link ) );
					$order->payment_complete( $inv['id'] );
				}
				return;

			case 'confirming':
				if ( ! $order->get_meta( self::META_SEEN ) ) {
					$order->update_meta_data( self::META_SEEN, time() );
					$order->add_order_note( __( 'ParnianCoin payment received — waiting for block confirmation.', 'parnian-pay' ) );
					$order->save();
				}
				return;

			case 'underpaid':
			case 'late':
				if ( $order->needs_payment() && 'on-hold' !== $order->get_status() ) {
					$note = 'late' === $inv['status']
						/* translators: 1: amount received, 2: invoice link */
						? __( 'ParnianCoin payment arrived after the invoice expired (%1$s PARC). Decide manually: complete the order or refund. %2$s', 'parnian-pay' )
						/* translators: 1: amount received, 2: invoice link */
						: __( 'ParnianCoin invoice expired with a partial payment (%1$s PARC). Contact the customer. %2$s', 'parnian-pay' );
					$order->update_status( 'on-hold', sprintf( $note, $inv['paid'], $link ) );
				}
				return;

			case 'expired':
				if ( 'pending' === $order->get_status() ) {
					$order->update_status( 'failed', __( 'ParnianCoin invoice expired without payment.', 'parnian-pay' ) );
				}
				return;
		}
	}

	/** Buyer is back on the order-received page: re-check the invoice with the gateway. */
	public function thankyou_page( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_meta( self::META_INVOICE ) ) {
			return;
		}
		if ( $order->needs_payment() ) {
			$inv = $this->api()->get_invoice( $order->get_meta( self::META_INVOICE ) );
			if ( ! is_wp_error( $inv ) ) {
				$this->sync_order( $order, $inv );
				$order = wc_get_order( $order_id );
			}
		}
		if ( $order->needs_payment() && 'failed' !== $order->get_status() ) {
			echo '<p class="woocommerce-info">' . esc_html__( 'Your ParnianCoin payment is being confirmed on the blockchain. This page will show the order as paid once it is confirmed; you will not be charged twice.', 'parnian-pay' ) . '</p>';
			$url = $order->get_meta( self::META_URL );
			if ( $url ) {
				echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Open the payment page', 'parnian-pay' ) . '</a></p>';
			}
		}
	}

	/** POST ?wc-api=parnian_pay — signed events from Parnian Pay. */
	public function handle_webhook() {
		$raw = (string) file_get_contents( 'php://input' );
		$sig = isset( $_SERVER['HTTP_PARNIAN_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_PARNIAN_SIGNATURE'] ) ) : '';
		list( $code, $text ) = $this->process_webhook( $raw, $sig );
		status_header( $code );
		exit( esc_html( $text ) );
	}

	/**
	 * @return array{0:int,1:string} HTTP status and body.
	 */
	public function process_webhook( $raw, $sig ) {
		if ( ! Parnian_Pay_API::verify_signature( $raw, $sig, $this->webhook_secret ) ) {
			$this->log( 'webhook rejected: bad signature', 'error' );
			return array( 400, 'bad signature' );
		}

		$event = json_decode( $raw, true );
		$data  = is_array( $event ) && isset( $event['data']['invoice'] ) && is_array( $event['data']['invoice'] ) ? $event['data']['invoice'] : null;
		$order = $data && ! empty( $data['order_id'] ) ? wc_get_order( absint( $data['order_id'] ) ) : false;
		$this->log( 'webhook ' . ( $event['type'] ?? '?' ) . ' invoice ' . ( $data['id'] ?? '?' ) );

		if ( ! $order || $order->get_payment_method() !== $this->id || $order->get_meta( self::META_INVOICE ) !== ( $data['id'] ?? '' ) ) {
			// Not ours (or a superseded invoice): acknowledge so it is not retried forever.
			return array( 200, 'ignored' );
		}

		// Trust the gateway's current state, not only the event body.
		$inv = $this->api()->get_invoice( $data['id'] );
		if ( is_wp_error( $inv ) ) {
			$this->log( 'webhook re-check failed: ' . $inv->get_error_message(), 'error' );
			return array( 503, 'retry' ); // Parnian Pay retries later.
		}
		$this->sync_order( $order, $inv );
		return array( 200, 'ok' );
	}
}
