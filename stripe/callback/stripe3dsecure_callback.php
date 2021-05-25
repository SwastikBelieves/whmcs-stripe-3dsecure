<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/10/12
 * Time: 13:55
 */

use WHMCS\Database\Capsule;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Charge;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

require_once(__DIR__ . '/../stripe-php/vendor/autoload.php');

$gatewayModuleName = basename(__FILE__, '_callback.php');
$params = getGatewayVariables($gatewayModuleName);

if (!$params['type']) {
    die("Module Not Activated");
}

if (!isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
    die("Wrong request");
}

if ($params['testMode'] === 'on') {
    $publishableKey = $params['testPublishableKey'];
    $secretKey = $params['testSecretKey'];
    $webhooksSigningSecret = $params['testWebhooksSigningSecret'];
    $identifier = $params['testIdentifier'];
} else {
    $publishableKey = $params['livePublishableKey'];
    $secretKey = $params['liveSecretKey'];
    $webhooksSigningSecret = $params['liveWebhooksSigningSecret'];
    $identifier = $params['liveIdentifier'];
}

Stripe::setApiKey($secretKey);

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

try {
    $event = Webhook::constructEvent(
        $payload, $sig_header, $webhooksSigningSecret
    );

    if ($event['type'] == 'source.chargeable' && $event['data']['object']['type'] == 'three_d_secure'
        && $event['data']['object']['metadata']['identifier'] == $identifier) {
        $source = $event['data']['object'];

        $count = Capsule::table('tblgatewaylog')->where('data', 'like', '%' . $source['id'] . '%')->count();
        if ($count > 0) exit();
        logTransaction($params['paymentmethod'], $source, 'success(source)-callback');

        Charge::create([
            'amount' => $source['amount'],
            'currency' => $source['currency'],
            'source' => $source['id'],
            'description' => $params['companyname'] . " Invoice#" . $source['metadata']['invoice_id']
        ]);
    } elseif ($event['type'] == 'charge.succeeded' && $event['data']['object']['source']['type'] == 'three_d_secure'
        && $event['data']['object']['source']['metadata']['identifier'] == $identifier) {
        $charge = $event['data']['object'];
        $source = $charge['source'];

        checkCbInvoiceID($source['metadata']['invoice_id'], $params['name']);//如果发票号无效，则将停止回调脚本执行。
        checkCbTransID($charge['id']);//如果回调ID重复，则将停止回调脚本执行。

        $amount = $source['amount'] / 100;
        if (!empty(trim($params['convertto']))) {
            $currencyType = Capsule::table('tblcurrencies')->where('id', $params['convertto'])->first();
            $userInfo = Capsule::table('tblinvoices')->where('id', $source['metadata']['invoice_id'])->first();
            $currency = Capsule::table('tblclients')->where('id', $userInfo->userid)->first();
            $amount = convertCurrency($amount, $currencyType->id, $currency->currency);//$currency->currency 为用户货币种类ID
        }

        $transactionFee = ($amount * ($params['transactionFeePer'] / 100)) + ($params['transactionFeeFixed'] / 100);

        addInvoicePayment(
            $source['metadata']['invoice_id'],
            $charge['id'],
            $amount,
            $transactionFee,
            $params['paymentmethod']
        );

        logTransaction($params['paymentmethod'], $charge, 'success(charge)-callback');
    }
} catch (Exception $e) {
    http_response_code(400);
}
