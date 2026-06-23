# LiveOnCrypto for WooCommerce

LiveOnCrypto for WooCommerce is a WooCommerce payment gateway plugin that lets merchants accept cryptocurrency payments through LiveOnCrypto while keeping WooCommerce as the system of record for checkout, order status, and reconciliation.

## Repository layout

```text
liveoncrypto-woocommerce/      WordPress plugin directory to package or upload
  assets/                      Admin, checkout, Blocks, and screenshot assets
  includes/                    Gateway, API client, webhook, Blocks, admin, and utility classes
  templates/                   Checkout widget and admin diagnostics templates
  liveoncrypto-woocommerce.php Plugin bootstrap and compatibility declarations
  readme.txt                   WordPress.org-style plugin readme and release notes
docs/                          Merchant/developer setup documentation
tests/                         Standalone PHP tests with WordPress/WooCommerce stubs
```

## Requirements

- PHP 8.0 or newer.
- WordPress 6.0 or newer.
- WooCommerce 7.1 or newer.
- A LiveOnCrypto merchant account with API keys and webhook access.
- HTTPS for production stores.

The plugin currently declares compatibility with WooCommerce High-Performance Order Storage (HPOS) and WooCommerce cart/checkout Blocks.

## Plugin behavior at a glance

- Registers the `LiveOnCrypto` WooCommerce payment gateway.
- Accepts merchant-provided public keys beginning with `pk_live_` or `pk_test_`.
- Accepts merchant-provided secret keys beginning with `sk_live_` or `sk_test_` and redacts saved secrets in admin output and logs.
- Uses the hard-coded production LiveOnCrypto API endpoint `https://app.liveoncrypto.online`.
- Uses the hard-coded production widget script `https://app.liveoncrypto.online/widget.js`.
- Creates server-side payment intents after WooCommerce order creation when credentials are configured.
- Redirects shoppers to the WooCommerce order-pay page, where the LiveOnCrypto widget is rendered.
- Verifies webhooks with `x-liveoncrypto-event`, `x-liveoncrypto-timestamp`, and `x-liveoncrypto-signature` headers.
- Rejects missing signatures, invalid signatures, stale timestamps, invalid payloads, and amount/currency mismatches.
- Completes orders only after a valid paid/completed payment event satisfies the WooCommerce order amount and currency.
- Preserves order payment records and order metadata on uninstall; settings are deleted only when the merchant enables the cleanup option before uninstalling.

## Merchant setup documentation

Use the full setup guide for installation, WooCommerce configuration, webhook registration, end-to-end testing, troubleshooting, and production readiness:

- [`docs/setup-wordpress-woocommerce-liveoncrypto.md`](docs/setup-wordpress-woocommerce-liveoncrypto.md)

The plugin readme used for release packaging is maintained here:

- [`liveoncrypto-woocommerce/readme.txt`](liveoncrypto-woocommerce/readme.txt)

## Local development

From the repository root, run PHP syntax checks before committing changes:

```bash
find liveoncrypto-woocommerce tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Run the standalone test suite:

```bash
php tests/unit-tests.php
php tests/integration-tests.php
php tests/test-support-helpers.php
```

The tests use lightweight WordPress/WooCommerce stubs and do not require a local WordPress installation. See [`tests/README.md`](tests/README.md) for coverage details and the manual end-to-end test outline.

## Packaging a plugin ZIP

Package the plugin directory itself, not the repository root. From the repository root:

```bash
rm -f liveoncrypto-woocommerce.zip
zip -r liveoncrypto-woocommerce.zip liveoncrypto-woocommerce -x '*/.DS_Store'
```

The resulting ZIP should contain `liveoncrypto-woocommerce/liveoncrypto-woocommerce.php` at the top level inside the archive.

## Release checklist

Before tagging or shipping a production release:

1. Confirm `liveoncrypto-woocommerce/liveoncrypto-woocommerce.php` and `liveoncrypto-woocommerce/readme.txt` have matching version numbers.
2. Run PHP syntax checks and the standalone test suite.
3. Test current WordPress and WooCommerce versions.
4. Test PHP 8.0, 8.1, 8.2, and 8.3 where possible.
5. Test HPOS enabled and disabled.
6. Test classic checkout and WooCommerce Blocks checkout.
7. Test key rotation for public key, secret key, and webhook signing secret.
8. Test valid, duplicate, missing-signature, invalid-signature, stale-timestamp, amount-mismatch, currency-mismatch, expired, underpaid, review, and overpaid webhook flows.
9. Confirm the order-pay widget loads and never exposes the secret key.
10. Confirm uninstall behavior preserves order records and deletes plugin settings only when explicitly configured.
