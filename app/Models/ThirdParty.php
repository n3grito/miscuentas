<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

class ThirdParty extends Model
{
    use SoftDeletes, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'type',
                'identity_type',
                'identity_number',
                'business_name',
                'full_name',
                'email',
                'phone',
                'mobile',
                'address',
                'city',
                'state',
                'zip',
                'country',
                'is_taxpayer',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('third_party');
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'ip' => request()->ip(),
        ]);
    }

    protected $fillable = [
        'type',
        'identity_type',
        'identity_number',
        'business_name',
        'full_name',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'is_taxpayer',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_taxpayer' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function displayName(): string
    {
        return $this->business_name ?: $this->full_name;
    }
}