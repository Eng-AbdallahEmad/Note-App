<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class Note extends Model
{
    protected $fillable = [
        'title',
        'content',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }
}
