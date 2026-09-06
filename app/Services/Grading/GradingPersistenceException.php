<?php

namespace App\Services\Grading;

use RuntimeException;
use Throwable;

class GradingPersistenceException extends RuntimeException
{
    public function __construct(
        public readonly int $status = 500,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Unable to persist grading results.', 0, $previous);
    }
}
