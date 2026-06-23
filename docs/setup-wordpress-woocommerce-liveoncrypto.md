# WordPress, WooCommerce, and LiveOnCrypto payment widget setup guide

This guide walks a store owner or developer through setting up a WordPress store, installing WooCommerce, installing the LiveOnCrypto for WooCommerce payment widget plugin, and configuring the webhook that marks paid orders as paid.

## What you will set up

By the end of this guide, you will have:

1. A working WordPress site.
2. WooCommerce installed and configured for basic checkout.
3. The LiveOnCrypto for WooCommerce plugin installed and enabled as a payment method.
4. LiveOnCrypto API keys and widget settings saved in WooCommerce.
5. A LiveOnCrypto webhook pointed at your WordPress site.
6. A way to confirm that webhook delivery is working.

## Prerequisites

Before you begin, make sure you have the following:

- A domain name or local development URL for the WordPress site.
- Web hosting that supports WordPress.
- PHP 8.0 or newer.
- WordPress 6.0 or newer.
- WooCommerce 7.1 or newer.
- Administrator access to the WordPress dashboard.
- A LiveOnCrypto merchant account with access to API keys and webhook settings.
- A plugin ZIP that contains the `liveoncrypto-woocommerce` plugin directory.

For production payments, use HTTPS for your WordPress site, the LiveOnCrypto API URL, and the widget script URL.

## Step 1: Install WordPress

1. Sign in to your hosting control panel.
2. Create a new WordPress site using your host's WordPress installer, or install WordPress manually from wordpress.org.
3. Point your domain to the WordPress site if your host does not do this automatically.
4. Open the WordPress admin dashboard. This is usually available at:

   ```text
   https://your-store.example/wp-admin/
   ```

5. Complete the initial WordPress setup wizard if one appears.
6. Go to **Settings > General** and confirm the following:
   - **Site Title** is correct.
   - **WordPress Address (URL)** uses the expected domain.
   - **Site Address (URL)** uses the expected domain.
   - Production sites use `https://` URLs.
7. Go to **Settings > Permalinks**.
8. Select a permalink structure such as **Post name**.
9. Click **Save Changes**.

Saving permalinks helps ensure the WordPress REST API route used by the LiveOnCrypto webhook is available.

## Step 2: Install WooCommerce

1. In WordPress admin, go to **Plugins > Add New**.
2. Search for **WooCommerce**.
3. Click **Install Now** on the WooCommerce plugin from Automattic.
4. Click **Activate**.
5. Follow the WooCommerce setup wizard.
6. Configure at least the following store basics:
   - Store address.
   - Selling region.
   - Store currency.
   - Product type.
   - Shipping and tax settings if they apply to your store.
7. Add at least one test product:
   1. Go to **Products > Add New**.
   2. Enter a product name.
   3. Enter a regular price greater than zero.
   4. Publish the product.
8. Confirm checkout pages exist by going to **WooCommerce > Status > Tools** and using WooCommerce page creation tools if required.

## Step 3: Install the LiveOnCrypto plugin

1. In WordPress admin, go to **Plugins > Add New**.
2. Click **Upload Plugin**.
3. Choose the LiveOnCrypto for WooCommerce plugin ZIP file.
4. Click **Install Now**.
5. Click **Activate Plugin**.
6. Confirm WooCommerce is active. The LiveOnCrypto plugin depends on WooCommerce payment gateway features.

If you are installing manually instead of using the ZIP uploader, upload the `liveoncrypto-woocommerce` directory to:

```text
/wp-content/plugins/liveoncrypto-woocommerce/
```

Then activate **LiveOnCrypto for WooCommerce** from **Plugins > Installed Plugins**.

## Step 4: Open the payment method settings

1. In WordPress admin, go to **WooCommerce > Settings**.
2. Open the **Payments** tab.
3. Find **LiveOnCrypto** in the payment methods list.
4. Click **Manage**.

You should now be on the LiveOnCrypto gateway settings screen.

## Step 5: Configure the LiveOnCrypto gateway

Fill in the settings carefully:

1. **Enable/Disable**: check **Enable LiveOnCrypto payments** when you are ready for customers to see the option at checkout.
2. **Checkout title**: enter the payment method name shown to customers, for example `Pay with Crypto`.
3. **Checkout description**: enter the short explanation shown at checkout.
4. **Public client key**:
   - Paste your LiveOnCrypto public client key.
   - The key must begin with `pk_live_` or `pk_test_`.
5. **Secret client key**:
   - Paste your LiveOnCrypto secret client key.
   - The key must begin with `sk_live_` or `sk_test_`.
   - Do not share this value publicly.
6. **Pending status behavior**:
   - Choose **On hold** if you want orders held while payment is pending.
   - Choose **Pending payment** if that better matches your store operations.
7. **Button label**: enter the text shown on the checkout submit button when LiveOnCrypto is selected.
8. **Expired payment behavior**: choose whether expired payments should leave the order on hold, mark it failed, or cancel it.
9. Click **Save changes**.

## Step 6: Copy the webhook endpoint URL from WooCommerce

The webhook is what lets LiveOnCrypto notify WooCommerce that a payment was created, detected, confirmed, paid, expired, underpaid, or needs review.

1. Stay on **WooCommerce > Settings > Payments > LiveOnCrypto**.
2. Find **Webhook endpoint URL**.
3. Click inside the readonly URL field to select it.
4. Copy the full URL.

The URL will usually look like this:

```text
https://your-store.example/wp-json/liveoncrypto/v1/webhook
```

Use the exact URL shown in your WordPress admin. Do not guess or manually rewrite it unless you know your WordPress URL configuration requires it.

## Step 7: Create the webhook in LiveOnCrypto

1. Open your LiveOnCrypto merchant dashboard in a new browser tab.
2. Go to the dashboard area for webhooks or developer settings.
3. Create a new webhook endpoint.
4. Paste the webhook endpoint URL copied from WooCommerce.
5. Select the payment events required for WooCommerce order updates. Include payment lifecycle events such as:
   - `payment.created`
   - `payment.detected`
   - `payment.confirming`
   - `payment.paid`
   - `payment.expired`
   - `payment.underpaid`
   - `payment.review`
   - `payment.overpaid`
6. Save the webhook endpoint in LiveOnCrypto.
7. Copy the webhook signing secret generated by LiveOnCrypto.

The signing secret should begin with `whsec_`.

## Step 8: Save the webhook signing secret in WooCommerce

1. Return to **WooCommerce > Settings > Payments > LiveOnCrypto**.
2. Paste the LiveOnCrypto webhook signing secret into **Webhook signing secret**.
3. Confirm the value begins with `whsec_`.
4. Click **Save changes**.

WooCommerce uses this secret to verify that webhook requests were signed by LiveOnCrypto before updating an order.

## Step 9: Test the LiveOnCrypto connection

1. Go to **WooCommerce > LiveOnCrypto** or use the connection test button on the LiveOnCrypto payment settings screen.
2. Click **Test LiveOnCrypto connection**.
3. Confirm the test succeeds.

If the test fails:

- Confirm the Secret client key is correct.
- Confirm your WordPress host can make outbound HTTPS requests.

## Step 10: Place an end-to-end test order

1. Open your storefront in a private browser window or sign out of WordPress.
2. Add a product with a price greater than zero to the cart.
3. Go to checkout.
4. Enter a valid billing email address. LiveOnCrypto payments require a valid billing email.
5. Select **LiveOnCrypto** or the checkout title you configured.
6. Place the order.
7. WooCommerce should redirect to the order payment page.
8. The LiveOnCrypto payment widget should load.
9. Complete the payment using your LiveOnCrypto test or production flow.
10. Wait for the LiveOnCrypto webhook to reach WooCommerce.
11. In WordPress admin, open **WooCommerce > Orders**.
12. Open the test order and confirm the status changes after the payment webhook is received.
13. Review the **LiveOnCrypto Payment** order panel for payment metadata such as payment ID, network, asset, transaction hash, fiat amount, and last webhook event.

## Step 11: Confirm webhook delivery

Use these checks to confirm webhook delivery is working:

1. In WordPress admin, go to **WooCommerce > Settings > Payments > LiveOnCrypto**.
2. Check **Last received webhook**.
3. Go to **WooCommerce > LiveOnCrypto** for diagnostics.
4. Confirm the diagnostics page shows whether a public key, secret key, and webhook secret are configured.
5. Open the test order and review order notes for LiveOnCrypto updates.
6. If debug logging is enabled, review WooCommerce logs for LiveOnCrypto entries.

## Troubleshooting

### The payment method does not appear at checkout

- Confirm WooCommerce is active.
- Confirm the LiveOnCrypto plugin is active.
- Confirm **Enable LiveOnCrypto payments** is checked.
- Confirm the cart contains a product with an order total greater than zero.
- Confirm your checkout page is using a supported WooCommerce checkout flow.

### The widget does not load on the order payment page

- Confirm production widget URLs use HTTPS.
- Check the browser console for blocked scripts or content security policy issues.
- Confirm the order has a LiveOnCrypto payment ID.

### Webhooks are not received

- Confirm the webhook URL in LiveOnCrypto exactly matches the URL shown in WooCommerce.
- Confirm your WordPress site is publicly reachable from the internet.
- Confirm HTTPS is working in production.
- Save **Settings > Permalinks** again in WordPress.
- Confirm security plugins, firewalls, or maintenance mode are not blocking `/wp-json/liveoncrypto/v1/webhook`.
- Confirm LiveOnCrypto is sending the required signature, timestamp, and event headers.

### Webhooks are received but orders are not marked paid

- Confirm the webhook signing secret in WooCommerce begins with `whsec_` and matches the secret in LiveOnCrypto.
- Confirm the webhook event is `payment.paid` or another paid/completed event supported by the plugin.
- Confirm the webhook fiat amount matches the WooCommerce order amount.
- Confirm the webhook fiat currency matches the WooCommerce order currency.
- Confirm the webhook references the same payment ID or merchant order reference stored on the WooCommerce order.

## Production checklist

Before accepting real payments, confirm every item below:

- WordPress, WooCommerce, and the LiveOnCrypto plugin are up to date.
- Your site uses HTTPS.
- WooCommerce currency and store settings are correct.
- LiveOnCrypto uses the hard-coded production environment.
- Production public key begins with `pk_live_`.
- Production secret key begins with `sk_live_`.
- Webhook signing secret begins with `whsec_`.
- The hard-coded Base API URL is `https://app.liveoncrypto.online`.
- The hard-coded widget script URL is `https://app.liveoncrypto.online/widget.js`.
- A successful connection test has been run.
- A real or test checkout has loaded the payment widget successfully.
- A webhook has been received and recorded by WooCommerce.
- A paid payment has updated the WooCommerce order correctly.
- Store staff know how to find LiveOnCrypto payment metadata on an order.
