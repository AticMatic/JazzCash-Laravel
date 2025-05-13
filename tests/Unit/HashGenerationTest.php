<?php

namespace Aticmatic\JazzCash\Tests\Unit;

use Aticmatic\JazzCash\Tests\TestCase; // Use the package's base TestCase
use Aticmatic\JazzCash\JazzCashService; // Access the service directly for testing protected methods if needed, or use reflection
use ReflectionMethod;

class HashGenerationTest extends TestCase
{
    protected JazzCashService $jazzCashService;

    protected function setUp(): void
    {
        parent::setUp();
        // Get an instance of JazzCashService from the service container
        $this->jazzCashService = $this->app->make(JazzCashService::class);
    }

    /**
     * Helper to access protected method generateRequestHash using reflection.
     */
    protected function invokeGenerateRequestHash(array $data): string
    {
        $method = new ReflectionMethod(JazzCashService::class, 'generateRequestHash');
        $method->setAccessible(true);
        return $method->invoke($this->jazzCashService, $data);
    }

    /**
     * Helper to access protected method generateResponseHash using reflection.
     */
    protected function invokeGenerateResponseHash(array $data): string
    {
        $method = new ReflectionMethod(JazzCashService::class, 'generateResponseHash');
        $method->setAccessible(true);
        return $method->invoke($this->jazzCashService, $data);
    }

    /** @test */
    public function it_generates_correct_request_hash_as_per_jazzcash_v2_documentation()
    {
        // Example from JazzCash MWallet REST API v2.0 (CNIC Feature) documentation [1]
        // Assuming Integrity Salt/Hash Key as "9208s6wx05" (from docs)
        // We'll use the salt configured in our test TestCase: 'testintegritysalt'
        // and adapt the example.

        $this->app['config']->set('jazzcash.sandbox.integrity_salt', '9208s6wx05');
        // Re-initialize service to pick up new salt for this specific test
        $serviceWithSpecificSalt = new JazzCashService($this->app['config']);
        $method = new ReflectionMethod(JazzCashService::class, 'generateRequestHash');
        $method->setAccessible(true);


        $data = [1];

        // Expected string to hash [1]:
        // Salt&pp_Amount_val&pp_BillReference_val&pp_CNIC_val&pp_Description_val&pp_Language_val&pp_MerchantID_val&pp_MobileNumber_val&pp_Password_val&pp_TxnCurrency_val&pp_TxnDateTime_val&pp_TxnExpiryDateTime_val&pp_TxnRefNo_val
        // 9208s6wx05&100&billRef3781&345678&Test case description&EN&MC32084&03123456789&yy41w5f10e&PKR&20220124224204&20220125224204&T71608120
        // [1]
        // Our implementation correctly sorts by key, then concatenates values.

        $expectedStringToHash = '9208s6wx05'. // Salt
            '&'. '100'.                // pp_Amount
            '&'. 'billRef3781'.        // pp_BillReference
            '&'. '345678'.             // pp_CNIC
            '&'. 'Test case description'.// pp_Description
            '&'. 'EN'.                 // pp_Language
            '&'. 'MC32084'.            // pp_MerchantID
            '&'. '03123456789'.        // pp_MobileNumber
            '&'. 'yy41w5f10e'.         // pp_Password
            '&'. 'PKR'.                // pp_TxnCurrency
            '&'. '20220124224204'.     // pp_TxnDateTime
            '&'. '20220125224204'.     // pp_TxnExpiryDateTime
            '&'. 'T71608120';           // pp_TxnRefNo

        $generatedHash = $method->invoke($serviceWithSpecificSalt, $data);
        $expectedHashFromDoc = '39ECAACFC30F9AFA1763B7E61EA33AC75977FB2E849A5EE1EDC4016791F3438F'; // From [1]

        // To verify our string construction:
        // $calculatedStringToHash = '9208s6wx05';
        // $tempData = $data; ksort($tempData, SORT_STRING);
        // foreach ($tempData as $value) { if ($value!== null && $value!== '') { $calculatedStringToHash.= '&'. (string)$value; } }
        // $this->assertEquals($expectedStringToHash, $calculatedStringToHash, "String to hash construction mismatch.");

        $this->assertEquals($expectedHashFromDoc, $generatedHash);
    }


    /** @test */
    public function it_generates_a_consistent_request_hash()
    {
        $data =;
        $hash1 = $this->invokeGenerateRequestHash($data);
        $hash2 = $this->invokeGenerateRequestHash($data); // Same data should produce same hash

        $this->assertEquals($hash1, $hash2);
        $this->assertNotEmpty($hash1);
        $this->assertEquals(64, strlen($hash1)); // SHA256 hex output length
    }

    /** @test */
    public function it_generates_request_hash_correctly_with_optional_ppmpf_fields()
    {
        $data =;

        // Expected string for hashing (values sorted by key, salt prepended, empty ppmpf_5 excluded)
        // Salt&2000&BILL002&112233&Unit Test with ppmpf&EN&TESTMERCHANT123&03021234567&testpassword&PKR&20230315100500&20230315110500&UNITTESTREF2&MWALLET&2.0&custom_val_1&custom_val_3
        $salt = config('jazzcash.sandbox.integrity_salt'); // 'testintegritysalt'
        $expectedStringToHash = $salt.
            '&'. '2000'.  // pp_Amount
            '&'. 'BILL002'. // pp_BillReference
            '&'. '112233'.  // pp_CNIC
            '&'. 'Unit Test with ppmpf'. // pp_Description
            '&'. 'EN'.      // pp_Language
            '&'. 'TESTMERCHANT123'. // pp_MerchantID
            '&'. '03021234567'. // pp_MobileNumber
            '&'. 'testpassword'. // pp_Password
            '&'. 'PKR'.     // pp_TxnCurrency
            '&'. '20230315100500'. // pp_TxnDateTime
            '&'. '20230315110500'. // pp_TxnExpiryDateTime
            '&'. 'UNITTESTREF2'. // pp_TxnRefNo
            '&'. 'MWALLET'. // pp_TxnType
            '&'. '2.0'.     // pp_Version
            '&'. 'custom_val_1'. // ppmpf_1
            '&'. 'custom_val_3';  // ppmpf_3

        $generatedHash = $this->invokeGenerateRequestHash($data);
        $this->assertEquals(strtoupper(hash_hmac('sha256', $expectedStringToHash, $salt)), $generatedHash);
    }

    /** @test */
    public function it_generates_a_consistent_response_hash()
    {
        $responseData =;

        $hash1 = $this->invokeGenerateResponseHash($responseData);
        $hash2 = $this->invokeGenerateResponseHash($responseData);

        $this->assertEquals($hash1, $hash2);
        $this->assertNotEmpty($hash1);
        $this->assertEquals(64, strlen($hash1));
    }

    /** @test */
    public function response_hash_generation_excludes_pp_securehash_field_itself()
    {
        $salt = config('jazzcash.sandbox.integrity_salt');
        $dataWithoutHashField =;
        $dataWithHashField =;

        $expectedStringToHash = $salt. '&'. '100'. '&'. '000'; // Salt&pp_Amount&pp_ResponseCode (sorted)
        $expectedHash = strtoupper(hash_hmac('sha256', $expectedStringToHash, $salt));

        $hashFromDataWithField = $this->invokeGenerateResponseHash($dataWithHashField);

        $this->assertEquals($expectedHash, $hashFromDataWithField);
    }
}