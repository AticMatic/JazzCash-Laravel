Laravel JazzCash Mobile Wallet REST API Integration
1. Overview & Features
This package provides a straightforward way to integrate the JazzCash Mobile Wallet REST API directly into your Laravel applications. It is designed to simplify the process of initiating payments and handling callbacks securely.

Key Features:

Mobile Wallet Payment Initiation: Utilizes the JazzCash DoMWalletTransaction v2.0 REST API for direct payment processing.   
Transaction Status Inquiry: Offers a method to check the status of a previously initiated transaction (implementation based on inferred PaymentInquiry API details, requires verification with official JazzCash documentation).
Secure Callback/IPN Handling: Includes robust HMAC-SHA256 hash verification for incoming payment notifications to ensure data integrity and authenticity.   
Configurable Environments: Easily switch between JazzCash Sandbox and Production (Live) environments via configuration.
Laravel Integration: Seamlessly integrates with Laravel applications through a Service Provider and Facade.
Important Considerations:

API Focus: This package exclusively supports the direct JazzCash Mobile Wallet REST API. It does not support JazzCash Hosted Checkout, Page Redirection, Voucher Payments, or direct Card Payments. This distinction is important as other integration methods, like those found in packages such as zfhassaan/jazzcash, focus on different flows.   
API Version & CNIC Requirement: This package is built based on the available documentation for the JazzCash Mobile Wallet REST API v2.0 (CNIC Enabled). A critical requirement of this API version is the pp_CNIC parameter, which necessitates collecting the last 6 digits of the customer's CNIC (Computerized National Identity Card). Your application flow must accommodate this data collection. Failure to provide this will result in failed transactions with this specific API version. Other API versions (v1, v3, v4) are mentioned in JazzCash documentation , but detailed REST API specifications for direct mobile wallet payments through them were not sufficiently available in the provided materials to build a robust implementation in this package.   
2. Installation Guide
Require the Package via Composer:
Open your terminal and run the following command in your Laravel project's root directory:bash
composer require your-vendor/laravel-jazzcash

(Replace `your-vendor/laravel-jazzcash` with the actual package name once published on Packagist.)

Publish Configuration (Optional but Recommended):
This package comes with a configuration file that allows you to set your JazzCash credentials and other settings. To publish the configuration file to your application's config directory, run:

Bash

php artisan vendor:publish --provider="YourVendor\JazzCash\JazzCashServiceProvider" --tag="jazzcash-config"
This command will create a config/jazzcash.php file in your application.

Package Auto-Discovery:
For Laravel versions 5.5 and above, the package's Service Provider and Facade will be automatically discovered and registered by Laravel. Manual registration in config/app.php is typically not required.   

3. Configuration
After publishing the configuration file (or if you choose to use environment variables directly), you need to set your JazzCash merchant credentials. It is highly recommended to store these credentials in your application's .env file for security.

Add the following keys to your .env file and replace the placeholder values with your actual JazzCash credentials:

Code snippet

JAZZCASH_ENVIRONMENT=sandbox # or "live" for production

JAZZCASH_SANDBOX_MERCHANT_ID=your_sandbox_merchant_id
JAZZCASH_SANDBOX_PASSWORD=your_sandbox_password
JAZZCASH_SANDBOX_SALT=your_sandbox_integrity_salt
JAZZCASH_SANDBOX_RETURN_URL="<span class="math-inline">\{APP\_URL\}/jazzcash/callback" \# Default, ensure this route exists
JAZZCASH\_LIVE\_MERCHANT\_ID\=your\_live\_merchant\_id
JAZZCASH\_LIVE\_PASSWORD\=your\_live\_password
JAZZCASH\_LIVE\_SALT\=your\_live\_integrity\_salt
JAZZCASH\_LIVE\_RETURN\_URL\="</span>{APP_URL}/jazzcash/callback" # Default, ensure this route exists
The config/jazzcash.php file allows for more detailed configuration:

Configuration Options Table:

Key Path (in config/jazzcash.php)	Description	Type	Default Value (in config)	.env Variable Example	Environment Specific
environment	Sets the operating environment. Can be 'sandbox' or 'live'.	string	sandbox	JAZZCASH_ENVIRONMENT	No
api_version	The JazzCash API version this package is configured for.	string	2.0	N/A	No
language	Default language for API requests.	string	EN	N/A	No
sandbox.merchant_id	Your JazzCash Sandbox Merchant ID.	string	null	JAZZCASH_SANDBOX_MERCHANT_ID	Yes
sandbox.password	Your JazzCash Sandbox Password.	string	null	JAZZCASH_SANDBOX_PASSWORD	Yes
sandbox.integrity_salt	Your JazzCash Sandbox Integrity Salt (Hash Key).	string	null	JAZZCASH_SANDBOX_SALT	Yes
sandbox.return_url	The URL JazzCash will POST the transaction response to (Sandbox).	string	/jazzcash/callback	JAZZCASH_SANDBOX_RETURN_URL	Yes
sandbox.api_base_url	Base URL for JazzCash Sandbox REST API.	string	See config file	N/A	Yes
live.merchant_id	Your JazzCash Production (Live) Merchant ID.	string	null	JAZZCASH_LIVE_MERCHANT_ID	Yes
live.password	Your JazzCash Production (Live) Password.	string	null	JAZZCASH_LIVE_PASSWORD	Yes
live.integrity_salt	Your JazzCash Production (Live) Integrity Salt (Hash Key).	string	null	JAZZCASH_LIVE_SALT	Yes
live.return_url	The URL JazzCash will POST the transaction response to (Production).	string	/jazzcash/callback	JAZZCASH_LIVE_RETURN_URL	Yes
live.api_base_url	Base URL for JazzCash Production (Live) REST API.	string	See config file	N/A	Yes
endpoints.do_mobile_wallet_transaction	Path for the Mobile Wallet payment initiation API endpoint, relative to api_base_url.	string	See config file	N/A	No
endpoints.transaction_inquiry	Path for the Transaction Status Inquiry API endpoint, relative to api_base_url. (Needs Verification)	string	See config file	N/A	No

Export to Sheets
Refer to existing JazzCash Laravel packages for common .env key naming conventions.
The base API URLs are typically like https://sandbox.jazzcash.com.pk/ApplicationAPI/API/ for sandbox and https://payments.jazzcash.com.pk/ApplicationAPI/API/ for production.   

4. Usage Examples
4.1. Initiating Mobile Wallet Payment
To initiate a payment using a customer's JazzCash mobile wallet:

PHP

use YourVendor\JazzCash\Facades\JazzCash;
use YourVendor\JazzCash\Exceptions\JazzCashApiException;

try {
    $amount = 1000; // Amount in lowest currency unit (e.g., 10 PKR = 1000 Paisa) [1, 2]
    $mobileNumber = '03xxxxxxxxx'; // Customer's JazzCash mobile number
    $cnicLast6 = '123456'; // Last 6 digits of customer's CNIC (Mandatory for API v2.0) [1]
    $transactionRef = 'ORD-'. time(). rand(100, 999); // Your unique transaction reference number
    $billRef = 'INV-2023-101'; // Optional: Your bill or order reference
    $description = 'Payment for Order INV-2023-101';

    // Optional parameters (ppmpf_1 to ppmpf_5)
    $optionalParams = [
        'ppmpf_1' => 'custom_data_1',
        // 'ppmpf_2' => 'custom_data_2',
        //... up to ppmpf_5
    ];

    $response = JazzCash::initiateMobileWalletPayment(
        $amount,
        $mobileNumber,
        $cnicLast6,
        $transactionRef,
        $billRef,
        $description,
        $optionalParams
    );

    // $response will be an array parsed from JazzCash's JSON response
    if (isset($response) && $response === '000') {
        // Payment request submitted successfully (this does not mean payment is complete)
        // Store $transactionRef and other relevant details, await callback
        // The actual payment happens when the user approves on their mobile (if required by JazzCash flow)
        // or if it's a direct debit based on prior authorization (less common for initial payments).
        // The API response indicates the request was accepted by JazzCash.
        // For DoMWalletTransaction, the user might need to enter MPIN on their phone.
        // The final status comes via the callback.
        Log::info('JazzCash Payment Initiated: ', $response);
        // Redirect user to a pending page or show a message to check their phone.
    } else {
        // Payment initiation failed
        $errorMessage = $response?? 'Unknown error during payment initiation.';
        Log::error('JazzCash Initiation Failed: '. $errorMessage, $response);
        // Handle error, show message to user
    }

} catch (JazzCashApiException $e) {
    Log::error('JazzCash API Exception: '. $e->getMessage());
    // Handle API communication errors (e.g., network issues, server errors from JazzCash)
} catch (\Exception $e) {
    Log::error('General Exception: '. $e->getMessage());
    // Handle other unexpected errors
}
Parameters Explained:

$amount: The transaction amount in the lowest currency unit (e.g., for PKR 10.00, pass 1000). JazzCash API v2.0 documentation specifies that the last two digits are treated as decimal places.   
$mobileNumber: The customer's JazzCash registered mobile number.
$cnicLast6: The last 6 digits of the customer's CNIC. This is mandatory for the JazzCash Mobile Wallet REST API v2.0.   
$transactionRef: A unique transaction reference number generated by you (the merchant). Max 20 alphanumeric characters.   
$billRef: An optional reference for your bill or order number.
$description: A brief description of the transaction.
$optionalParams: An associative array for ppmpf_1 through ppmpf_5 custom fields.
The $response from initiateMobileWalletPayment is the direct JSON decoded array from the JazzCash API. A pp_ResponseCode of 000 generally indicates the request was successfully received and understood by JazzCash, not necessarily that the payment is complete. The final transaction outcome is communicated via the callback/IPN.

4.2. Checking Transaction Status
To inquire about the status of a previously initiated transaction:

PHP

use YourVendor\JazzCash\Facades\JazzCash;
use YourVendor\JazzCash\Exceptions\JazzCashApiException;

try {
    $transactionRef = 'ORD-1678886400123'; // The unique merchant transaction reference number

    $statusResponse = JazzCash::getTransactionStatus($transactionRef);

    // $statusResponse will be an array parsed from JazzCash's JSON response
    if (isset($statusResponse)) {
        Log::info('JazzCash Transaction Status: ', $statusResponse);
        // Process the status:
        // $statusResponse
        // $statusResponse
        // $statusResponse (or similar field indicating status)
        // Refer to JazzCash documentation for a full list of response codes and status fields.
    } else {
        Log::error('JazzCash Status Inquiry Failed - Invalid Response Format: ', $statusResponse);
    }

} catch (JazzCashApiException $e) {
    Log::error('JazzCash API Status Inquiry Exception: '. $e->getMessage());
} catch (\Exception $e) {
    Log::error('General Exception during Status Inquiry: '. $e->getMessage());
}
Parameters Explained:

$transactionRef: The unique merchant-generated transaction reference number that was used when initiating the payment.
Disclaimer for Transaction Status Inquiry: The specific JazzCash API endpoint (PaymentInquiry is inferred from community packages like one by rafayhingoro ) and the exact request/response parameters for a direct Mobile Wallet transaction status check via REST API require definitive confirmation from official JazzCash v2.0+ documentation. The current implementation is based on the best available information but should be thoroughly tested against the JazzCash sandbox and validated with their official API specifications for this specific use case. The pp_TxnType for inquiry might differ from payment initiation.   

5. Callback / IPN Handling
JazzCash communicates the final status of a transaction by sending an HTTP POST request to the Return URL you configured in your JazzCash merchant portal and/or in this package's configuration (jazzcash.php -> return_url for the respective environment).

Define a Route:
You need to define a route in your Laravel application to receive these POST requests. Typically, this is done in routes/web.php or routes/api.php.

PHP

// In routes/web.php or routes/api.php
use YourVendor\JazzCash\Http\Controllers\JazzCashCallbackController;

Route::post('/jazzcash/callback', [JazzCashCallbackController::class, 'handle'])->name('jazzcash.callback');
Note: Ensure the route path /jazzcash/callback matches what you've configured as your Return URL.
Package route examples can be seen in.   

Exclude from CSRF Verification:
Since the callback is an external POST request from JazzCash, you must exclude this route from Laravel's CSRF protection. Add the route URI to the $except array in your app/Http/Middleware/VerifyCsrfToken.php file:

PHP

// In app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'jazzcash/callback', // Or your configured callback path
    // other routes...
];
This is crucial; otherwise, Laravel will reject the callback with a 419 Page Expired error.

Handle the Callback in a Controller:
The package provides a default JazzCashCallbackController. Its handle method will automatically:

Verify the pp_SecureHash of the incoming request.
Dispatch events: JazzCashPaymentSuccess or JazzCashPaymentFailed with the response data.
You should listen for these events in your application to update your order status, notify users, etc.

Example Event Listener (in your EventServiceProvider.php):

PHP

// In app/Providers/EventServiceProvider.php
protected $listen =,
    \YourVendor\JazzCash\Events\JazzCashPaymentFailed::class => [
        \App\Listeners\ProcessFailedJazzCashPayment::class,
    ],
    //... other events
];
Example Listener (App\Listeners\ProcessSuccessfulJazzCashPayment.php):

PHP

<?php

namespace App\Listeners;

use YourVendor\JazzCash\Events\JazzCashPaymentSuccess;
use Illuminate\Support\Facades\Log;
// use App\Models\Order; // Example

class ProcessSuccessfulJazzCashPayment
{
    public function handle(JazzCashPaymentSuccess $event)
    {
        $responseData = $event->data;
        Log::info('JazzCash Payment Success Callback Received:', $responseData);

        // $transactionRef = $responseData?? null;
        // $responseCode = $responseData?? null;
        // $responseMessage = $responseData?? null;
        // $amount = $responseData['pp_Amount']?? null; // Amount in Paisa

        // if ($transactionRef && $responseCode === '000') {
        //     // Find your order/transaction by $transactionRef
        //     // $order = Order::where('transaction_id', $transactionRef)->first();
        //     // if ($order) {
        //     //     $order->status = 'completed';
        //     //     $order->payment_details = $responseData;
        //     //     $order->save();
        //     //     // Notify user, dispatch goods, etc.
        //     // }
        // }
    }
}
If you prefer to handle the callback logic directly in your own controller instead of using the package's controller and events, you can point the route to your controller method. In that method, you would inject YourVendor\JazzCash\JazzCashService and use its verifyCallbackHash() method:

PHP

// In your custom controller, e.g., App\Http\Controllers\MyJazzCashHandlerController.php
use Illuminate\Http\Request;
use YourVendor\JazzCash\Facades\JazzCash; // Or inject JazzCashService
use Illuminate\Support\Facades\Log;

public function handleJazzCashCallback(Request $request)
{
    $responseData = $request->all();
    Log::info('JazzCash Callback Received:', $responseData);

    if (JazzCash::verifyCallbackHash($responseData)) {
        // Hash is valid, proceed with processing
        $responseCode = $responseData?? null;
        $transactionRef = $responseData?? null;

        if ($responseCode === '000') { // '000' typically means success [9]
            // Payment successful
            // Update your application's order status for $transactionRef
            Log::info("JazzCash Payment Successful for TxnRef: {$transactionRef}");
            // E.g., Order::where('transaction_id', $transactionRef)->update(['status' => 'paid']);
        } else {
            // Payment failed or other status
            $responseMessage = $responseData?? 'Unknown error.';
            Log::error("JazzCash Payment Failed/Other for TxnRef: {$transactionRef}. Code: {$responseCode}, Message: {$responseMessage}");
            // E.g., Order::where('transaction_id', $transactionRef)->update(['status' => 'failed', 'error_message' => $responseMessage]);
        }

        // It's good practice to return a 200 OK response to JazzCash to acknowledge receipt
        return response()->json(['status' => 'Callback received'], 200); // [10]
    } else {
        // Hash verification failed - potential tampering or misconfiguration
        Log::error('JazzCash Callback: Invalid Secure Hash.', $responseData);
        // It's important to not process the transaction if the hash is invalid.
        // You might return a 400 Bad Request or simply log and ignore.
        return response()->json(['status' => 'Invalid hash'], 400);
    }
}
Expected Callback POST Parameters (Subset for Mobile Wallet v2.0):

The following table lists common parameters JazzCash is expected to POST to your Return URL after a DoMWalletTransaction v2.0. The exact list can vary, so always log the full incoming request during testing.

Parameter Name	Description	Example Value
pp_ResponseCode	Indicates the outcome of the transaction. '000' is typically success.	000
pp_ResponseMessage	A descriptive message about the transaction outcome.	Transaction Successful
pp_TxnRefNo	The merchant's unique transaction reference number (sent in the request).	ORD-1678886400123
pp_Amount	The transaction amount (in lowest currency unit, e.g., Paisa).	1000
pp_TxnDateTime	Date and time of the transaction (Format: YYYYMMDDHHMMSS).	20231028143000
pp_TxnCurrency	Transaction currency.	PKR
pp_BillReference	The bill/order reference (sent in the request, if provided).	INV-2023-101
pp_RetreivalReferenceNo	JazzCash's (or bank's) internal reference number for the transaction.	0123456789AB (Example)
pp_MerchantID	Your Merchant ID.	MC12345
pp_AuthCode	Authorization code from the bank/issuer (if applicable).	A1B2C3 (Example, may be empty)
pp_SecureHash	The secure hash string for verifying the integrity of the callback data.	(A long hexadecimal string)
pp_TxnType	Transaction Type (e.g., MWALLET).	MWALLET
pp_MobileNumber	Customer's mobile number (if returned by API).	03xxxxxxxxx
pp_CNIC	Customer's CNIC (last 6 digits, if returned by API).	123456
ppmpf_1 to ppmpf_5	Optional merchant-defined fields (if sent in request and returned).	custom_data_1
  
This list is based on common parameters seen in various JazzCash documentation snippets and examples. Always verify by logging the actual data received from JazzCash during sandbox testing.   

6. Security Considerations
Verify pp_SecureHash: Always verify the pp_SecureHash on every callback/IPN request. This is critical to ensure that the data has not been tampered with during transit and that the request genuinely originated from JazzCash. The package handles this if you use the provided callback controller or the JazzCash::verifyCallbackHash() method.   
Secure Credentials: Store your Merchant ID, Password, and Integrity Salt securely using your Laravel application's .env file. Do not hardcode them in your codebase.   
Integrity Salt: The Integrity Salt (Hash Key) is a secret shared between you and JazzCash. Keep it confidential.
HTTPS: Ensure your callback URL is served over HTTPS in a production environment.
Idempotency: Design your callback handler to be idempotent. This means if JazzCash sends the same callback multiple times (which can happen under certain network conditions), your system should process it correctly only once (e.g., by checking if the transaction reference number has already been processed).
7. API Version Notes
This package currently targets the JazzCash Mobile Wallet REST API v2.0 (CNIC Enabled). This is based on the most detailed documentation available for direct REST API integration for mobile wallets.   
As highlighted earlier, a key characteristic of this API version is the mandatory pp_CNIC parameter, requiring the last 6 digits of the customer's CNIC for payment initiation.
JazzCash has other API versions and integration methods (e.g., v1.1 for page redirection , different modes for card payments, vouchers ). This package does not cover those. If you need to integrate other JazzCash products or API versions, this package would require modification or you might need a different solution.   
8. Testing Guidelines
Sandbox Environment: Thoroughly test your integration using the JazzCash Sandbox environment before going live. Use the sandbox credentials provided by JazzCash.   
Test Cases:
Successful mobile wallet payment.
Failed payment (e.g., due to invalid mobile number, incorrect CNIC, insufficient funds - simulate these if sandbox allows).
Callback hash verification (test with both valid and intentionally tampered hashes if possible).
Transaction status inquiry for a successful and a failed/pending transaction.
Laravel HTTP Fake: For unit/feature testing your application's interaction with this package, you can use Laravel's Http::fake() to mock responses from the JazzCash API, allowing you to test your application logic without making actual API calls.   
9. Contribution Guide
Contributions are welcome! Please follow these guidelines:

Reporting Bugs: Open an issue on the GitHub repository, providing detailed steps to reproduce, expected behavior, and actual behavior.
Suggesting Features: Open an issue to discuss new features or improvements.
Pull Requests:
Fork the repository.
Create a new branch for your feature or bug fix.
Write tests for your changes.
Ensure your code adheres to PSR-12 coding standards.
Submit a pull request with a clear description of your changes.
Reference general contribution guidelines from open-source projects.   

10. License Information
This package is open-sourced software licensed under the MIT license.
Common open-source license.   

11. API Methods (Internal Service Class)
While the Facade (JazzCash::methodName()) is the recommended way to interact with the package, the core logic resides in the YourVendor\JazzCash\JazzCashService class. Here's a summary of its main public methods:

Method Name	Description	Key JazzCash API Called (v2.0)
initiateMobileWalletPayment	Prepares and sends a payment initiation request to the JazzCash Mobile Wallet API.	DoMWalletTransaction
getTransactionStatus	Prepares and sends a request to inquire about the status of a specific transaction. (Endpoint details need official verification).	TransactionInquiry (Inferred)
verifyCallbackHash	Verifies the pp_SecureHash in the callback data received from JazzCash to ensure data integrity.	N/A
