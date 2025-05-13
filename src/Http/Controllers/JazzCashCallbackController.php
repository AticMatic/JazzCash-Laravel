<?php

namespace Aticmatic\JazzCash\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Aticmatic\JazzCash\JazzCashService;
use Aticmatic\JazzCash\Events\JazzCashPaymentSuccess;
use Aticmatic\JazzCash\Events\JazzCashPaymentFailed;
use Aticmatic\JazzCash\Events\JazzCashPaymentCallbackReceived;

class JazzCashCallbackController extends Controller
{
    protected JazzCashService $jazzCashService;

    public function __construct(JazzCashService $jazzCashService)
    {
        $this->jazzCashService = $jazzCashService;
    }

    /**
     * Handles the callback/IPN from JazzCash.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $responseData = $request->all();
        Log::info('JazzCash Callback Received:', $responseData);

        // Dispatch an event with the raw callback data first
        event(new JazzCashPaymentCallbackReceived($responseData));

        if (!$this->jazzCashService->verifyCallbackHash($responseData)) {
            Log::error('JazzCash Callback: Invalid Secure Hash. Aborting.', $responseData);
            // It's important not to process if the hash is invalid.
            // JazzCash expects a 200 OK even for errors on merchant side, but for security,
            // a 400 might be more appropriate if the hash is invalid, signaling a bad request.
            // However, to prevent JazzCash from resending, a 200 might still be preferred by them.
            // Let's return 200 but log the error and not process further.
             return response()->json(['status' => 'error', 'message' => 'Invalid secure hash'], 200); // Or 400
        }

        $responseCode = $responseData?? null;
        $transactionRef = $responseData?? 'N/A';

        // Process based on response code
        // '000' is typically success [9]
        if ($responseCode === '000') {
            Log::info("JazzCash Payment Successful for TxnRef: {$transactionRef}. Dispatching Success Event.", $responseData);
            event(new JazzCashPaymentSuccess($responseData));
            // Application-specific logic should be handled by listeners of JazzCashPaymentSuccess event
        } else {
            $responseMessage = $responseData?? 'Unknown error.';
            Log::warning("JazzCash Payment Failed or Other Status for TxnRef: {$transactionRef}. Code: {$responseCode}, Message: {$responseMessage}. Dispatching Failed Event.", $responseData);
            event(new JazzCashPaymentFailed($responseData));
            // Application-specific logic for failed/other statuses by listeners of JazzCashPaymentFailed event
        }

        // Acknowledge receipt to JazzCash with a 200 OK.
        // Some gateways expect specific content. JazzCash docs are not explicit on callback response body.
        // A simple JSON ack is usually fine. [10] suggests HTTP 200.
        return response()->json(['status' => 'success', 'message' => 'Callback received and processed.'], 200);
    }
}