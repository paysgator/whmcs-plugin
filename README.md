<div align="center">
  <img src="https://paysgator.com/paysgator.png" alt="Paysgator" width="300"/>
</div>

# Paysgator WHMCS Payment Gateway


This module allows you to accept payments via Paysgator in WHMCS.

## Requirements

- **PHP**: 7.4 or higher
- **WHMCS**: 7.0 or higher
- **SSL**: Required for production environments (HTTPS webhook URL mandatory)

## Installation

1. Copy the `paysgator-whmcs-payment/modules` folder to your WHMCS root directory. It should merge with your existing `modules` folder.
   
   Final structure should look like:
   ```
   /path/to/whmcs/
   └── modules/
       └── gateways/
           ├── paysgator.php
           ├── paysgator/
           │   └── logo.png
           └── callback/
               └── paysgator.php
   ```

2. Log in to your WHMCS Admin Area.
3. Go to **Configuration > System Settings > Payment Gateways**.
4. Click **Manage Existing Gateways** (or All Payment Gateways).
5. Locate **Paysgator** in the list and click it to activate.
6. Configure the following settings:
   - **API Key**: Required. Get this from your Paysgator Dashboard.
   - **Webhook Secret**: Required. Used to verify webhook signatures for security. Get this from your Paysgator Dashboard.
   - **Test Mode**: Enable for sandbox testing.
7. Click **Save Changes**.

## Configuration

- **API Key**: Required. Your Paysgator API key (Live or Test).
- **Webhook Secret**: Required. Your Paysgator webhook secret for HMAC-SHA256 signature verification. Must be configured to enable security.
- **Test Mode**: Enable for sandbox testing.

## Webhooks

Paysgator will send webhooks to notify WHMCS of payment events.

**Webhook URL**: `https://your-whmcs-domain.com/modules/gateways/callback/paysgator.php`

⚠️ **Important**: Your webhook URL MUST use HTTPS in production. HTTP webhook URLs will be rejected for security.

Configure this URL in your Paysgator Dashboard under Webhooks settings.

### Supported Events
- `payment.success` - Automatically marks invoices as paid
- Other events are logged but not processed

### Security
The module supports HMAC-SHA256 signature verification. To enable:
1. Get your Webhook Secret from Paysgator Dashboard
2. Enter it in the **Webhook Secret** field in WHMCS gateway configuration
3. Uncomment the signature verification code in `callback/paysgator.php`

## Transaction ID Format

The module uses a sanitized `externalTransactionId` format: `inv-{invoiceId}` (max 15 characters, alphanumeric with dash/underscore only).

## Logging

All transactions are logged in **Billing > Gateway Log** for debugging and audit purposes.

## Support

For issues or questions, contact Paysgator support at https://paysgator.com
