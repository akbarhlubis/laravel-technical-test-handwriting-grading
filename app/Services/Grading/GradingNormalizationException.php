<?php

namespace App\Services\Grading;

use RuntimeException;
use Throwable;

class GradingNormalizationException extends RuntimeException
{
    public function __construct(string $message = 'Unable to normalize handwriting grading.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
