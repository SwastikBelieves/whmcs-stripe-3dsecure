# WHMCS Stripe 3DSecure Module (Source code included)

WHMCS Stripe 3D Secure Module by MXiDev.com (China - Thief)

# Step 1: Upload files to modules / payments
# Step 2: Stripe set webhooks via add endpoint, set URL to 'https://yourdomain.com/modules/payments/callback/stripe3dsecure_callback.php' and event type as source.chargeable, charge.succeeded (https://dashboard.stripe.com/webhooks)
# Step 3 (**Optional): Edit modules/gateways/stripe3dsecure.php 145 line add - width: 100%; (below the height option)
# Step 4 (**Optional): Edit modules/gateways/stripe3dsecure.php 205 line add - class="btn btn-primary" (after id="payment-button")
