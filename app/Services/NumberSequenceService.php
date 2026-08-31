<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    /**
     * Genera el siguiente número de secuencia para un tipo de documento.
     * Usa lockForUpdate para garantizar numeración correlativa segura.
     */
    public function next(string $type, string $prefix = ''): string
    {
        return DB::transaction(function () use ($type, $prefix) {
            $sequence = NumberSequence::where('type', $type)->lockForUpdate()->first();

            if (! $sequence) {
                $sequence = NumberSequence::create([
                    'type' => $type,
                    'prefix' => $prefix,
                    'next_value' => 1,
                ]);
            }

            $value = $sequence->next_value;
            $sequence->increment('next_value');

            $prefix = $prefix !== '' ? $prefix : $sequence->prefix;

            return $prefix.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
        });
    }
}