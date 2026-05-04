<?php

require_once __DIR__ . '/env.php';

/**
 * Paystack Configuration
 *
 * Keep real keys out of this file. Put local values in your server environment
 * or an untracked .env file.
 */

define('PAYSTACK_SECRET_KEY', trim(getenv('PAYSTACK_SECRET_KEY') ?: ''));
define('PAYSTACK_PUBLIC_KEY', trim(getenv('PAYSTACK_PUBLIC_KEY') ?: ''));
define('PAYSTACK_CURRENCY', 'NGN');

function isPaystackConfigured()
{
    return strpos(PAYSTACK_SECRET_KEY, 'replace_with_your_secret_key') === false
        && preg_match('/^sk_(test|live)_/', PAYSTACK_SECRET_KEY);
}
