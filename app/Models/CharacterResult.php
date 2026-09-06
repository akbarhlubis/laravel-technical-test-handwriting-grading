<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CharacterResult extends Model
{
    protected $connection = 'supabase';

    protected $table = 'character_results';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'id',
        'submission_id',
        'character_name',
        'recognized_text',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'created_at' => 'datetime',
    ];
}
