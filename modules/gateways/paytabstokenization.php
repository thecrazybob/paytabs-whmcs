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
function paytabstokenization_MetaData()
{
    return [
        'DisplayName' => 'PayTabs (with Tokenization)',
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
function paytabstokenization_config()
{
    return [
        // the friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'PayTabs for WHMCS (with Tokenization)',
        ],
        'pt_merchantId' => [
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Enter your PayTabs Merchant ID here e.g. 10012345',
        ],
        'pt_secretKey' => [
            'FriendlyName' => 'Secret Key',
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Enter your Secret Key which is found under Merchant Dashboard > Secret Key',
        ],
        'pt_merchantEmail' => [
            'FriendlyName' => 'Merchant Email',
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Enter your Email which you use to login to PayTabs Dashboard',
        ],
        'pt_secureHashString' => [
            'FriendlyName' => 'Secure Hash String',
            'Type' => 'text',
            'Size' => '40',
            'Default' => 'secure@paytabs#@aaes11%%',
            'Description' => 'Enter your Secure Hash String which is found under Account > Profile',
        ],
        'pt_type' => [
            'FriendlyName' => 'PayTabs Checkout Type',
            'Type' => 'dropdown',
            'Options' => array(
                'button' => 'Button',
                'iframe' => 'Iframe',
            ),
            'Description' => 'Button / Iframe',
        ],
        'pt_color' => [
            'FriendlyName' => 'Theme Colour',
            'Type' => 'text',
            'Size' => '7',
            'Description' => 'Enter CSS Hex color including # e.g. #3097ef',
        ],
        'pt_hideBillingAddress' => [
            'FriendlyName' => 'Hide Billing Address',
            'Type' => 'yesno',
            'Description' => 'Hide Billing Address in PayTabs Form',
        ],
        'pt_hideHeader' => [
            'FriendlyName' => 'Hide Header',
            'Type' => 'yesno',
            'Description' => 'Hide Header in PayTabs Form',
        ],
        'pt_buttonImgWidth' => [
            'FriendlyName' => 'Checkout Button Image Width',
            'Type' => 'text',
            'Size' => '5',
            'Description' => 'Width in px e.g. 30px',
        ],
        'pt_buttonImgHeight' => [
            'FriendlyName' => 'Checkout Button Height',
            'Type' => 'text',
            'Size' => '5',
            'Description' => 'Width in px e.g. 30px',
        ],
        'pt_buttonImgUrl' => [
            'FriendlyName' => 'Checkout Custom Image',
            'Type' => 'text',
            'Size' => '100',
            'Description' => 'Enter url to custom image',
        ],
        'pt_customCss' => [
            'FriendlyName' => 'Checkout Form CSS',
            'Type' => 'textarea',
            'Description' => 'Add any custom css over here',
        ],
        'pt_comissionRate' => [
            'FriendlyName' => 'PayTabs Comission Rate',
            'Type' => 'text',
            'Default' => '2.65',
            'Description' => 'PayTabs Comission e.g. 2.65 (do not add percentage sign)',
        ],
        'pt_taxOverComissionRate' => [
            'FriendlyName' => 'PayTabs VAT over Comission Rate',
            'Type' => 'text',
            'Default' => '5',
            'Description' => 'PayTabs VAT over comission e.g. 5 (do not add percentage sign)',
        ],

    ];
}

/**
 * No local credit card input.
 *
 * This is a required function declaration. Denotes that the module should
 * not allow local card data input.
 */
function paytabstokenization_nolocalcc() {}

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
function paytabstokenization_capture($params)
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

        $fees = calculate_pt_fee($amount, $params['pt_comissionRate'], $params['pt_taxOverComissionRate']);

        return [
            // 'success' if successful, otherwise 'declined', 'error' for failure
            'status' => 'success',
            // The unique transaction id for the payment
            'transid' => $response['transaction_id'],
            // Optional fee amount for the transaction
            'fee' => $fees,
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
function paytabstokenization_remoteinput($params)
{
    // Gateway Configuration Parameters
    $merchantId = $params['pt_merchantId'];
    $secretKey = $params['pt_secretKey'];

    $showBilling = $params['pt_hideBillingAddress'] == 'on' ? 'false' : 'true';
    $showHeader =  $params['pt_hideHeader'] == 'on' ? 'false' : 'true';

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
    $phone_country_code = $params['clientdetails']['phonecc'];

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

    // PayTabs Country Mapping
    $countries = '{"BD": "BGD", "BE": "BEL", "BF": "BFA", "BG": "BGR", "BA": "BIH", "BB": "BRB", "WF": "WLF", "BL": "BLM", "BM": "BMU", "BN": "BRN", "BO": "BOL", "BH": "BHR", "BI": "BDI", "BJ": "BEN", "BT": "BTN", "JM": "JAM", "BV": "BVT", "BW": "BWA", "WS": "WSM", "BQ": "BES", "BR": "BRA", "BS": "BHS", "JE": "JEY", "BY": "BLR", "BZ": "BLZ", "RU": "RUS", "RW": "RWA", "RS": "SRB", "TL": "TLS", "RE": "REU", "TM": "TKM", "TJ": "TJK", "RO": "ROU", "TK": "TKL", "GW": "GNB", "GU": "GUM", "GT": "GTM", "GS": "SGS", "GR": "GRC", "GQ": "GNQ", "GP": "GLP", "JP": "JPN", "GY": "GUY", "GG": "GGY", "GF": "GUF", "GE": "GEO", "GD": "GRD", "GB": "GBR", "GA": "GAB", "SV": "SLV", "GN": "GIN", "GM": "GMB", "GL": "GRL", "GI": "GIB", "GH": "GHA", "OM": "OMN", "TN": "TUN", "JO": "JOR", "HR": "HRV", "HT": "HTI", "HU": "HUN", "HK": "HKG", "HN": "HND", "HM": "HMD", "VE": "VEN", "PR": "PRI", "PS": "PSE", "PW": "PLW", "PT": "PRT", "SJ": "SJM", "PY": "PRY", "IQ": "IRQ", "PA": "PAN", "PF": "PYF", "PG": "PNG", "PE": "PER", "PK": "PAK", "PH": "PHL", "PN": "PCN", "PL": "POL", "PM": "SPM", "ZM": "ZMB", "EH": "ESH", "EE": "EST", "EG": "EGY", "ZA": "ZAF", "EC": "ECU", "IT": "ITA", "VN": "VNM", "SB": "SLB", "ET": "ETH", "SO": "SOM", "ZW": "ZWE", "SA": "SAU", "ES": "ESP", "ER": "ERI", "ME": "MNE", "MD": "MDA", "MG": "MDG", "MF": "MAF", "MA": "MAR", "MC": "MCO", "UZ": "UZB", "MM": "MMR", "ML": "MLI", "MO": "MAC", "MN": "MNG", "MH": "MHL", "MK": "MKD", "MU": "MUS", "MT": "MLT", "MW": "MWI", "MV": "MDV", "MQ": "MTQ", "MP": "MNP", "MS": "MSR", "MR": "MRT", "IM": "IMN", "UG": "UGA", "TZ": "TZA", "MY": "MYS", "MX": "MEX", "IL": "ISR", "FR": "FRA", "IO": "IOT", "SH": "SHN", "FI": "FIN", "FJ": "FJI", "FK": "FLK", "FM": "FSM", "FO": "FRO", "NI": "NIC", "NL": "NLD", "NO": "NOR", "NA": "NAM", "VU": "VUT", "NC": "NCL", "NE": "NER", "NF": "NFK", "NG": "NGA", "NZ": "NZL", "NP": "NPL", "NR": "NRU", "NU": "NIU", "CK": "COK", "XK": "XKX", "CI": "CIV", "CH": "CHE", "CO": "COL", "CN": "CHN", "CM": "CMR", "CL": "CHL", "CC": "CCK", "CA": "CAN", "CG": "COG", "CF": "CAF", "CD": "COD", "CZ": "CZE", "CY": "CYP", "CX": "CXR", "CR": "CRI", "CW": "CUW", "CV": "CPV", "CU": "CUB", "SZ": "SWZ", "SY": "SYR", "SX": "SXM", "KG": "KGZ", "KE": "KEN", "SS": "SSD", "SR": "SUR", "KI": "KIR", "KH": "KHM", "KN": "KNA", "KM": "COM", "ST": "STP", "SK": "SVK", "KR": "KOR", "SI": "SVN", "KP": "PRK", "KW": "KWT", "SN": "SEN", "SM": "SMR", "SL": "SLE", "SC": "SYC", "KZ": "KAZ", "KY": "CYM", "SG": "SGP", "SE": "SWE", "SD": "SDN", "DO": "DOM", "DM": "DMA", "DJ": "DJI", "DK": "DNK", "VG": "VGB", "DE": "DEU", "YE": "YEM", "DZ": "DZA", "US": "USA", "UY": "URY", "YT": "MYT", "UM": "UMI", "LB": "LBN", "LC": "LCA", "LA": "LAO", "TV": "TUV", "TW": "TWN", "TT": "TTO", "TR": "TUR", "LK": "LKA", "LI": "LIE", "LV": "LVA", "TO": "TON", "LT": "LTU", "LU": "LUX", "LR": "LBR", "LS": "LSO", "TH": "THA", "TF": "ATF", "TG": "TGO", "TD": "TCD", "TC": "TCA", "LY": "LBY", "VA": "VAT", "VC": "VCT", "AE": "ARE", "AD": "AND", "AG": "ATG", "AF": "AFG", "AI": "AIA", "VI": "VIR", "IS": "ISL", "IR": "IRN", "AM": "ARM", "AL": "ALB", "AO": "AGO", "AQ": "ATA", "AS": "ASM", "AR": "ARG", "AU": "AUS", "AT": "AUT", "AW": "ABW", "IN": "IND", "AX": "ALA", "AZ": "AZE", "IE": "IRL", "ID": "IDN", "UA": "UKR", "QA": "QAT", "MZ": "MOZ"}';

    $countries_array = json_decode($countries, true);

    $selected_country_iso3_code = $countries_array[$params['clientdetails']['countrycode']];
    
    $formFields = [
        'action' => $action,
        'merchant-id' => $merchantId,
        'secret-key' => $secretKey,
        'url-redirect' => $systemUrl . 'modules/gateways/callback/paytabstokenization.php',
        'url-cancel' => $systemUrl . 'modules/gateways/callback/paytabstokenization.php?is_canceled_pt=true',
        'amount' => $amount,
        'currency' => $currencyCode,
        'order-id' => $invoiceId,
        'customer-email-address' => $email,
        'billing-full-address' => $address1 . ' ' . $address2,
        'billing-city' => $city,
        'billing-country' => $selected_country_iso3_code,
        'billing-postal-code' => $postcode,
        'billing-state' => $state,
        'title' => 'Payment for Invoice #' . $invoiceId,
        'product-names' => $description,
        'customer-phone-number' => $phone,
        'customer-country-code' => $phone_country_code,
        'is-tokenization' => 'true',
        'ui-type' => $params['pt_type'],
        'color' => $params['pt_color'],
        'ui-element-id' => 'frmRemoteCardProcess',
        'ui-show-billing-address' => $showBilling,
        'ui-show-header' => $showHeader,
        'checkout-button-width' => $params['pt_buttonImgWidth'], 
        'checkout-button-height' => $params['pt_buttonImgHeight'],
        'checkout-button-img-url' => $params['pt_buttomImgUrl'], 
        'custom-css' => $params['pt_customCss'],  
    ];

    $formOutput = '';
    foreach ($formFields as $key => $value) {
        $formOutput .= 'data-' . $key . '=' . '"' . $value . '"' . PHP_EOL;
    }

    // Post params to PayTabs
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
function paytabstokenization_remoteupdate($params)
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
        'return_url' => $systemUrl . 'modules/gateways/callback/paytabstokenization.php',
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
function paytabstokenization_adminstatusmsg($params)
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
