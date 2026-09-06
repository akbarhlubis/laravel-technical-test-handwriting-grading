<?php

namespace Tests\Unit;

use App\Models\Submission;
use PHPUnit\Framework\TestCase;

class SubmissionTest extends TestCase
{
    public function test_submission_uses_the_existing_supabase_schema_configuration(): void
    {
        $submission = new Submission;

        self::assertSame('supabase', $submission->getConnectionName());
        self::assertSame('submissions', $submission->getTable());
        self::assertSame('id', $submission->getKeyName());
        self::assertFalse($submission->getIncrementing());
        self::assertSame('string', $submission->getKeyType());
        self::assertTrue($submission->usesTimestamps());
        self::assertNull($submission->getUpdatedAtColumn());
    }
}
