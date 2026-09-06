<?php

namespace App\Services\Storage;

use RuntimeException;
use Throwable;

class SupabaseStorageException extends RuntimeException
{
    public function __construct(
        public readonly int $status = 502,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Unable to upload handwriting image.', 0, $previous);
    }
}
