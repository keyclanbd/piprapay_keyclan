# PipraPay Integration Guide

A comprehensive guide for integrating PipraPay into your website or application.

---

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)
4. [API Integration](#api-integration)
5. [Transaction Statuses](#transaction-statuses)
6. [Payment Flow](#payment-flow)
7. [Integration Flow (Main Website)](#integration-flow-main-website)
8. [Webhook Integration](#webhook-integration)
9. [Payment Session Timer](#payment-session-timer)
10. [Page Redirection](#page-redirection)
11. [Platform Plugins](#platform-plugins)
12. [Error Handling](#error-handling)
13. [Security](#security)

---

## Overview

PipraPay is a self-hosted payment automation platform. It provides a REST API for creating charges, verifying payments, and receiving webhook callbacks. The system supports multiple payment gateways (Stripe, bKash, Nagad, SSLCommerz, etc.) and is fully customizable through themes and plugins.

---

## Prerequisites

- A server running PHP 7.4+ with a MySQL/MariaDB database
- A web server (Apache with `.htaccess` support or Nginx)
- An API key from the PipraPay admin panel
- A PipraPay installation at your domain (e.g., `https://pay.yoursite.com`)

---

## Installation

1. Clone or upload PipraPay to your server
2. Configure database credentials in `pp-config.php`
3. Access the installer at `https://your-domain.com/install/`
4. Log in to the admin panel and configure payment gateways
5. Generate an API key from **System Settings > API**

---

## API Integration

### Base URL

```
https://your-piprapay-domain.com/api/
```

### Authentication

All API requests require an API key passed in the `mh-piprapay-api-key` header:

```
mh-piprapay-api-key: your_api_key_here
```

### Create Charge

**Endpoint:** `POST /api/create-charge`

**Headers:**
```
Content-Type: application/json
mh-piprapay-api-key: your_api_key_here
```

**Request Body:**
```json
{
    "full_name": "John Doe",
    "email_mobile": "john@example.com",
    "amount": 100.00,
    "currency": "BDT",
    "metadata": {
        "order_id": "12345",
        "product": "Premium Plan"
    },
    "redirect_url": "https://yoursite.com/payment-success",
    "cancel_url": "https://yoursite.com/payment-cancel",
    "webhook_url": "https://yoursite.com/webhook",
    "return_type": "POST"
}
```

**Required Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `full_name` | string | Customer full name (max 150 chars) |
| `email_mobile` | string | Customer email or mobile (max 150 chars) |
| `amount` | number | Payment amount (must be > 0) |
| `metadata` | object | Custom metadata (max 10000 chars) |
| `redirect_url` | string | HTTPS URL to redirect after success. HTTP allowed for `localhost`, `127.0.0.1` during local development. |
| `cancel_url` | string | HTTPS URL to redirect after cancellation. HTTP allowed for `localhost`, `127.0.0.1` during local development. |
| `webhook_url` | string | HTTPS URL for IPN callbacks. HTTP allowed for `localhost`, `127.0.0.1` during local development. |
| `return_type` | string | Response type (max 30 chars) |
| `currency` | string | One of: BDT, USD, EUR, GBP, INR, PKR, AED, SAR, CAD, AUD, JPY, CNY |

**Optional Fields:**
| Field | Type | Description |
|-------|------|-------------|
| `c_id` | string | Customer ID (max 100 chars) |
| `product_name` | string | Product name (max 255 chars) |
| `product_description` | string | Product description (max 1000 chars) |
| `product_meta` | mixed | Additional product metadata |

**Success Response (HTTP 200):**
```json
{
    "status": true,
    "pp_id": "a1b2c3d4e5f6...",
    "pp_url": "https://your-piprapay-domain.com/payment/a1b2c3d4e5f6..."
}
```

**Error Responses:**
| HTTP Code | Response |
|-----------|----------|
| 400 | `{"status": false, "message": "Missing required field(s): ..."}` |
| 400 | `{"status": false, "message": "Invalid request data"}` |
| 401 | `{"status": false, "message": "Unauthorized request"}` |
| 429 | `{"status": false, "message": "Too many requests"}` |

---

### Verify Payment

**Endpoint:** `POST /api/verify-payments`

**Headers:**
```
Content-Type: application/json
mh-piprapay-api-key: your_api_key_here
```

**Request Body:**
```json
{
    "pp_id": "a1b2c3d4e5f6..."
}
```

**Success Response (HTTP 200):**
```json
{
    "pp_id": "a1b2c3d4e5f6...",
    "customer_name": "John Doe",
    "customer_email_mobile": "john@example.com",
    "payment_method": "sslcommerz",
    "amount": 100.00,
    "fee": 5.00,
    "refund_amount": 0,
    "total": 105.00,
    "currency": "BDT",
    "metadata": {},
    "sender_number": "01XXXXXXXXX",
    "transaction_id": "TXN123456",
    "status": "completed",
    "date": "2026-08-06 12:00:00"
}
```

**Error Responses:**
| HTTP Code | Response | When |
|-----------|----------|------|
| 400 | `{"status": false, "message": "Invalid Transaction"}` | Status is still `initialize` or transaction not found |
| 400 | `{"status": false, "message": "Missing required fields"}` | `pp_id` is missing or invalid format |
| 401 | `{"status": false, "message": "Unauthorized request"}` | Invalid API key |

---

## Payment Flow

### Step-by-Step

```
1. Your server calls create-charge API
   → Receives pp_id and pp_url

2. Redirect user to pp_url
   → User sees the PipraPay checkout page

3. User selects a payment method and completes payment
   → PipraPay processes the payment via the gateway

4. PipraPay redirects user back to your redirect_url
   → Your page receives the user

5. Your page calls verify-payments API with pp_id
   → Receives final transaction status and details

6. Place or update the order based on status
```

### Important Notes

- **Do NOT call `verify-payments` immediately after `create-charge`** — the transaction status will be `initialize` and the API returns a 400 error
- Wait until the user has completed the payment on the PipraPay checkout page
- Use the webhook for real-time status updates instead of polling

### Handling Order Placement

When the user returns to your `redirect_url`:

| Status from `verify-payments` | Place Order? | Action |
|-------------------------------|-------------|--------|
| `pending` | Yes | Place the order as **"awaiting payment confirmation"** |
| `completed` | Yes | Place/update the order as **"confirmed"** |
| `failed` | No | Do not place the order. Show cancellation message |
| `initialize` | No | Payment not started. Should not happen at this stage |

**Recommended approach:**
1. Place the order immediately when status is `pending` — mark it as awaiting payment confirmation
2. Update the order when webhook/cron confirms `completed` — mark it as confirmed
3. Cancel the order if webhook/cron later shows `failed`

```
Main Website                    PipraPay
    |                              |
    |-- "Buy Now" ---------------->|
    |   (create-charge API)       |
    |<-- pp_url -------------------|
    |                              |
    |-- Redirect user to pp_url -->|
    |                              |
    |   User pays on PipraPay      |
    |                              |
    |<-- Redirect back to          |
    |   redirect_url (pending) ----|
    |                              |
    |-- verify-payments (pp_id) -->|
    |<-- status: pending ----------|
    |                              |
    |-- Place order (pending) ---->|
    |                              |
    |   Gateway confirms payment   |
    |   (webhook / cron)          |
    |                              |
    |-- verify-payments again ---->|
    |<-- status: completed --------|
    |                              |
    |-- Update order to confirmed |
```

---

## Webhook Integration

Set the `webhook_url` when creating a charge. PipraPay will POST transaction updates to this URL.

**Webhook Payload:**
```json
{
    "pp_id": "a1b2c3d4e5f6...",
    "status": "completed",
    "transaction_id": "TXN123456",
    "amount": 100.00,
    "fee": 5.00,
    "currency": "BDT"
}
```

**Webhook Security:** Verify the request originates from PipraPay by checking the `pp_id` against your database.

---

## Payment Session Timer

The PipraPay checkout page includes a payment session timer (10 minutes by default). When the timer expires:

1. The client-side timer stops and redirects the user to the cancel URL or main site
2. The server-side check in `payment/index.php` detects the expired session and sets the transaction status to `failed`
3. A cron-based background job (`payment-session-expiry` module) also expires stale `initialize` transactions

### Configuration

The session timeout is defined in `pp-config.php`:

```php
define('PP_PAYMENT_SESSION_TIMEOUT', 600); // 600 seconds = 10 minutes
```

### Server-Side Expiry Check

When a user visits `/payment/{pp_id}`, the system checks if `created_at + timeout` has passed. If expired:
- Transaction status is set to `failed`
- User is redirected to the main site

### Cron Module

The `payment-session-expiry` module hooks into `pp_cron` and automatically expires stale `initialize` transactions. Ensure the cron job is configured:

```
https://your-domain.com/?cron
```

Set this to run every 10 minutes from your hosting control panel.

---

## Page Redirection

### Cancel

- User clicks the X/close button on the checkout page → `?cancel` is appended to the URL
- `functions.php` sets the transaction status to `failed`
- User is redirected to the `cancel_url` specified in the API request, or to the main site
- Back button in the keypay theme also redirects to `cancel_url`

### Payment Pending

- Transaction status is `pending` when payment is initiated but not yet verified
- The `pending-ui.php` view is shown if the user returns to the payment page
- The auto-verify pending payment module (`pp_cron`) checks for pending payments and verifies them

### Payment Completed

- Transaction status changes to `completed` after successful verification
- The `completed-ui.php` view is shown
- The `pp_transaction_ipn` hook fires, triggering webhooks, SMS, and email notifications
- User is redirected to the `redirect_url` specified in the API request

### Payment Failed

- Transaction status changes to `failed` when:
  - User cancels the payment
  - Payment session expires
  - Gateway verification fails
  - Admin manually marks it as failed
- The `failed-ui.php` view is shown
- User is redirected to the `cancel_url` specified in the API request

### Refund

- Transaction status changes to `refunded` when an admin processes a refund
- The `pp_transaction_ipn` hook fires for refund notifications

---

## Platform Plugins

PipraPay offers official plugins for popular platforms:

| Platform | Plugin | Link |
|----------|--------|------|
| WooCommerce | PipraPay Gateway | [GitHub](https://github.com/PipraPay/woocommerce-piprapay-gateway) |
| WHMCS | PipraPay Gateway | [GitHub](https://github.com/PipraPay/whmcs-piprapay-gateway) |
| WordPress | PipraPay Plugin | [GitHub](https://github.com/PipraPay/WordPress-PipraPay-Gateway) |
| Laravel | PipraPay SDK | [GitHub](https://github.com/PipraPay/laravel-sdk-piprapay-gateway) |
| CodeIgniter 3 | PipraPay SDK | [GitHub](https://github.com/PipraPay/codeigniter3-sdk-piprapay-gateway) |
| PHP (generic) | PipraPay Library | [GitHub](https://github.com/PipraPay/php-library-piprapay-gateway) |
| FOSSBilling | PipraPay Gateway | [GitHub](https://github.com/PipraPay/fossbilling-piprapay-gateway) |
| BoxBilling | PipraPay Gateway | [GitHub](https://github.com/PipraPay/boxbilling-piprapay-gateway) |
| Blesta | PipraPay Integration | [GitHub](https://github.com/PipraPay/blesta-piprapay-gateway) |
| PerfexCRM | PipraPay Module | [GitHub](https://github.com/PipraPay/perfexcrm-piprapay-gateway) |

---

## Error Handling

### Common API Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `Unauthorized request` | Invalid or missing API key | Verify your API key in the admin panel |
| `Missing required field(s)` | One or more required fields are empty | Check all required fields are present |
| `Invalid request data` | Invalid URL, currency, or amount | Ensure all URLs are HTTPS and currency is supported |
| `Too many requests` | Rate limit exceeded | Wait and retry; 30 requests/min for create-charge, 60 for verify-payments |
| `Invalid Transaction` | Transaction not found or still in `initialize` status | Wait for payment to complete before verifying |

### Rate Limits

| Endpoint | Limit |
|----------|-------|
| `create-charge` | 30 requests per minute |
| `verify-payments` | 60 requests per minute |

---

## Security

- Always use HTTPS for all URLs (API keys, redirect URLs, webhook URLs)
- Store your API key securely on the server side — never expose it in client-side code
- Validate the `pp_id` format (32-character hex string) before making API calls
- Use the webhook for server-side payment confirmation rather than relying solely on client-side redirects
- The `pp_id` is a cryptographically secure random string generated using `random_bytes(16)`
- Transaction status transitions are strictly controlled (e.g., `initialize` → `pending`/`completed`/`failed`)

---

## Quick Start Example (PHP)

```php
<?php
// Step 1: Create a charge
$apiKey = 'your_api_key';
$apiUrl = 'https://your-piprapay-domain.com/api/create-charge';

$payload = [
    'full_name' => 'John Doe',
    'email_mobile' => 'john@example.com',
    'amount' => 100.00,
    'currency' => 'BDT',
    'metadata' => ['order_id' => '12345'],
    'redirect_url' => 'https://yoursite.com/success',
    'cancel_url' => 'https://yoursite.com/cancel',
    'webhook_url' => 'https://yoursite.com/webhook',
    'return_type' => 'POST'
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'mh-piprapay-api-key: ' . $apiKey
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

if ($result['status'] === true) {
    // Step 2: Redirect user to PipraPay checkout
    header('Location: ' . $result['pp_url']);
    exit;
} else {
    echo 'Error: ' . $result['message'];
}
?>
```

---

## Support

- **Documentation:** [piprapay.readme.io](https://piprapay.readme.io)
- **Demo:** [https://demo.piprapay.com/admin/login](https://demo.piprapay.com/admin/login)
  - Username: `demo`
  - Password: `123456`
- **Issues:** [https://github.com/PipraPay/PipraPay-Open-Source-Web/issues](https://github.com/PipraPay/PipraPay-Open-Source-Web/issues)
