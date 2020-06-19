<?php
/**
 * WHMCS Remote Input Gateway Callback File
 *
 * The purpose of this file is to demonstrate how to handle the return post
 * from a Remote Input and Remote Update Gateway
 *
 * It demonstrates verifying that the payment gateway module is active,
 * validating an Invoice ID, checking for the existence of a Transaction ID,
 * Logging the Transaction for debugging, Adding Payment to an Invoice and
 * adding or updating a payment method.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2019
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

require_once __DIR__ . '/../../../init.php';

App::load_function('gateway');
App::load_function('invoice');

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Verify the module is active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

$merchantId = $gatewayParams['pt_merchantId'];
$secretKey = $gatewayParams['pt_secretKey'];
$secureHashString = $gatewayParams['pt_secureHashString'];

// Retrieve data returned in redirect
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'payment';

$action = isset($_REQUEST['is_canceled_pt']) ? 'cancel' : $action;

// Tokenization Data
$customerEmail = isset($_REQUEST['pt_customer_email']) ? $_REQUEST['pt_customer_email'] : '';
$customerPassword = isset($_REQUEST['pt_customer_password']) ? $_REQUEST['pt_customer_password'] : '';
$customerToken = isset($_REQUEST['pt_token']) ? $_REQUEST['pt_token'] : '';

$payTabsTokenData = [
    'pt_customer_email' => $customerEmail,
    'pt_customer_password' => $customerPassword,
    'pt_token' => $customerToken,
];

// Customer Data
$customerPhone = isset($_REQUEST['customer_phone']) ? $_REQUEST['customer_phone'] : '';
$amount = isset($_REQUEST['transaction_amount']) ? $_REQUEST['transaction_amount'] : '';
$currencyCode = isset($_REQUEST['transaction_currency']) ? $_REQUEST['transaction_currency'] : '';
$cardType = isset($_REQUEST['card_brand']) ? $_REQUEST['card_brand'] : '';
$cardFirstFour = isset($_REQUEST['first_4_digits']) ? $_REQUEST['first_4_digits'] : '';
$cardLastFour = isset($_REQUEST['last_4_digits']) ? $_REQUEST['last_4_digits'] : '';
$transactionId = isset($_REQUEST['transaction_id']) ? $_REQUEST['transaction_id'] : '';
$invoiceId = isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : '';
$responseCode = isset($_REQUEST['response_code']) ? $_REQUEST['response_code'] : '';
$customerName = isset($_REQUEST['customer_name']) ? $_REQUEST['customer_name'] : '';
$secureSign = isset($_REQUEST['secure_sign']) ? $_REQUEST['secure_sign'] : '';
$time = isset($_REQUEST['datetime']) ? $_REQUEST['datetime'] : '';
$transactionResponseCode = isset($_REQUEST['transaction_response_code']) ? $_REQUEST['transaction_response_code'] : '';
$message = isset($_REQUEST['detail']) ? $_REQUEST['detail'] : '';
// Uncomment if provided by gateway
// $fees = isset($_REQUEST['fees']) ? $_REQUEST['fees'] : '';


/**
 * Calculates PayTabs Comission Including VAT
 *
 * @param int $amount
 * @param int $comission_rate
 * @param int $tax_over_comission
 * @return string
 */
function calculate_pt_fee($amount, $comission_rate, $tax_over_comission) {

    $rate = $comission_rate / 100;

    $fee = $amount * $rate;

    $fee_with_tax_over_fee = $fee * 1.05;

    return (string) $fee_with_tax_over_fee;
    
}

$fees = calculate_pt_fee($amount, $gatewayParams['pt_comissionRate'], $gatewayParams['pt_taxOverComissionRate']);

$success = $responseCode === '100' ? true : false;

// Validate Verification Hash. Uncomment for production use.
$paramsForComparisonHash = [
    'order_id' => $invoiceId,
    'response_code' => $responseCode,
    'customer_name' => $customerName,
    'transaction_currency' => $currencyCode,
    'last_4_digits' => $cardLastFour,
    'customer_email' => $customerEmail,
];
       
// Class taken from: https://dev.paytabs.com/docs/express-checkout-v4/
class PaytabsSecureHash{

    private $shain_phrase;

    public function __construct($shain_phrase) {
        $this->shain_phrase = $shain_phrase;
    }

    public function createSecureHash($params){
        $string = '';
        ksort($params);
        foreach ($params as $keys => $values)
        {
        $string .= strtoupper($keys) . '=' . $values . $this->shain_phrase;
        }
        return sha1($string);
    }
}

$hashObj = new PaytabsSecureHash($secureHashString);
$comparisonHash = $hashObj->createSecureHash($paramsForComparisonHash);

if ($action != 'cancel') {
    if ($secureSign !== $comparisonHash) {
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Invalid Hash");
        die('Invalid hash.');
    }
}

if ($action == 'payment') {
    if ($success) {
        // Validate invoice id received is valid.
        $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['paymentmethod']);

        // Log to gateway log as successful.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Success");

        // Create a pay method for the newly created remote token.
        invoiceSaveRemoteCard($invoiceId, $cardLastFour, $cardType, null, $payTabsTokenData);

        // Apply payment to the invoice.
        addInvoicePayment($invoiceId, $transactionId, $amount, $fees, $gatewayModuleName);

        // Redirect to the invoice with payment successful notice.
        callback3DSecureRedirect($invoiceId, true);
    } else {
        // Log to gateway log as failed.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, "Failed");

        sendMessage('Credit Card Payment Failed', $invoiceId);

        // Redirect to the invoice with payment failed notice.
        callback3DSecureRedirect($invoiceId, false);
    }
}

if ($action == 'cancel') {
    callback3DSecureRedirect($invoiceId, false);
}

if ($action == 'create') {
    if ($success) {
        try {
            // Function available in WHMCS 7.9 and later
            createCardPayMethod(
                $customerId,
                $gatewayModuleName,
                $cardLastFour,
                $cardExpiryDate,
                $cardType,
                null, //start date
                null, //issue number
                $cardToken
            );

            // Log to gateway log as successful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Create Success');

            // Show success message.
            echo 'Create successful.';
        } catch (Exception $e) {
            // Log to gateway log as unsuccessful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, $e->getMessage());

            // Show failure message.
            echo 'Create failed. Please try again.';
        }
    } else {
        // Log to gateway log as unsuccessful.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Create Failed');

        // Show failure message.
        echo 'Create failed. Please try again.';
    }
}

if ($action == 'update') {
    if ($success) {
        try {
            // Function available in WHMCS 7.9 and later
            updateCardPayMethod(
                $customerId,
                $payMethodId,
                $cardExpiryDate,
                null, // card start date
                null, // card issue number
                $cardToken
            );

            // Log to gateway log as successful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Update Success');

            // Show success message.
            echo 'Update successful.';
        } catch (Exception $e) {
            // Log to gateway log as unsuccessful.
            logTransaction($gatewayParams['paymentmethod'], $_REQUEST, $e->getMessage());

            // Show failure message.
            echo 'Update failed. Please try again.';
        }
    } else {
        // Log to gateway log as unsuccessful.
        logTransaction($gatewayParams['paymentmethod'], $_REQUEST, 'Update Failed');

        // Show failure message.
        echo 'Update failed. Please try again.';
    }
}
