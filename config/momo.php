<?php

return [
    'enabled' => env('MOMO_ENABLED', false),
    'environment' => env('MOMO_ENVIRONMENT', 'sandbox'),
    // Bind this merchant to one business; never collect another tenant's money into it.
    'business_id' => (int) env('MOMO_BUSINESS_ID', 0),
    'partner_code' => env('MOMO_PARTNER_CODE'),
    'access_key' => env('MOMO_ACCESS_KEY'),
    'secret_key' => env('MOMO_SECRET_KEY'),
];
