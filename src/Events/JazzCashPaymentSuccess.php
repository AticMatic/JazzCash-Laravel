<?php

namespace Aticmatic\JazzCash\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JazzCashPaymentSuccess
{
    use Dispatchable, SerializesModels;

    public array $data;

    /**
     * Create a new event instance.
     *
     * @param array $data The response data from JazzCash
     * @return void
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }
}