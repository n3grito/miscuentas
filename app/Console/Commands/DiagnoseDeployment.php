<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DiagnoseDeployment extends Command
{
    protected $signature = 'deploy:diagnose';

    protected $description = 'Diagnostica problemas comunes de despliegue: clave de cifrado, collation, base de datos y migraciones.';

    public function handle(): int
    {
        $this->newLine();
        $this->info('===== DIAGNÓSTICO DE DESPLIEGUE - MisCuentas =====');

        $this->checkAppKey();
        $this->checkEnvironment();
        $this->checkDatabaseConnection();
        $this->checkCollation();
        $this->checkMigrations();

        $this->newLine();
        $this->info('===== FIN DEL DIAGNÓSTICO =====');

        return self::SUCCESS;
    }

    private function checkAppKey(): void
    {
        $key = config('app.key');

        if (! $key || str_starts_with($key, 'base64:') === false || strlen($key) < 20) {
            $this->error('[CRÍTICO] No hay APP_KEY válida configurada.');
            $this->line('  → Ejecuta: php artisan key:generate');
            $this->line('  → Luego:   php artisan config:clear');
        } else {
            $this->info('[OK] APP_KEY configurada correctamente.');
        }
    }

    private function checkEnvironment(): void
    {
        $env = config('app.env');

        $this->warn("[ADVERTENCIA] APP_ENV = {$env}");

        if ($env === 'production') {
            $debug = config('app.debug');
            $this->warn('[ADVERTENCIA] APP_DEBUG = '.var_export($debug, true));
            $this->line('  → En producción debe ser false.');
        }
    }

    private function checkDatabaseConnection(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $this->warn("[INFO] Conexión por defecto: {$connection} (base: {$database})");

        try {
            $connected = \Illuminate\Support\Facades\DB::connection()->getPdo();
            $this->info('[OK] Conexión a la base de datos establecida.');
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            $this->error('[CRÍTICO] No se pudo conectar a la base de datos.');
            $this->line('  → '.$msg);

            if (str_contains($msg, 'Unknown database')) {
                $this->line('  → La base de datos NO existe. Créala, por ejemplo:');
                $this->line('    CREATE DATABASE '.$database.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
            } elseif (str_contains($msg, 'Access denied')) {
                $this->line('  → Credenciales incorrectas. Revisa DB_USERNAME / DB_PASSWORD en .env.');
            }
        }
    }

    private function checkCollation(): void
    {
        try {
            $collation = \Illuminate\Support\Facades\DB::selectOne('SELECT @@collation_server as c');
            $dbCollation = \Illuminate\Support\Facades\DB::selectOne('SELECT @@collation_database as c');

            $this->line("[INFO] Collation del servidor:   ".($collation->c ?? '?'));
            $this->line("[INFO] Collation de la base:     ".($dbCollation->c ?? '?'));

            if (str_contains(($dbCollation->c ?? ''), 'utf8mb4_0900_ai_ci') && ! $this->supportsAiCi()) {
                $this->error('[CRÍTICO] El servidor (probablemente MariaDB) NO soporta utf8mb4_0900_ai_ci.');
                $this->line('  → Corrige en el .env:');
                $this->line('    DB_CHARSET=utf8mb4');
                $this->line('    DB_COLLATION=utf8mb4_unicode_ci');
                $this->line('  → Luego ejecuta: php artisan config:clear');
            } else {
                $this->info('[OK] Collation compatible.');
            }
        } catch (\Throwable) {
            $this->warn('[INFO] No se pudo comprobar la collation (no conectado a la BD).');
        }
    }

    private function supportsAiCi(): bool
    {
        try {
            $row = \Illuminate\Support\Facades\DB::selectOne("SHOW COLLATION LIKE 'utf8mb4_0900_ai_ci'");

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkMigrations(): void
    {
        try {
            $pending = \Illuminate\Support\Facades\Artisan::call('migrate:status');

            $output = \Illuminate\Support\Facades\Artisan::output();

            if (preg_match('/Pending/i', $output) > 0) {
                $this->warn('[ADVERTENCIA] Hay migraciones pendientes.');
                $this->line('  → Ejecuta: php artisan migrate --force');
            } else {
                $this->info('[OK] No hay migraciones pendientes.');
            }
        } catch (\Throwable $e) {
            $this->warn('[INFO] No se pudo comprobar migraciones: '.$e->getMessage());
        }
    }
}