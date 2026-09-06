<?php

namespace Tests\Unit;

use App\Models\Lesson;
use PHPUnit\Framework\TestCase;

class LessonTest extends TestCase
{
    public function test_lesson_uses_the_supabase_schema_configuration(): void
    {
        $lesson = new Lesson;

        self::assertSame('supabase', $lesson->getConnectionName());
        self::assertSame('lessons', $lesson->getTable());
        self::assertSame('id', $lesson->getKeyName());
        self::assertFalse($lesson->getIncrementing());
        self::assertSame('string', $lesson->getKeyType());
        self::assertFalse($lesson->usesTimestamps());
    }

    public function test_postgres_text_array_values_are_normalized_to_php_arrays(): void
    {
        $lesson = new Lesson;
        $lesson->setRawAttributes([
            'word_list' => '{"caochang","litang","laoshi"}',
        ]);

        self::assertSame(['caochang', 'litang', 'laoshi'], $lesson->word_list);
    }
}
