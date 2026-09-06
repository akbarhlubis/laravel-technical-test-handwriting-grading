<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $connection = 'supabase';

    protected $table = 'lessons';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected function getWordListAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '{}') {
            return [];
        }

        $value = trim((string) $value);

        if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $value = substr($value, 1, -1);
        }

        return $value === '' ? [] : str_getcsv($value, ',', '"', '\\');
    }
}
