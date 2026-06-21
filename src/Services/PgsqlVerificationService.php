<?php

namespace SMonteroMx\UsefulArtisanCommands\Services;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PDO;
use Throwable;

use function config;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

class PgsqlVerificationService
{
    public function registerCommandListener(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($this->shouldVerifyCommand($event->command)) {
                $this->ensureDatabaseAndSchemaExist();
            }
        });
    }

    public function shouldVerifyCommand(string $command): bool
    {
        return $command === 'migrate' || Str::startsWith($command, 'migrate:');
    }

    /**
     * @throws Throwable
     */
    public function ensureDatabaseAndSchemaExist(): void
    {
        intro('PostgreSQL Verification');

        try {
            $connection = DB::connection();

            if ($connection->getDriverName() !== 'pgsql') {
                outro('Non-PostgreSQL driver detected, skipping verification.');

                return;
            }

            $connection->getPdo();
        } catch (Throwable $exception) {
            if ($this->isMissingDatabaseException($exception)) {
                $this->createDatabaseViaMaintenanceConnection();
            } else {
                throw $exception;
            }
        }

        $this->ensureMigrationSchema();

        outro('PostgreSQL is ready.');
    }

    private function isMissingDatabaseException(Throwable $exception): bool
    {
        return Str::contains($exception->getMessage(), ['does not exist', 'no existe'])
            || ($exception instanceof \PDOException && $exception->getCode() === '3D000');
    }

    private function createDatabaseViaMaintenanceConnection(): void
    {
        $config = config('database.connections.pgsql', []);

        if (! is_array($config)) {
            warning('No PostgreSQL connection configuration was found, skipping database creation.');

            return;
        }

        $database = $config['database'] ?? null;

        if (! is_string($database) || $database === '') {
            warning('No PostgreSQL database name is configured, skipping database creation.');

            return;
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        $dsn = "pgsql:host={$host};port={$port};dbname=postgres";

        try {
            note("Attempting to create database '{$database}' via maintenance connection...");

            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
            $stmt->execute([$database]);

            if (! $stmt->fetch()) {
                $pdo->exec('CREATE DATABASE '.$this->quoteIdentifier($database));

                info("Database '{$database}' created successfully.");
            } else {
                warning("Database '{$database}' already exists, skipping creation.");
            }
        } catch (Throwable $exception) {
            error("Could not auto-create database '{$database}': {$exception->getMessage()}");
        }
    }

    private function ensureMigrationSchema(): void
    {
        $migrationTable = config('database.migrations.table', 'migrations');

        if (is_string($migrationTable) && Str::contains($migrationTable, '.')) {
            $schema = Str::before($migrationTable, '.');

            DB::statement('CREATE SCHEMA IF NOT EXISTS '.$this->quoteIdentifier($schema));

            info("Schema '{$schema}' is ready.");
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
