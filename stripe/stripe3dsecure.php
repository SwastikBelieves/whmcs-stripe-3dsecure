<?php
/**
 * Created by PhpStorm.
 * User: Admin
 * Date: 2018/10/15
 * Time: 10:48
 */

use Stripe\Stripe;
use Stripe\Refund;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once(__DIR__ . '/stripe-php/vendor/autoload.php');

function stripe3dsecure_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Stripe 3d secure'
        ],
        'testMode' => [
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ],
        'testPublishableKey' => [
            'FriendlyName' => 'Test publishable key',
            'Type' => 'text',
            'Size' => 64,
            'Description' => 'Enter test publishable key here',
        ],
        'testSecretKey' => [
            'FriendlyName' => 'Test secret key',
            'Type' => 'text',
            'Size' => 64,
            'Description' => 'Enter test secret key here',
        ],
        'testWebhooksSigningSecret' => [
            'FriendlyName' => 'Test webhooks signing secret key',
            'Type' => 'text',
            'Size' => 64,
            'Description' => 'Enter test webhooks signing secret key here',
        ],
        'testIdentifier' => [
            'FriendlyName' => 'Test site identifier',
            'Type' => 'text',
            'Size' => 64,
            'Default' => '',
            'Description' => 'Enter test identifier (must be unique)'
        ],
        'livePublishableKey' => [
            'FriendlyName' => 'Live publishable key',
            'Type' => 'text',
            'Size' => 64,
            'Description' => 'Enter live publishable key here',
        ],
        'liveSecretKey' => [
            'FriendlyName' => 'Live secret key',
            'Type' => 'text',
            'Size' => 64,
            'Description' => 'Enter live secret key here',
        ],
        'liveWebhooksSigningSecret' => [
            'FriendlyName' => 'Live webhooks signing secret key',
            'Type' => 'text',
            'Size' => 64,
            'Description' => 'Enter live webhooks signing secret key here',
        ],
        'liveIdentifier' => [
            'FriendlyName' => 'Live site identifier',
            'Type' => 'text',
            'Size' => 64,
            'Default' => '',
            'Description' => 'Enter live identifier (must be unique)'
        ],
        'transactionFeePer' => [
            'FriendlyName' => 'Transaction fee percentage',
            'Type' => 'text',
            'Size' => 5,
            'Default' => 2.9,
            'Description' => 'The percentage of transaction fee for each successful charge (eg: 2.9%)'
        ],
        'transactionFeeFixed' => [
            'FriendlyName' => 'Fixed transaction fee',
            'Type' => 'text',
            'Size' => 5,
            'Default' => 30,
            'Description' => 'A fixed transaction fee for each successful charge (eg: 30 Cent)'
        ]
    ];
}

function stripe3dsecure_link($params)
{
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
	
	$amount = abs($params['amount'] * 100);
    $currency = strtolower($params['currency']);
    $invoiceid = $params['invoiceid'];

    if(isset($_GET['pay'])) return '<div class="alert alert-success" role="alert">Thank You! Your payment was successful.</div>';
	
	if(!strpos($_SERVER['PHP_SELF'], 'viewinvoice')) {
		$html = '<form action="' . $params['systemurl'] . 'viewinvoice.php' . '" method="get">';
		$html .= '<input type="hidden" name="id" value="' . $invoiceid . '" />';
		$html .= '<input type="submit" class="btn btn-primary" value="' . $params['langpaynow'] . '" /></form>';
		return $html;
	}

    $returnUrl = $params['systemurl'] . 'modules/gateways/callback/stripe3dsecure_return.php';
    $ownerInfo = json_encode([
        'owner' => [
            'name' => $params['clientdetails']['fullname'],
            'address' => [
                'line1' => $params['clientdetails']['address1'],
                'line2' => $params['clientdetails']['address2'],
                'city' => $params['clientdetails']['city'],
                'state' => $params['clientdetails']['fullstate'],
                'postal_code' => $params['clientdetails']['postcode'],
                'country' => $params['clientdetails']['country'],
            ],
            'email' => $params['clientdetails']['email'],
        ]
    ]);

    $html = <<<html
<style>
    .StripeElement {
        background-color: white;
        height: 40px;
        padding: 10px 12px;
        border-radius: 4px;
        border: 1px solid transparent;
        box-shadow: 0 1px 3px 0 #e6ebf1;
        -webkit-transition: box-shadow 150ms ease;
        transition: box-shadow 150ms ease;
    }

    .StripeElement--focus {
        box-shadow: 0 1px 3px 0 #cfd7df;
    }

    .StripeElement--invalid {
        border-color: #fa755a;
    }

    .StripeElement--webkit-autofill {
        background-color: #fefde5 !important;
    }

    #payment-button {
        border: none;
        border-radius: 4px;
        outline: none;
        text-decoration: none;
        color: #fff;
        background: #ff629b;
        white-space: nowrap;
        display: inline-block;
        height: 40px;
        line-height: 40px;
        padding: 0 14px;
        box-shadow: 0 4px 6px rgba(50, 50, 93, .11), 0 1px 3px rgba(0, 0, 0, .08);
        border-radius: 4px;
        font-size: 15px;
        font-weight: 600;
        letter-spacing: 0.025em;
        text-decoration: none;
        -webkit-transition: all 150ms ease;
        transition: all 150ms ease;
        float: left;
        margin-left: 12px;
        margin-top: 28px;
    }
</style>
<script src="https://js.stripe.com/v3/"></script>

<form action="" method="post" id="payment-form">
    <div class="form-row">
        <label for="card-element">
            Visa, Mastercard and American Express
        </label>
        <div id="card-element">
            <!-- A Stripe Element will be inserted here. -->
        </div>

        <!-- Used to display Element errors. -->
        <div id="card-errors" role="alert"></div>
    </div>

    <button id="payment-button">Pay Now</button>
</form>
<script>
    var stripe = Stripe('$publishableKey');
    var elements = stripe.elements();
    var style = {
        base: {
            color: '#2e3234',
            lineHeight: '18px',
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            fontSmoothing: 'antialiased',
            fontSize: '16px',
            '::placeholder': {
                color: '#aab7c4'
            }
        },
        invalid: {
            color: '#fa755a',
            iconColor: '#fa755a'
        }
    };

    // Create an instance of the card Element.
    var card = elements.create('card', {style: style});

    // Add an instance of the card Element into the `card-element` <div>.
    card.mount('#card-element');

    card.addEventListener('change', function (event) {
        var displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });

    var form = document.getElementById('payment-form');
    var button = document.getElementById('payment-button');
    var errorElement = document.getElementById('card-errors');
    
    var ownerInfo = $ownerInfo;
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        
        
        button.disabled = true;
        button.style.backgroundColor = 'buttonface';

        stripe.createSource(card, ownerInfo).then(function (result) {
            if (result.error) {
                errorElement.textContent = result.error.message;
                button.disabled = false;
                button.style.backgroundColor = '#2e3234';
            } else {
                if (result.source.card.three_d_secure === 'not_supported') {
                    errorElement.textContent = 'Unfortunately your payment attempt was not successful.';
                    button.disabled = false;
                    button.style.backgroundColor = '#2e3234';
                    return false;
                }

                var src = (result.source.id);
                stripe.createSource({
                    type: 'three_d_secure',
                    amount: $amount,
                    currency: "$currency",
                    three_d_secure: {
                        card: src
                    },
                    redirect: {
                        return_url: '$returnUrl'
                    },
                    metadata: {
                        invoice_id: '$invoiceid',
                        identifier: '$identifier'
                    }
                }).then(function (result) {
                    if (result.error) {
                        errorElement.textContent = result.error.message;
                        button.disabled = false;
                        button.style.backgroundColor = '#2e3234';
                    } else {
                        window.location = result.source.redirect.url;
                    }
                });
            }
        });
    });
</script>
html;
    return $html;
}

function stripe3dsecure_refund($params)
{
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

    try {
        $refund = Refund::create([
            'charge' => $params['transid'],
            'amount' => abs($params['amount'] * 100)
        ]);
        if ($refund['status'] == 'succeeded' || $refund['status'] == 'pending')
        {
            return [
                'status' => 'success',
                'rawdata' => $refund,
                'transid' => $refund['id'],
            ];
        } else {
            return [
                'status' => 'declined',
                'rawdata' => $refund,
                'transid' => $refund['id'],
            ];
        }

    } catch (Exception $e){
        return [
            'status' => 'error',
            'rawdata' => $e->getMessage()
        ];
    }
}