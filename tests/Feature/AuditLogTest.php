<?php

namespace Tests\Feature;

use App\Filament\Resources\ThirdPartyResource\Pages\CreateThirdParty;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

class AuditLogTest extends TestCase
{
    public function test_third_party_creation_is_logged_with_causer(): void
    {
        $admin = User::where('email', 'admin@miscuentas.test')->firstOrFail();
        $identityNumber = '880101'.rand(10000, 99999);

        Livewire::actingAs($admin)
            ->test(CreateThirdParty::class)
            ->fillForm([
                'type' => 'customer',
                'identity_type' => 'CI',
                'identity_number' => $identityNumber,
                'full_name' => 'Juan Pérez',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $thirdParty = ThirdParty::where('identity_number', $identityNumber)->firstOrFail();

        $activity = Activity::where('subject_type', ThirdParty::class)
            ->where('subject_id', $thirdParty->id)
            ->latest()
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
        $this->assertEquals('third_party', $activity->log_name);
        $this->assertEquals($admin->id, $activity->causer_id);
        $this->assertNotEmpty($activity->properties->get('ip'));
    }
}