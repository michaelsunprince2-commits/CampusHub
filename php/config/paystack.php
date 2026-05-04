<?php

/**
 * Paystack Configuration
 *
 * For local testing, put your Paystack test keys here or define them as
 * environment variables: PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY.
 */

define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: 'sk_test_ce1b846b097123b4a2fb62eb7c6857ee3279a247');
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_test_59438adb72cd5d813f88eba42d3c4dc409432564');
define('PAYSTACK_CURRENCY', 'NGN');

function isPaystackConfigured()
{
    return strpos(PAYSTACK_SECRET_KEY, 'replace_with_your_secret_key') === false
        && preg_match('/^sk_(test|live)_/', PAYSTACK_SECRET_KEY);
}
