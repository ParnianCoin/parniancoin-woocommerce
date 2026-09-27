# ParnianCoin Payment Gateway for WooCommerce

Accept **ParnianCoin (PARC)** payments in your WooCommerce store. Payments go **directly from the buyer's wallet to your own PARC account**. The gateway never holds your funds.

[فارسی](README.fa.md) · [Website](https://pay.parniancoin.com) · [Releases](../../releases)

---

## Features

- **Non-custodial**: funds are sent straight to the merchant's PARC account; no third party holds your money.
- **Price in your own currency**: products stay priced in your store currency (IRT, IRR, USD and 20+ more). The gateway converts to PARC using the rate you set in your merchant dashboard.
- **Secure hosted payment page**: buyers pay on `pay.parniancoin.com`. Private keys and passwords are never entered on your store and never reach any server.
- **Two ways to pay**: the ParnianCoin web wallet, or manual payment by QR code / account number from any PARC wallet.
- **Server-to-server verification**: an order is marked paid only after the gateway confirms the transaction on-chain, never on the basis of a browser redirect alone.
- **Signed webhooks** (HMAC + timestamp) for instant order status updates.
- **Modern WooCommerce support**: Checkout Blocks and High-Performance Order Storage (HPOS).
- **Multilingual**: English, Persian and Arabic.

## How it works

```
Buyer ──► Your store ──(create invoice, API key)──► pay.parniancoin.com
                                                        │
Buyer ◄──────────── redirected to payment page ◄────────┘
  │
  └──► pays from own wallet ──► ParnianCoin blockchain ──► your PARC account
                                                        │
Your store ◄──── signed webhook + verify ◄──────────────┘  → order: Processing
```

## Requirements

- WordPress 6.0 or later
- WooCommerce 7.0 or later
- PHP 7.4 or later with the `curl` and `json` extensions
- HTTPS on your store
- An **approved** merchant account on [pay.parniancoin.com](https://pay.parniancoin.com)

## 1. Get your merchant account

1. Go to [pay.parniancoin.com](https://pay.parniancoin.com) and sign in with **Google** or **Microsoft**.
2. Enter your store details: store name, **website domain**, and your **PARC account number** (where payments will be received).
3. Set your **conversion rate** for your store currency (for example, how many IRT equal 1 PARC).
4. Wait for administrator approval. Your account cannot take payments until it is approved.
5. After approval, copy your **API Key** and **Webhook Secret** from the dashboard.

> **Note:** Any change to your profile, PARC account or website sends your account back for review. Payments are paused until it is approved again.

## 2. Install the plugin

**From a release (recommended)**

1. Download the latest `parniancoin-gateway.zip` from [Releases](../../releases).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip file and click **Install Now**.
3. Click **Activate**.

**Manually**

Copy the `parniancoin-gateway` folder to `wp-content/plugins/` and activate it from the **Plugins** page.

## 3. Configure

Go to **WooCommerce → Settings → Payments → ParnianCoin** and fill in:

| Setting | Description |
|---|---|
| Enable | Turn the payment method on |
| Title | Name shown to buyers at checkout |
| Description | Short text shown under the title |
| API Key | From your merchant dashboard |
| Webhook Secret | From your merchant dashboard |

Save the settings. The **webhook URL** shown on the settings page must match the one registered in your merchant dashboard.

> The conversion rate is **not** set in the plugin. It is managed per currency in your merchant dashboard, so all your stores and plugins use the same rate.

## Payment page and your domain

For your buyers' safety, the payment page only opens when the buyer arrives from the **website domain approved in your merchant account**. If your store moves to a new domain, update it in the dashboard first (this triggers a new review).

## Order statuses

| Gateway event | WooCommerce order |
|---|---|
| Invoice created | Pending payment |
| Payment confirmed on-chain | Processing |
| Invoice expired / unpaid | Cancelled |

Refunds are handled manually from your own wallet.

## Troubleshooting

- **Payment method does not appear at checkout**: make sure it is enabled, your store currency has a rate in the dashboard, and your merchant account is approved.
- **Payment page shows an access error**: the buyer did not come from your approved domain, or your account is under review.
- **Order stays "Pending payment" after paying**: check that your site is reachable over HTTPS, that the webhook URL and secret match the dashboard, and that no firewall or security plugin blocks incoming requests from the gateway.

## Security

- Never share your API Key or Webhook Secret, and never commit them to a repository.
- Buyers must never be asked for a private key or wallet password on your store.
- To report a vulnerability, please follow [SECURITY.md](SECURITY.md) instead of opening a public issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

Released under the [GNU General Public License v2.0 or later](LICENSE).
