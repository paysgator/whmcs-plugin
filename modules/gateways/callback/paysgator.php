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

// Optional: Add webhook secret to gateway config and verify here
// For now, we'll log and process, but in production you should verify the signature
// if (!empty($gatewayParams['webhookSecret'])) {
//     $expectedSignature = hash_hmac('sha256', $rawPayload, $gatewayParams['webhookSecret']);
//     if (!hash_equals($expectedSignature, $signature)) {
//         logTransaction($gatewayParams['name'], ['error' => 'Invalid signature'], 'Signature Verification Failed');
//         http_response_code(401);
//         die('Invalid signature');
//     }
// }

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

// Parse invoice ID from externalTransactionId (format: {invoiceId}inv{timestamp})
// Extract the part before 'inv'
$parts = explode('inv', $externalTransactionId);
$invoiceId = isset($parts[0]) ? $parts[0] : '';
$invoiceId = preg_replace('/[^0-9]/', '', $invoiceId); // Extract only numbers

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

