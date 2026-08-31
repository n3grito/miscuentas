<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;

class LogViewerAccessTest extends TestCase
{
    public function test_log_viewer_redirects_anonymous_users(): void
    {
        $this->get('/log-viewer')->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_access_log_viewer_and_dashboard(): void
    {
        $user = User::where('email', 'admin@miscuentas.test')->firstOrFail();

        $this->actingAs($user)
            ->get('/log-viewer')
            ->assertOk();

        $this->actingAs($user)
            ->get('/log-viewer')
            ->assertOk();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
}