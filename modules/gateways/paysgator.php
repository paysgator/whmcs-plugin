<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module metadata.
 *
 * @return array
 */
function paysgator_MetaData()
{
    return array(
        'DisplayName' => 'Paysgator',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function paysgator_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Paysgator',
        ),
        'apiKey' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter your Paysgator API Key (Live or Test)',
        ),
        'webhookSecret' => array(
            'FriendlyName' => 'Webhook Secret',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Optional: Enter your Paysgator Webhook Secret for signature verification',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick this to enable test mode',
        ),
    );
}

/**
 * Generate payment link.
 *
 * @param array $params
 * @return string
 */
function paysgator_link($params)
{
    // Gateway Configuration Parameters
    $apiKey = $params['apiKey'];
    $testMode = $params['testMode'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // Endpoint URL
    $url = 'https://paysgator.com/api/v1/payment/create';

    // Generate sanitized externalTransactionId with timestamp (max 15 chars)
    // Format: {invoiceId}inv{timestamp} truncated to 15 chars
    $externalTxId = $invoiceId . 'inv' . time();
    $externalTxId = preg_replace('/[^a-zA-Z0-9_-]/', '', $externalTxId);
    $externalTxId = substr($externalTxId, 0, 15);
    
    // Prepare Payload
    $postData = [
        'amount' => (double)$amount,
        'currency' => $currencyCode,
        'externalTransactionId' => $externalTxId,
        'fields' => ['name', 'email', 'phone', 'address'],
        'returnUrl' => $returnUrl,
        'metadata' => [
            'description' => $description,
            'source' => 'WHMCS',
            'invoice_id' => $invoiceId,
            'client_email' => $email
        ]
    ];

    // Make API Request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Api-Key: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    if ($curlError) {
        return '<div class="alert alert-danger">Payment Error: ' . htmlspecialchars($curlError) . '</div>';
    }
    
    $data = json_decode($response, true);
    
    if ($httpCode !== 200 || !isset($data['success']) || !$data['success']) {
        $errorMsg = isset($data['error']['message']) ? $data['error']['message'] : 'Payment creation failed';
        return '<div class="alert alert-danger">Payment Error: ' . htmlspecialchars($errorMsg) . '</div>';
    }
    
    $checkoutUrl = $data['data']['checkoutUrl'];
    
    $htmlOutput = '<form method="get" action="' . htmlspecialchars($checkoutUrl) . '">';
    $htmlOutput .= '<input type="submit" value="' . $langPayNow . '" class="btn btn-primary" />';
    $htmlOutput .= '</form>';
    
    return $htmlOutput;
}
