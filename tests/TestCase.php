<?php

namespace Aticmatic\JazzCash\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Aticmatic\JazzCash\Providers\JazzCashServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return;
    }

    protected function getPackageAliases($app)
    {
        return [
            'JazzCash' => \Aticmatic\JazzCash\Facades\JazzCash::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default config values for tests
        $app['config']->set('jazzcash.environment', 'sandbox');
        $app['config']->set('jazzcash.api_version', '2.0');
        $app['config']->set('jazzcash.language', 'EN');
        $app['config']->set('jazzcash.currency', 'PKR');
        $app['config']->set('jazzcash.datetime_format', 'YmdHis');
        $app['config']->set('jazzcash.transaction_expiry_duration', '+1 hour');

        $app['config']->set('jazzcash.sandbox.merchant_id', 'TESTMERCHANT123');
        $app['config']->set('jazzcash.sandbox.password', 'testpassword');
        $app['config']->set('jazzcash.sandbox.integrity_salt', 'testintegritysalt');
        $app['config']->set('jazzcash.sandbox.return_url', '/jazzcash/callback');
        $app['config']->set('jazzcash.sandbox.api_base_url', 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/');

        $app['config']->set('jazzcash.live.merchant_id', 'LIVEMERCHANT123');
        $app['config']->set('jazzcash.live.password', 'livepassword');
        $app['config']->set('jazzcash.live.integrity_salt', 'liveintegritysalt');
        $app['config']->set('jazzcash.live.return_url', '/jazzcash/callback');
        $app['config']->set('jazzcash.live.api_base_url', 'https://payments.jazzcash.com.pk/ApplicationAPI/API/');

        $app['config']->set('jazzcash.endpoints.do_mobile_wallet_transaction', '{version}/Purchase/DoMWalletTransaction');
        $app['config']->set('jazzcash.endpoints.transaction_inquiry', '{version}/Status/TransactionInquiry');
    }
}