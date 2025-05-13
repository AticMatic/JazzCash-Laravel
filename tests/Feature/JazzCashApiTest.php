<?php

namespace Aticmatic\JazzCash\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Aticmatic\JazzCash\Facades\JazzCash;
use Aticmatic\JazzCash\Tests\TestCase;
use Aticmatic\JazzCash\Exceptions\JazzCashApiException;
use Carbon\Carbon;

class JazzCashApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Mock Carbon::now() if needed for consistent date/time in tests
        // Carbon::setTestNow(Carbon::create(2023, 1, 1, 12, 0, 0));
        Log::spy(); // Spy on Log facade
    }

    /** @test */
    public function it_can_initiate_a_mobile_wallet_payment_successfully()
    {
        Http::fake(, 200),
        ]);

        $response = JazzCash::initiateMobileWalletPayment(
            1000, // 10 PKR
            '03001234567',
            '123456',
            'TESTORD123',
            'BILLREF001',
            'Test Payment'
        );

        $this->assertIsArray($response);
        $this->assertEquals('000', $response);
        $this->assertEquals('TESTORD123', $response);

        Http::assertSent(function ($request) {
            return $request->url() == 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/2.0/Purchase/DoMWalletTransaction' &&
                   $request == 'TESTMERCHANT123' &&
                   $request == 'MWALLET' &&
                   isset($request);
        });
    }

    /** @test */
    public function it_throws_exception_on_api_error_during_initiation()
    {
        Http::fake(, 400),
        ]);

        $this->expectException(JazzCashApiException::class);
        $this->expectExceptionMessageMatches('/Payment Initiation failed. Status: 400/');

        JazzCash::initiateMobileWalletPayment(
            1000,
            '03001234567',
            '123456',
            'TESTORD124',
            'BILLREF002',
            'Test Payment Fail'
        );
    }

    /** @test */
    public function it_can_get_transaction_status_successfully()
    {
        // Note: The transaction inquiry endpoint and its exact request/response structure
        // are based on inference and require official verification.
        Http::fake(, 200),
        ]);

        $response = JazzCash::getTransactionStatus('TESTORDSTATUS123');

        $this->assertIsArray($response);
        $this->assertEquals('000', $response);
        $this->assertEquals('TESTORDSTATUS123', $response);
        $this->assertEquals('Paid', $response);

        Log::assertWarned(function ($message) {
            return str_contains($message, 'The exact API endpoint and parameters for Mobile Wallet REST API status check require verification');
        });

        Http::assertSent(function ($request) {
            return $request->url() == 'https://sandbox.jazzcash.com.pk/ApplicationAPI/API/2.0/Status/TransactionInquiry' &&
                   $request == 'PaymentInquiry' && // Based on current implementation
                   $request == 'TESTORDSTATUS123';
        });
    }

    /** @test */
    public function it_verifies_a_valid_callback_hash_correctly()
    {
        $salt = config('jazzcash.sandbox.integrity_salt');
        $responseData =;

        // Manually calculate expected hash for this test data
        $stringToHash = $salt;
        $sortedData = $responseData; // Assuming all these fields are part of hash
        ksort($sortedData, SORT_STRING);
        foreach ($sortedData as $value) {
            if ($value!== null && $value!== '') {
                 $stringToHash.= '&'. (string)$value;
            }
        }
        $expectedHash = strtoupper(hash_hmac('sha256', $stringToHash, $salt));
        $responseData = $expectedHash;

        $isValid = JazzCash::verifyCallbackHash($responseData);
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_rejects_an_invalid_callback_hash()
    {
        $responseData =;

        $isValid = JazzCash::verifyCallbackHash($responseData);
        $this->assertFalse($isValid);
        Log::assertLogged('error', function ($message, $context) {
            return str_contains($message, 'JazzCash Callback: Hash mismatch.');
        });
    }

    /** @test */
    public function it_rejects_callback_if_secure_hash_is_missing()
    {
        $responseData =; // pp_SecureHash is missing

        $isValid = JazzCash::verifyCallbackHash($responseData);
        $this->assertFalse($isValid);
        Log::assertLogged('error', function ($message, $context) {
            return str_contains($message, 'JazzCash Callback: pp_SecureHash not found in response data.');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset Carbon mock
        parent::tearDown();
    }
}