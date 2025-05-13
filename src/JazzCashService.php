<?php

namespace Aticmatic\JazzCash;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Config\Repository as ConfigRepository;
use Aticmatic\JazzCash\Exceptions\JazzCashApiException;
use Aticmatic\JazzCash\Exceptions\InvalidHashException;
use Carbon\Carbon;

class JazzCashService
{
    protected ConfigRepository $config;
    protected string $merchantId;
    protected string $password;
    protected string $integritySalt;
    protected string $returnUrl;
    protected string $apiBaseUrl;
    protected string $apiVersion;
    protected string $language;
    protected string $environment;
    protected string $currency;
    protected string $datetimeFormat;
    protected string $transactionExpiryDuration;

    public function __construct(ConfigRepository $config)
    {
        $this->config = $config;
        $this->environment = $this->config->get('jazzcash.environment', 'sandbox');
        $this->apiVersion = $this->config->get('jazzcash.api_version', '2.0');
        $this->language = $this->config->get('jazzcash.language', 'EN');
        $this->currency = $this->config->get('jazzcash.currency', 'PKR');
        $this->datetimeFormat = $this->config->get('jazzcash.datetime_format', 'YmdHis');
        $this->transactionExpiryDuration = $this->config->get('jazzcash.transaction_expiry_duration', '+1 hour');

        $this->loadCredentials();
    }

    protected function loadCredentials(): void
    {
        $envPrefix = ($this->environment === 'live')? 'live' : 'sandbox';
        $this->merchantId = $this->config->get("jazzcash.{$envPrefix}.merchant_id");
        $this->password = $this->config->get("jazzcash.{$envPrefix}.password");
        $this->integritySalt = $this->config->get("jazzcash.{$envPrefix}.integrity_salt");
        $this->returnUrl = $this->config->get("jazzcash.{$envPrefix}.return_url");
        $this->apiBaseUrl = rtrim($this->config->get("jazzcash.{$envPrefix}.api_base_url"), '/');

        if (!$this->merchantId ||!$this->password ||!$this->integritySalt ||!$this->apiBaseUrl) {
            throw new \InvalidArgumentException('JazzCash credentials for the current environment are not fully configured.');
        }
    }

    /**
     * Initiates a Mobile Wallet payment.
     * Uses JazzCash DoMWalletTransaction v2.0 API. [1]
     *
     * @param int $amount Amount in lowest currency unit (e.g., Paisa for PKR).
     * @param string $mobileNumber Customer's JazzCash mobile number.
     * @param string $cnicLast6 Last 6 digits of customer's CNIC (Mandatory for v2.0).
     * @param string $transactionRef Merchant's unique transaction reference.
     * @param string $billRef Optional bill/order reference.
     * @param string $description Optional payment description.
     * @param array $optionalParams Optional ppmpf_1 to ppmpf_5 fields.
     * @return array API response.
     * @throws JazzCashApiException
     */
    public function initiateMobileWalletPayment(
        int $amount,
        string $mobileNumber,
        string $cnicLast6,
        string $transactionRef,
        string $billRef = '',
        string $description = 'Payment',
        array $optionalParams =
    ): array {
        $now = Carbon::now();
        $payload =
            'pp_Amount' => (string) $amount, // Amount in lowest denomination, e.g. 10 PKR = 1000 paisa [1, 2]
            'pp_TxnCurrency' => $this->currency,
            'pp_TxnDateTime' => $now->format($this->datetimeFormat),
            'pp_TxnExpiryDateTime' => $now->copy()->modify($this->transactionExpiryDuration)->format($this->datetimeFormat),
            'pp_BillReference' => $billRef?: $transactionRef,
            'pp_Description' => $description,
            'pp_MobileNumber' => $mobileNumber,
            'pp_CNIC' => $cnicLast6, // Mandatory for v2.0 (CNIC Enabled) [1]
            // 'pp_ReturnURL' => $this->returnUrl, // Typically configured on merchant portal, but can be sent if API supports overriding
            'ppmpf_1' => $optionalParams['ppmpf_1']?? '',
            'ppmpf_2' => $optionalParams['ppmpf_2']?? '',
            'ppmpf_3' => $optionalParams['ppmpf_3']?? '',
            'ppmpf_4' => $optionalParams['ppmpf_4']?? '',
            'ppmpf_5' => $optionalParams['ppmpf_5']?? '',
        ];

        // Add pp_ReturnURL if it's not empty and the API expects it in the request body
        // Some JazzCash docs imply it's configured on the portal, others show it in requests.
        // For DoMWalletTransaction v2.0, it's usually configured on the portal. [1]
        // However, if your specific API version requires it in the payload, uncomment:
        // if (!empty($this->returnUrl)) {
        //     $payload = $this->returnUrl;
        // }


        $payload = $this->generateRequestHash($payload);

        $endpointPath = str_replace('{version}', $this->apiVersion, $this->config->get('jazzcash.endpoints.do_mobile_wallet_transaction'));
        $url = $this->apiBaseUrl. '/'. ltrim($endpointPath, '/');

        return $this->makeApiCall($url, $payload, 'Payment Initiation');
    }

    /**
     * Checks the status of a transaction.
     * Endpoint and exact pp_TxnType for mobile wallet inquiry need verification with official JazzCash documentation.
     * This implementation is based on inferred information.[8]
     *
     * @param string $transactionRef Merchant's unique transaction reference.
     * @return array API response.
     * @throws JazzCashApiException
     */
    public function getTransactionStatus(string $transactionRef): array
    {
        $payload =
            'pp_MerchantID' => $this->merchantId,
            'pp_Password' => $this->password,
            'pp_TxnRefNo' => $transactionRef,
            'pp_Language' => $this->language, // Often required for inquiry APIs
            'pp_TxnDateTime' => Carbon::now()->format($this->datetimeFormat), // Often required for inquiry APIs
        ];
        // Some inquiry APIs might not require all these params, or might need others (e.g. pp_Amount).
        // The JazzCash documentation for a direct REST API for Mobile Wallet Transaction Status Inquiry is not fully detailed in the provided research.

        $payload = $this->generateRequestHash($payload);

        $endpointPath = str_replace('{version}', $this->apiVersion, $this->config->get('jazzcash.endpoints.transaction_inquiry'));
        $url = $this->apiBaseUrl. '/'. ltrim($endpointPath, '/');

        Log::warning('JazzCash Transaction Status Inquiry: The exact API endpoint and parameters for Mobile Wallet REST API status check require verification with official JazzCash documentation. Current implementation is based on inferred details.');

        return $this->makeApiCall($url, $payload, 'Transaction Status Inquiry');
    }

    /**
     * Verifies the secure hash of an incoming callback/IPN response.
     *
     * @param array $responseData The data received from JazzCash.
     * @return bool True if hash is valid, false otherwise.
     */
    public function verifyCallbackHash(array $responseData): bool
    {
        if (!isset($responseData)) {
            Log::error('JazzCash Callback: pp_SecureHash not found in response data.', $responseData);
            return false;
        }
        $receivedHash = $responseData;
        $calculatedHash = $this->generateResponseHash($responseData);

        if (!hash_equals($calculatedHash, $receivedHash)) { // Timing attack safe comparison [22]
            Log::error('JazzCash Callback: Hash mismatch.',);
            return false;
        }
        return true;
    }

    /**
     * Generates the pp_SecureHash for an API request.
     * The method involves sorting all 'pp_' prefixed fields alphabetically,
     * concatenating their values with '&', prepending the integrity salt with an '&',
     * and then creating an HMAC-SHA256 hash.
     * Based on JazzCash documentation for v2.0.[1]
     *
     * @param array $data The data array for which to generate the hash.
     * @return string The generated HMAC-SHA256 hash.
     */
    protected function generateRequestHash(array $data): string
    {
        $sortedData =;
        // Filter for 'pp_' prefixed keys and ensure they are not null or explicitly empty strings
        // that JazzCash might ignore in hash calculation.
        // JazzCash documentation [1] states "all PP fields".
        // Some implementations [2, 11] iterate and check for empty/null/undefined.
        foreach ($data as $key => $value) {
            if (strpos($key, 'pp_') === 0 && $value!== null && $value!== '') {
                $sortedData[$key] = (string)$value;
            }
        }
        ksort($sortedData, SORT_STRING); // Sort by key alphabetically

        $hashString = $this->integritySalt;
        foreach ($sortedData as $value) {
            $hashString.= '&'. $value;
        }
        // Example from [1]: SALT&value1&value2&value3...

        return strtoupper(hash_hmac('sha256', $hashString, $this->integritySalt)); // [22, 23]
    }

    /**
     * Generates the expected pp_SecureHash for verifying a JazzCash callback response.
     * The logic should mirror how JazzCash generates the hash they send.
     * This typically involves sorting all received 'pp_' fields (excluding pp_SecureHash itself),
     * concatenating values with '&', prepending the integrity salt, and hashing.
     *
     * @param array $responseData The callback data array from JazzCash.
     * @return string The calculated HMAC-SHA256 hash.
     */
    protected function generateResponseHash(array $responseData): string
    {
        $sortedData =;
        // Filter for 'pp_' prefixed keys, exclude 'pp_SecureHash', and ensure values are not null/empty
        // as per typical JazzCash hash calculation for responses.
        foreach ($responseData as $key => $value) {
            if ($key === 'pp_SecureHash') {
                continue;
            }
            if (strpos($key, 'pp_') === 0 && $value!== null && $value!== '') {
                $sortedData[$key] = (string)$value;
            }
        }
        ksort($sortedData, SORT_STRING);

        $hashString = $this->integritySalt;
        foreach ($sortedData as $value) {
            $hashString.= '&'. $value;
        }
        // The string construction for response hash verification should be identical to request hash generation,
        // using the received POST parameters (excluding the hash itself). [2, 11]

        return strtoupper(hash_hmac('sha256', $hashString, $this->integritySalt));
    }

    /**
     * Makes an API call to JazzCash.
     * Uses Laravel HTTP Client.[18, 24]
     *
     * @param string $url The full API endpoint URL.
     * @param array $payload The data to send.
     * @param string $actionName For logging purposes.
     * @return array The JSON decoded response.
     * @throws JazzCashApiException
     */
    protected function makeApiCall(string $url, array $payload, string $actionName): array
    {
        Log::debug("JazzCash API Request ({$actionName}) to {$url}: ", $payload);

        try {
            // JazzCash REST APIs (like DoMWalletTransaction v2.0) typically expect JSON payload.
            // Content-Type: application/json [1]
            $response = Http::timeout(30) // 30-second timeout
                ->asJson() // Send data as JSON
                ->acceptJson() // Prefer JSON response
                ->post($url, $payload);

            Log::debug("JazzCash API Response ({$actionName}): ", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if (!$response->successful()) {
                $errorMessage = "{$actionName} failed. Status: {$response->status()}.";
                if ($response->body()) {
                    $errorMessage.= " Response: {$response->body()}";
                }
                Log::error($errorMessage, ['url' => $url, 'payload' => $payload]);
                throw new JazzCashApiException($errorMessage, $response->status(), $response->json()?: ['raw_response' => $response->body()]);
            }

            $jsonResponse = $response->json();
            if (is_null($jsonResponse)) {
                 Log::error("JazzCash API Error ({$actionName}): Invalid JSON response.", ['url' => $url, 'body' => $response->body()]);
                throw new JazzCashApiException("{$actionName} failed: Invalid JSON response.", $response->status(), ['raw_response' => $response->body()]);
            }
            return $jsonResponse;

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error("JazzCash API Request Exception ({$actionName}): ". $e->getMessage(), ['url' => $url, 'payload' => $payload]);
            throw new JazzCashApiException("{$actionName} request failed: ". $e->getMessage(), $e->getCode(), null, $e);
        } catch (\Exception $e) {
            Log::error("JazzCash API General Exception ({$actionName}): ". $e->getMessage(), ['url' => $url, 'payload' => $payload]);
            throw new JazzCashApiException("An unexpected error occurred during {$actionName}: ". $e->getMessage(), $e->getCode(), null, $e);
        }
    }
}