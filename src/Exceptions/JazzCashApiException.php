<?php

namespace Aticmatic\JazzCash\Exceptions;

use Exception;
use Throwable;

class JazzCashApiException extends Exception
{
    protected $apiResponse;

    public function __construct($message = "", $code = 0, $apiResponse = null, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->apiResponse = $apiResponse;
    }

    /**
     * Get the API response if available.
     *
     * @return mixed
     */
    public function getApiResponse()
    {
        return $this->apiResponse;
    }
}