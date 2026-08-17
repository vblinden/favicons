<?php

namespace App\Exceptions;

use RuntimeException;

class FetchRateLimitedException extends RuntimeException
{
    public function __construct(public int $retryAfter)
    {
        parent::__construct('Too many favicon fetches.');
    }
}
