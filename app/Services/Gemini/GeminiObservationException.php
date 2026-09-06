<?php

namespace App\Services\Gemini;

use RuntimeException;
use Throwable;

class GeminiObservationException extends RuntimeException
{
    public function __construct(
        public readonly int $status = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Unable to observe handwriting.', 0, $previous);
    }
}
