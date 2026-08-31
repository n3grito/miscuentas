<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupConfigTest extends TestCase
{
    public function test_backup_config_file_exists(): void
    {
        $this->assertFileExists(config_path('backup.php'));
    }

    public function test_backup_config_has_correct_app_name(): void
    {
        $config = config('backup');

        $this->assertEquals(env('APP_NAME', 'MisCuentas'), $config['backup']['name']);
    }

    public function test_backup_config_has_mysql_connection(): void
    {
        $config = config('backup');

        $this->assertArrayHasKey('mysql', $config['backup']['source']['databases']);
    }

    public function test_backup_config_has_proper_cleanup_strategy(): void
    {
        $config = config('backup');

        $this->assertArrayHasKey('cleanup', $config);
        $this->assertEquals(30, $config['cleanup']['default']['keep_all_backups_for_days']);
        $this->assertEquals(14, $config['cleanup']['default']['keep_daily_backups_for_days']);
    }

    public function test_backup_config_destination_is_local(): void
    {
        $config = config('backup');

        $this->assertEquals('local', $config['backup']['destination']['disk']);
        $this->assertEquals('backups', $config['backup']['destination']['directory']);
    }
}