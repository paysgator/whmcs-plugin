<?php

/**
 * Paysgator WHMCS Callback File
 *
 * This file handles webhooks from Paysgator to confirm payment.
 * It verifies the HMAC signature and processes payment.success events.
 */

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = 'paysgator';

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

// Get raw POST body
$rawPayload = file_get_contents('php://input');
$webhookData = json_decode($rawPayload, true);

// Verify signature if webhook secret is configured
$signature = isset($_SERVER['HTTP_X_PAYSGATOR_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSGATOR_SIGNATURE'] : '';

// Verify webhook signature if webhook secret is configured
if (!empty($gatewayParams['webhookSecret'])) {
    $expectedSignature = hash_hmac('sha256', $rawPayload, $gatewayParams['webhookSecret']);
    if (!hash_equals($expectedSignature, $signature)) {
        logTransaction($gatewayParams['name'], ['error' => 'Invalid signature'], 'Signature Verification Failed');
        http_response_code(401);
        die('Invalid signature');
    }
}

// Validate webhook structure
if (!isset($webhookData['event']) || !isset($webhookData['data'])) {
    logTransaction($gatewayParams['name'], $webhookData, 'Invalid webhook structure');
    http_response_code(400);
    die('Invalid webhook structure');
}

$event = $webhookData['event'];
$data = $webhookData['data'];

// Only process payment.success events
if ($event !== 'payment.success') {
    logTransaction($gatewayParams['name'], $webhookData, 'Event ignored: ' . $event);
    http_response_code(200);
    die('OK - Event ignored');
}

// Extract payment data
$transactionId = isset($data['transactionId']) ? $data['transactionId'] : null;
$amount = isset($data['amount']) ? $data['amount'] : 0;
$currency = isset($data['currency']) ? $data['currency'] : '';
$status = isset($data['status']) ? $data['status'] : '';
$externalTransactionId = isset($data['externalTransactionId']) ? $data['externalTransactionId'] : null;

// Extract invoice ID from externalTransactionId (format: inv-{invoiceId})
if (!$externalTransactionId) {
    logTransaction($gatewayParams['name'], $webhookData, 'No externalTransactionId found');
    http_response_code(400);
    die('No externalTransactionId');
}

// Parse invoice ID from externalTransactionId using robust regex pattern
// Format: {invoiceId}inv{timestamp} - extract numeric invoice ID before 'inv'
if (!preg_match('/^(\d+)inv/', $externalTransactionId, $matches)) {
    logTransaction($gatewayParams['name'], $webhookData, 'Invalid externalTransactionId format');
    http_response_code(400);
    die('Invalid externalTransactionId format');
}
$invoiceId = $matches[1];

if (!$invoiceId) {
    logTransaction($gatewayParams['name'], $webhookData, 'Could not extract invoice ID from externalTransactionId');
    http_response_code(400);
    die('Invalid externalTransactionId format');
}

// Validate invoice ID
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

// Check transaction ID hasn't been processed before
checkCbTransID($transactionId);

// Verify status is SUCCESS
if ($status !== 'SUCCESS') {
    logTransaction($gatewayParams['name'], $webhookData, 'Payment status not SUCCESS: ' . $status);
    http_response_code(200);
    die('OK - Status not SUCCESS');
}

// Validate payment amount against invoice total
$invoiceData = select_query('tblinvoices', ['total', 'currency'], ['id' => $invoiceId]);
if (!$invoiceData) {
    logTransaction($gatewayParams['name'], $webhookData, 'Invoice not found');
    http_response_code(400);
    die('Invoice not found');
}
$invoiceTotal = $invoiceData['total'];
$invoiceCurrency = $invoiceData['currency'];

// Verify amount matches invoice total (allow small floating point differences)
if (abs($amount - $invoiceTotal) > 0.01) {
    logTransaction($gatewayParams['name'], $webhookData, 'Amount mismatch: webhook=' . $amount . ', invoice=' . $invoiceTotal);
    http_response_code(400);
    die('Amount mismatch');
}

// Verify currency matches invoice currency
if ($currency !== $invoiceCurrency) {
    logTransaction($gatewayParams['name'], $webhookData, 'Currency mismatch: webhook=' . $currency . ', invoice=' . $invoiceCurrency);
    http_response_code(400);
    die('Currency mismatch');
}

// Apply payment to invoice
addInvoicePayment(
    $invoiceId,
    $transactionId,
    $amount,
    0, // Fees - not provided in webhook
    $gatewayModuleName
);

logTransaction($gatewayParams['name'], $webhookData, 'Success');

http_response_code(200);
echo 'OK';

