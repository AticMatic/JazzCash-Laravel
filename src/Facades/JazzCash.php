<?php

namespace Aticmatic\JazzCash\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array initiateMobileWalletPayment(int $amount, string $mobileNumber, string $cnicLast6, string $transactionRef, string $billRef = '', string $description = '', array $optionalParams =)
 * @method static array getTransactionStatus(string $transactionRef)
 * @method static bool verifyCallbackHash(array $responseData)
 *
 * @see \Aticmatic\JazzCash\JazzCashService
 */
class JazzCash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        // This should match the binding key used in JazzCashServiceProvider
        // Referenced by [28, 29]
        return 'jazzcash.service';
    }
}