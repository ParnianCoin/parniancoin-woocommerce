# Parnian Pay for WooCommerce

Accept **ParnianCoin (PARC)** payments in your WooCommerce store. Payments go **directly from the buyer's wallet to your own PARC account**. The gateway never holds your funds or keys.

[فارسی](README.fa.md) · [Website](https://pay.parniancoin.com) · [API docs](https://pay.parniancoin.com/docs) · [Download](../../releases/latest)

---

## Features

- **Non-custodial**: funds go straight to the merchant's PARC account; no third party holds your money.
- **Price in your own currency**: products stay priced in your store currency (IRT, IRR, IRHT, IRHR, USD and more). The gateway converts to PARC using the rate you set in your merchant dashboard.
- **Hosted payment page**: buyers pay on `pay.parniancoin.com` with a QR code or one-click web wallet payment. Private keys and passwords are never entered on your store.
- **Server-side verification**: signed webhooks (HMAC-SHA256), plus a re-check of the invoice when the buyer returns. An order is never marked paid on a browser redirect alone.
- **Partial and late payments** put the order on hold for review.
- **Classic checkout and Checkout block**; HPOS compatible.
- Payment method is **hidden automatically** when the gateway is inactive or the store currency has no rate.
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

- WordPress 6.2 or later
- WooCommerce 7.6 – 10.x
- PHP 7.4 or later with the `curl` and `json` extensions
- HTTPS on your store (`http://localhost` is allowed for testing)
- An **approved** merchant account on [pay.parniancoin.com](https://pay.parniancoin.com)

## 1. Get your merchant account

1. Go to [pay.parniancoin.com](https://pay.parniancoin.com) and sign in with **Google** or **Microsoft**.
2. Enter your store details: store name, **website domain**, and your **PARC account number** (where payments will be received).
3. Add a **conversion rate** for your store currency (for example, how many IRT equal 1 PARC).
4. Wait for administrator approval. Your account cannot take payments until it is approved.
5. After approval, copy your **API key** and **Webhook signing secret** from the dashboard.

> **Note:** Any change to your profile, PARC account or website sends your account back for review. Payments are paused until it is approved again.

## 2. Install the plugin

1. Download `parnian-pay-woocommerce-<version>.zip` from the [latest release](../../releases/latest).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the zip file and click **Install Now**.
3. Click **Activate**.

> ⚠️ Do **not** use GitHub's green **Code → Download ZIP** button. Always install the zip from Releases.

## 3. Configure

Go to **WooCommerce → Settings → Payments → Parnian Pay** and fill in:

| Setting | Description |
|---|---|
| Enable | Turn the payment method on |
| Title | Name shown to buyers at checkout |
| Description | Short text shown under the title |
| API key | From your merchant dashboard |
| Webhook signing secret | From your merchant dashboard |

Save the settings. The page shows a **connection report**.

Then copy the **Webhook URL** shown on the settings page into your Parnian Pay dashboard.

> The conversion rate is **not** set in the plugin. It is managed per currency in your merchant dashboard, so all your stores and plugins use the same rate.

## Payment page and your domain

For your buyers' safety, the payment page only opens when the buyer arrives from the **website domain approved in your merchant account**. If your store moves to a new domain, update it in the dashboard first (this triggers a new review).

## Order statuses

| Gateway event | WooCommerce order |
|---|---|
| Invoice created | Pending payment |
| Payment confirmed on-chain | Processing |
| Partial or late payment | On hold (needs review) |
| Invoice expired / unpaid | Cancelled |

Refunds are handled manually from your own wallet.

## Troubleshooting

- **Payment method does not appear at checkout**: make sure it is enabled, your store currency has a rate in the dashboard, and your merchant account is approved.
- **Payment page shows an access error**: the buyer did not come from your approved domain, or your account is under review.
- **Order stays "Pending payment" after paying**: check that your site is reachable over HTTPS, that the webhook URL and secret match the dashboard, and that no firewall or security plugin blocks incoming requests from the gateway.

## Security

- Never share your API key or webhook signing secret, and never commit them to a repository.
- Buyers must never be asked for a private key or wallet password on your store.
- To report a vulnerability, see [SECURITY.md](SECURITY.md). Please do not open a public issue.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

Released under the [GNU General Public License v2.0 or later](LICENSE).
