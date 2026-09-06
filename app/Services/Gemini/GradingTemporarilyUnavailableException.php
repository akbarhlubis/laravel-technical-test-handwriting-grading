<?php

namespace App\Services\Gemini;

use RuntimeException;
use Throwable;

class GradingTemporarilyUnavailableException extends RuntimeException
{
    public const CODE = 'GRADING_TEMPORARILY_UNAVAILABLE';

    public readonly int $status;

    public readonly bool $retryable;

    public function __construct(?Throwable $previous = null)
    {
        $this->status = 503;
        $this->retryable = true;

        parent::__construct(
            "We couldn't grade your handwriting right now. Your submission has been saved. Please try again in a moment.",
            0,
            $previous,
        );
    }
}
