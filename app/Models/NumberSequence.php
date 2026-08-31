<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = [
        'type',
        'prefix',
        'next_value',
    ];

    protected function casts(): array
    {
        return [
            'next_value' => 'integer',
        ];
    }
}