# LiveOnCrypto WooCommerce tests

This repository includes a standalone PHP test suite for critical LiveOnCrypto payment behavior. The tests run against lightweight WordPress and WooCommerce stubs, so they can be executed from a checkout of this repository without installing WordPress locally.

## Prerequisites

- PHP 8.0 or newer available as `php` on your `PATH`.
- Run commands from the repository root.

## Run locally

Run all standalone tests:

```bash
php tests/unit-tests.php
php tests/integration-tests.php
php tests/test-support-helpers.php
```

Run a PHP syntax check over the plugin and tests:

```bash
find liveoncrypto-woocommerce tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Coverage

Unit tests cover:

- Settings credential validation.
- Webhook HMAC verification.
- Invalid and missing signatures.
- Timestamp replay rejection.
- Payload schema validation.
- Amount normalization.
- Amount and currency mismatch handling.
- Duplicate event key generation.
- Deterministic order reference generation.

Integration tests cover:

- Gateway settings registration.
- Checkout order creation.
- Order-pay widget rendering.
- Secret key redaction from rendered HTML.
- Successful paid webhooks.
- Duplicate webhook idempotency.
- Invalid signature rejection.
- Amount mismatch hold behavior.
- HPOS enabled and disabled scenarios through the same order lookup API surface used by WooCommerce.

## Manual E2E test outline

1. Start a local, staging, or production-like WordPress + WooCommerce environment with this plugin active.
2. Enable LiveOnCrypto in WooCommerce payment settings with a test public key, test secret key, and webhook secret.
3. Add a simple product to the cart with a total greater than zero.
4. Checkout with LiveOnCrypto and a valid billing email address.
5. Confirm the WooCommerce order is created as `pending` or `on-hold` according to the configured pending status.
6. Open the order-pay page and confirm the LiveOnCrypto widget config is displayed and does not contain the secret key.
7. Send a signed `payment.paid` webhook to `/wp-json/liveoncrypto/v1/webhook` using `HMAC-SHA256(timestamp + "." + raw_body, webhook_secret)`.
8. Confirm the order reaches `processing` or `completed` based on the store's normal WooCommerce fulfillment behavior.
9. Send the exact same signed webhook again and confirm the response is HTTP 200 with duplicate handling and no second completion event.
10. Repeat the webhook flow once with WooCommerce HPOS enabled and once with HPOS disabled.
