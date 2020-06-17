<?php
/**
 * WHMCS Sample Remote Input Gateway Module
 *
 * This sample module demonstrates how to create a merchant gateway module
 * that accepts input of payment details via a remotely hosted page that is
 * displayed within an iframe, returning a token that is stored locally for
 * future billing attempts. As a result, card data never passes through the
 * WHMCS system.
 *
 * As with all modules, within the module itself, all functions must be
 * prefixed with the module filename, followed by an underscore, and then
 * the function name. For this example file, the filename is "remoteinputgateway"
 * and therefore all functions begin "remoteinputgateway_".
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2019
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function remoteinputgateway_MetaData()
{
    return [
        'DisplayName' => 'PayTabs for WHMCS (with Tokenization)',
        'APIVersion' => '1.1', // Use API Version 1.1
    ];
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * Supported field types include:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/configuration/
 *
 * @return array
 */
function remoteinputgateway_config()
{
    return [
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'PayTabs for WHMCS (with Tokenization)',
        ],
        // a text field type allows for single line text input
        'pt_merchantId' => [
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your PayTabs Merchant ID here e.g. 10012345',
        ],
        // a password field type allows for masked text input
        'pt_secretKey' => [
            'FriendlyName' => 'Secret Key',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Secret Key which is found under Merchant Dashboard > Secret Key',
        ],
        'pt_merchantEmail' => [
            'FriendlyName' => 'Merchant Email',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your Email which you use to login to PayTabs Dashboard',
        ],
        'pt_secureHashString' => [
            'FriendlyName' => 'Secure Hash String',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'secure@paytabs#@aaes11%%',
            'Description' => 'Enter your Secure Hash String which is found under Account > Profile',
        ],
    ];
}

/**
 * No local credit card input.
 *
 * This is a required function declaration. Denotes that the module should
 * not allow local card data input.
 */
function remoteinputgateway_nolocalcc() {}

/**
 * Capture payment.
 *
 * Called when a payment is requested to be processed and captured.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */
function remoteinputgateway_capture($params)
{
    // Gateway Configuration Parameters
    $merchantId = $params['pt_merchantId'];
    $merchantEmail = $params['pt_merchantEmail'];
    $secretKey = $params['pt_secretKey'];

    // Capture Parameters
    $remoteGatewayToken = json_decode($params['gatewayid'], true);

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params['description'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // A token is required for a remote input gateway capture attempt
    if (!$remoteGatewayToken) {
        return [
            'status' => 'declined',
            'decline_message' => 'No Remote Token',
        ];
    }

    $postFields = [
        'merchant_email' => $merchantEmail,
        'secret_key' => $secretKey,
        'title' => 'Payment for Invoice #' . $invoiceId,
        'cc_first_name' => $firstname,
        'cc_last_name' => $lastname,
        'order_id' => $invoiceId,
        'product_name' => $description,
        'customer_email' => $email,
        'phone_number' => $phone,
        'amount' => $amount,
        'currency' => $currencyCode,
        'billing_shipping_details' => 'no',

        'pt_token' => $remoteGatewayToken['pt_token'],
        'pt_customer_email' => $remoteGatewayToken['pt_customer_email'],
        'pt_customer_password' => $remoteGatewayToken['pt_customer_password'],
    ];

    // Perform API call to initiate capture.
    $ch = curl_init('https://www.paytabs.com/apiv3/tokenized_transaction_prepare');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

    // execute!
    $response = json_decode(curl_exec($ch), true);

    // close the connection, release resources used
    curl_close($ch);
        
    if ($response['response_code'] == '100') {
        return [
            // 'success' if successful, otherwise 'declined', 'error' for failure
            'status' => 'success',
            // The unique transaction id for the payment
            'transid' => $response['transaction_id'],
            // Optional fee amount for the transaction
            'fee' => $response['fee'],
            // Return only if the token has updated or changed
            'gatewayid' => $response['token'],
            // Data to be recorded in the gateway log - can be a string or array
            'rawdata' => $response,
        ];
    }

    return [
        // 'success' if successful, otherwise 'declined', 'error' for failure
        'status' => 'declined',
        // For declines, a decline reason can optionally be returned
        'declinereason' => $response['decline_reason'],
        // Data to be recorded in the gateway log - can be a string or array
        'rawdata' => $response,
    ];
}

/**
 * Remote input.
 *
 * Called when a pay method is requested to be created or a payment is
 * being attempted.
 *
 * New pay methods can be created or added without a payment being due.
 * In these scenarios, the amount parameter will be empty and the workflow
 * should be to create a token without performing a charge.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */
function remoteinputgateway_remoteinput($params)
{
    // Gateway Configuration Parameters
    $merchantId = $params['pt_merchantId'];
    $secretKey = $params['pt_secretKey'];

    // Invoice Parameters
    $invoiceId = $params['invoiceid'];
    $description = $params['description'];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    // Client Parameters
    $clientId = $params['clientdetails']['id'];
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
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

    // Build a form which can be submitted to an iframe target to render
    // the payment form.

    $action = '';
    if ($amount > 0) {
        $action = 'payment';
    } else {
        $action = 'create';
    }
    
    $formFields = [
        'action' => $action,
        'merchant-id' => $merchantId,
        'secret-key' => $secretKey,
        // 'url-redirect' => $systemUrl . 'modules/gateways/callback/remoteinputgateway.php',
        'url-redirect' => 'http://whmcs.test/modules/gateways/callback/remoteinputgateway.php',
        'url-cancel' => 'http://whmcs.test/modules/gateways/callback/remoteinputgateway.php?is_canceled_pt=true',
        'amount' => $amount,
        'currency' => $currencyCode,
        'order-id' => $invoiceId,
        'customer-email-address' => $email,
        'billing-full-address' => $address1 . ' ' . $address2,
        'billing-city' => $city,
        'billing-country' => 'ARE', // TODO: Change this
        'billing-postal-code' => $postcode,
        'billing-state' => $state,
        'title' => 'Payment for Invoice #' . $invoiceId,
        'product-names' => $description,
        'customer-phone-number' => $phone,
        'customer-country-code' => '971', // TODO: Change to dynamic
        'is-tokenization' => 'true',
        'ui-type' => 'button', // TODO:
        'color' => '#3097ef', // TODO: Change to dynamic
        'ui-element-id' => 'frmRemoteCardProcess',
        'ui-show-billing-address' => 'false', // TODO:
        'ui-show-header' => 'false', // TODO:
        'checkout-button-width' => '600px', // TODO:
        'checkout-button-height' => '300px', //TODO:
        'checkout-button-img-url' => 'https://tejastraffic.com/wp-content/uploads/2018/09/PayNow.png', // TODO:
        'custom-css' => '', // TODO: 

        // TODO: Remove if not needed
        // 'customer_id' => $clientId,
        // 'first_name' => $firstname,
        // 'last_name' => $lastname,

        // Sample verification hash to protect against form tampering
        // 'verification_hash' => sha1(
        //     implode('|', [
        //         $merchantId,
        //         $clientId,
        //         $invoiceId,
        //         $amount,
        //         $currencyCode,
        //         $secretKey,
        //         '', // This will be the remoteStorageToken in an update
        //     ])
        // ),
    ];

    $formOutput = '';
    foreach ($formFields as $key => $value) {
        $formOutput .= 'data-' . $key . '=' . '"' . $value . '"' . PHP_EOL;
    }

    // This is a working example which posts to the file: demo/remote-iframe-demo.php
    return '<script src="https://www.paytabs.com/express/v4/paytabs-express-checkout.js"
    id="paytabs-express-checkout"
    ' . $formOutput . '
    >
 </script>';

}

/**
 * Remote update.
 *
 * Called when a pay method is requested to be updated.
 *
 * The expected return of this function is direct HTML output. It provides
 * more flexibility than the remote input function by not restricting the
 * return to a form that is posted into an iframe. We still recommend using
 * an iframe where possible and this sample demonstrates use of an iframe,
 * but the update can sometimes be handled by way of a modal, popup or
 * other such facility.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @see https://developers.whmcs.com/payment-gateways/remote-input-gateway/
 *
 * @return array
 */
function remoteinputgateway_remoteupdate($params)
{
    // Gateway Configuration Parameters
    $merchantId = $params['pt_merchantId'];
    $secretKey = $params['pt_secretKey'];
    $remoteStorageToken = $params['gatewayid'];

    // Client Parameters
    $clientId = $params['clientdetails']['id'];
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];
    $payMethodId = $params['paymethodid'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];

    // Build a form which can be submitted to an iframe target to render
    // the payment form.

    $formFields = [
        'merchantId' => $merchantId,
        'card_token' => $remoteStorageToken,
        'action' => 'update',
        'invoice_id' => 0,
        'amount' => 0,
        'currency' => '',
        'customer_id' => $clientId,
        'first_name' => $firstname,
        'last_name' => $lastname,
        'email' => $email,
        'address1' => $address1,
        'address2' => $address2,
        'city' => $city,
        'state' => $state,
        'postcode' => $postcode,
        'country' => $country,
        'phonenumber' => $phone,
        'return_url' => $systemUrl . 'modules/gateways/callback/remoteinputgateway.php',
        // Sample verification hash to protect against form tampering
        'verification_hash' => sha1(
            implode('|', [
                $merchantId,
                $clientId,
                0, // Invoice ID - there is no invoice for an update
                0, // Amount - there is no amount when updating
                '', // Currency Code - there is no currency when updating
                $secretKey,
                $remoteStorageToken,
            ])
        ),
        // The PayMethod ID will need to be available in the callback file after
        // update. We will pass a custom variable here to enable that.
        'custom_reference' => $payMethodId,
    ];

    $formOutput = '';
    foreach ($formFields as $key => $value) {
        $formOutput .= '<input type="hidden" name="' . $key . '" value="' . $value . '">' . PHP_EOL;
    }

    // This is a working example which posts to the file: demo/remote-iframe-demo.php
    return '<div id="frmRemoteCardProcess" class="text-center">
    <form method="post" action="#" target="remoteUpdateIFrame">
        ' . $formOutput . '
        <noscript>
            <input type="submit" value="Click here to continue &raquo;">
        </noscript>
    </form>
    <iframe name="remoteUpdateIFrame" class="auth3d-area" width="90%" height="600" scrolling="auto" src="about:blank"></iframe>
</div>
<script>
    setTimeout("autoSubmitFormByContainer(\'frmRemoteCardProcess\')", 1000);
</script>';
}

/**
 * Admin status message.
 *
 * Called when an invoice is viewed in the admin area.
 *
 * @param array $params Payment Gateway Module Parameters.
 *
 * @return array
 */
function remoteinputgateway_adminstatusmsg($params)
{
    // Gateway Configuration Parameters
    $merchantId = $params['pt_merchantId'];
    $secretKey = $params['pt_secretKey'];

    // Invoice Parameters
    $remoteGatewayToken = json_decode($params['gatewayid'], true);
    $invoiceId = $params['id']; // The Invoice ID
    $userId = $params['userid']; // The Owners User ID
    $date = $params['date']; // The Invoice Create Date
    $dueDate = $params['duedate']; // The Invoice Due Date
    $status = $params['status']; // The Invoice Status

    if ($remoteGatewayToken) {
        return [
            'type' => 'info',
            'title' => 'Token Gateway Profile',
            'msg' => 'This customer has a Remote Token storing their card'
                . ' details for automated recurring billing with Token ID ' . $remoteGatewayToken['pt_token'],
        ];
    }
}
