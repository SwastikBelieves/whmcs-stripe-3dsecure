<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/10/12
 * Time: 13:55
 */

use WHMCS\Database\Capsule;
use Stripe\Stripe;
use Stripe\Source;
use Stripe\Charge;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

require_once(__DIR__ . '/../stripe-php/vendor/autoload.php');

$gatewayModuleName = basename(__FILE__, '_return.php');
$params = getGatewayVariables($gatewayModuleName);

if (!$params['type']) {
    die("Module Not Activated");
}

if (!isset($_GET['source'])) {
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

$source = Source::retrieve($_GET['source']);

if ($source['status'] == 'chargeable' && $source['type'] == 'three_d_secure'
    && $source['metadata']['identifier'] == $identifier) {
    try {
        $count = Capsule::table('tblgatewaylog')->where('data', 'like', '%' . $source['id'] . '%')->count();
        if ($count > 0) {
            header('Location: /viewinvoice.php?' . http_build_query([
                    'id' => $source['metadata']['invoice_id'],
                    'pay' => true
                ]));
            exit();
        }
        logTransaction($params['paymentmethod'], $source, 'success(source)-return');

        $charge = Charge::create([
            'amount' => $source['amount'],
            'currency' => $source['currency'],
            'source' => $_GET['source'],
            'description' => $params['companyname'] . " Invoice#" . $source['metadata']['invoice_id']
        ]);

        if ($charge['paid']) {
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

            logTransaction($params['paymentmethod'], $charge, 'success(charge)-return');
        }
    } catch (Exception $e) {

    }
}
header('Location: /viewinvoice.php?' . http_build_query([
        'id' => $source['metadata']['invoice_id'],
    ]));