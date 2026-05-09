<?php

namespace EsFredDerick\UsefulArtisanCommands\Services;

use Illuminate\Foundation\Application;
use Illuminate\Support\Str;
use RuntimeException;

class SchemaDefaultsConfigurationService
{
    /**
     * @return array<string, array{label: string, default: string}>
     */
    public function schemaGroups(): array
    {
        return [
            'migrations' => [
                'label' => 'Migrations table schema name',
                'default' => 'database',
            ],
            'queue' => [
                'label' => 'Jobs, job batches, and failed jobs tables schema name',
                'default' => 'queue',
            ],
            'storage' => [
                'label' => 'Cache and cache locks tables schema name',
                'default' => 'storage',
            ],
            'authentication' => [
                'label' => 'Sessions and password reset tokens tables schema name',
                'default' => 'authentication',
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, env: string, schema_group: string, default_table: string, config_file: string, anchor: string, config_key: string}>
     */
    public function tableSettings(): array
    {
        return [
            'migrations' => [
                'label' => 'Migrations table name',
                'env' => 'DB_MIGRATIONS_TABLE',
                'schema_group' => 'migrations',
                'default_table' => 'migrations',
                'config_file' => 'database.php',
                'anchor' => "'migrations' => [",
                'config_key' => 'table',
            ],
            'jobs' => [
                'label' => 'Jobs table name',
                'env' => 'DB_QUEUE_TABLE',
                'schema_group' => 'queue',
                'default_table' => 'jobs',
                'config_file' => 'queue.php',
                'anchor' => "'database' => [",
                'config_key' => 'table',
            ],
            'job_batches' => [
                'label' => 'Job batches table name',
                'env' => 'DB_QUEUE_BATCHING_TABLE',
                'schema_group' => 'queue',
                'default_table' => 'job_batches',
                'config_file' => 'queue.php',
                'anchor' => "'batching' => [",
                'config_key' => 'table',
            ],
            'failed_jobs' => [
                'label' => 'Failed jobs table name',
                'env' => 'DB_QUEUE_FAILED_TABLE',
                'schema_group' => 'queue',
                'default_table' => 'failed_jobs',
                'config_file' => 'queue.php',
                'anchor' => "'failed' => [",
                'config_key' => 'table',
            ],
            'cache' => [
                'label' => 'Cache table name',
                'env' => 'DB_CACHE_TABLE',
                'schema_group' => 'storage',
                'default_table' => 'cache',
                'config_file' => 'cache.php',
                'anchor' => "'database' => [",
                'config_key' => 'table',
            ],
            'cache_locks' => [
                'label' => 'Cache locks table name',
                'env' => 'DB_CACHE_LOCK_TABLE',
                'schema_group' => 'storage',
                'default_table' => 'cache_locks',
                'config_file' => 'cache.php',
                'anchor' => "'database' => [",
                'config_key' => 'lock_table',
            ],
            'sessions' => [
                'label' => 'Sessions table name',
                'env' => 'SESSION_TABLE',
                'schema_group' => 'authentication',
                'default_table' => 'sessions',
                'config_file' => 'session.php',
                'anchor' => "'connection' => env('SESSION_CONNECTION'),",
                'config_key' => 'table',
            ],
            'password_reset_tokens' => [
                'label' => 'Password reset tokens table name',
                'env' => 'AUTH_PASSWORD_RESET_TOKEN_TABLE',
                'schema_group' => 'authentication',
                'default_table' => 'password_reset_tokens',
                'config_file' => 'auth.php',
                'anchor' => "'passwords' => [",
                'config_key' => 'table',
            ],
        ];
    }

    /**
     * @return array{label: string, default_schema: string, default_table: string}
     */
    public function userTableSetting(): array
    {
        return [
            'label' => 'Users table name',
            'default_schema' => 'client',
            'default_table' => 'users',
        ];
    }

    /**
     * @return array{label: string, default_schema: string}
     */
    public function teamTableSchemaSetting(): array
    {
        return [
            'label' => 'Team tables schema name',
            'default_schema' => 'teams',
        ];
    }

    /**
     * @return array<string, array{label: string, default_table: string}>
     */
    public function teamTableSettings(): array
    {
        return [
            'teams' => [
                'label' => 'Teams table name',
                'default_table' => 'teams',
            ],
            'team_members' => [
                'label' => 'Team members table name',
                'default_table' => 'team_members',
            ],
            'team_invitations' => [
                'label' => 'Team invitations table name',
                'default_table' => 'team_invitations',
            ],
        ];
    }

    /**
     * @return array<string, array{default_table: string}>
     */
    public function managedMigrationTableSettings(): array
    {
        return [
            'users' => ['default_table' => $this->userTableSetting()['default_table']],
            ...array_map(
                static fn (array $setting): array => ['default_table' => $setting['default_table']],
                $this->tableSettings(),
            ),
            ...array_map(
                static fn (array $setting): array => ['default_table' => $setting['default_table']],
                $this->teamTableSettings(),
            ),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function availableEnvFiles(string $basePath): array
    {
        $files = glob($basePath.'/.env*') ?: [];

        $envFiles = array_values(array_filter(array_map(
            static fn (string $file): string => basename($file),
            $files,
        ), static fn (string $file): bool => is_file($basePath.'/'.$file) && $file !== '.env.example'));

        usort($envFiles, static fn (string $first, string $second): int => match (true) {
            $first === '.env' => -1,
            $second === '.env' => 1,
            default => $first <=> $second,
        });

        return $envFiles;
    }

    /**
     * @return array<int, string>
     */
    public function managedEnvKeys(): array
    {
        return array_values(array_map(
            static fn (array $setting): string => $setting['env'],
            $this->tableSettings(),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function cleanEnvFiles(string $basePath): array
    {
        $cleaned = [];

        foreach ($this->availableEnvFiles($basePath) as $envFile) {
            $path = $basePath.'/'.$envFile;
            $contents = file_get_contents($path) ?: '';
            $cleanContents = $this->removeEnvValues($contents, $this->managedEnvKeys());

            if ($cleanContents === $contents) {
                continue;
            }

            file_put_contents($path, $cleanContents);

            $cleaned[] = $envFile;
        }

        return $cleaned;
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     * @return array{schemas: array<int, string>, updated_tables: array<int, array{file: string, table: string}>, omitted_tables: array<int, array{file: string, table: string}>, missing_migrations: array<int, string>}
     */
    public function syncDefaultMigrations(string $basePath, array $qualifiedTables): array
    {
        $syncResult = $this->updateDefaultLaravelTableMigrations($basePath, $qualifiedTables);
        $schemas = $this->writeInitialSchemasMigration($basePath, $qualifiedTables);

        return [
            ...$syncResult,
            'schemas' => $schemas,
        ];
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     */
    public function applyToConfigFiles(string $basePath, array $qualifiedTables): void
    {
        foreach ($this->tableSettings() as $key => $setting) {
            $this->replaceConfigValue(
                basePath: $basePath,
                configFile: $setting['config_file'],
                anchor: $setting['anchor'],
                configKey: $setting['config_key'],
                expression: $this->phpString($qualifiedTables[$key]),
            );
        }
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     */
    public function applyToEnvFile(string $basePath, string $envFile, array $qualifiedTables): void
    {
        $this->ensureConfigEnvCompatibility($basePath);

        $envPath = $basePath.'/'.$envFile;
        $contents = is_file($envPath) ? (file_get_contents($envPath) ?: '') : '';

        foreach ($this->tableSettings() as $key => $setting) {
            $contents = $this->writeEnvValue($contents, $setting['env'], $qualifiedTables[$key]);
        }

        file_put_contents($envPath, $contents);
    }

    public function currentEnvValue(string $basePath, string $envFile, string $key, string $default): string
    {
        $envPath = $basePath.'/'.$envFile;
        $contents = is_file($envPath) ? (file_get_contents($envPath) ?: '') : '';

        if (preg_match('/^#?\s*'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches)) {
            return trim($matches[1], '"\'') ?: $default;
        }

        return $default;
    }

    public function currentConfigValue(string $basePath, string $key, string $envFile): string
    {
        $setting = $this->tableSettings()[$key];
        $line = $this->findConfigValueLine(
            basePath: $basePath,
            configFile: $setting['config_file'],
            anchor: $setting['anchor'],
            configKey: $setting['config_key'],
        );

        if (preg_match('/\''.preg_quote($setting['config_key'], '/').'\'\s*=>\s*env\(\s*\'([^\']+)\'\s*,\s*\'([^\']*)\'\s*\),/', $line, $matches)) {
            return $this->currentEnvValue($basePath, $envFile, $matches[1], $matches[2]);
        }

        if (preg_match('/\''.preg_quote($setting['config_key'], '/').'\'\s*=>\s*\'([^\']*)\',/', $line, $matches)) {
            return $matches[1];
        }

        return $this->defaultQualifiedTable($key);
    }

    public function schemaNameFromQualifiedTable(string $qualifiedTable, string $default): string
    {
        return str_contains($qualifiedTable, '.') ? str($qualifiedTable)->before('.')->toString() : $default;
    }

    public function tableNameFromQualifiedTable(string $qualifiedTable, string $default): string
    {
        return str_contains($qualifiedTable, '.') ? str($qualifiedTable)->afterLast('.')->toString() : ($qualifiedTable ?: $default);
    }

    public function defaultQualifiedTable(string $key): string
    {
        if ($key === 'users') {
            $setting = $this->userTableSetting();

            return "{$setting['default_schema']}.{$setting['default_table']}";
        }

        $setting = $this->tableSettings()[$key];
        $schema = $this->schemaGroups()[$setting['schema_group']]['default'];

        return "{$schema}.{$setting['default_table']}";
    }

    public function currentUserQualifiedTable(string $basePath): string
    {
        $modelPath = $this->currentUserModelPath($basePath);
        $contents = $modelPath === null ? '' : (file_get_contents($modelPath) ?: '');

        if ($table = $this->configuredModelTable($contents)) {
            return $table;
        }

        return $this->defaultQualifiedTable('users');
    }

    public function hasTeamStarterMigrations(string $basePath): bool
    {
        foreach ([
            '*_create_teams_table.php',
            '*_add_current_team_id_to_users_table.php',
        ] as $pattern) {
            if ((glob($basePath.'/database/migrations/'.$pattern) ?: []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public function currentTeamQualifiedTables(string $basePath): array
    {
        $tables = array_map(
            static fn (array $setting): string => $setting['default_table'],
            $this->teamTableSettings(),
        );

        foreach (glob($basePath.'/database/migrations/*_create_teams_table.php') ?: [] as $path) {
            preg_match_all('/Schema::create\(\s*\'([^\']+)\'/', file_get_contents($path) ?: '', $matches);

            foreach ($matches[1] as $index => $table) {
                $managedKey = $this->managedKeyForMigrationTable($table)
                    ?? (['teams', 'team_members', 'team_invitations'][$index] ?? null);

                if ($managedKey === null || ! array_key_exists($managedKey, $tables)) {
                    continue;
                }

                $tables[$managedKey] = $table;
            }
        }

        return $tables;
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     * @return array<int, array{file: string, table: string}>
     */
    public function syncStarterKitModels(string $basePath, array $qualifiedTables, ?int $laravelMajorVersion = null): array
    {
        $updatedModels = [];

        foreach ($this->starterKitModelTableSettings() as $key => $setting) {
            if (! array_key_exists($key, $qualifiedTables)) {
                continue;
            }

            $path = $key === 'users'
                ? ($this->currentUserModelPath($basePath) ?? "{$basePath}/app/Models/{$setting['file']}")
                : "{$basePath}/app/Models/{$setting['file']}";

            if (! is_file($path) && $key !== 'users') {
                continue;
            }

            $contents = is_file($path) ? (file_get_contents($path) ?: '') : $this->defaultUserModelContents();
            $contents = $this->syncModelTableDeclaration(
                contents: $contents,
                className: $setting['class'],
                qualifiedTable: $qualifiedTables[$key],
                laravelMajorVersion: $laravelMajorVersion,
            );

            if ($key === 'teams' && array_key_exists('team_members', $qualifiedTables)) {
                $contents = $this->syncTeamModelPivotTableReferences($contents, $qualifiedTables['team_members']);
            }

            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            file_put_contents($path, $contents);

            $updatedModels[] = [
                'file' => str_replace($basePath.'/', '', $path),
                'table' => $qualifiedTables[$key],
            ];
        }

        return $updatedModels;
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     * @return array<int, string>
     */
    private function writeInitialSchemasMigration(string $basePath, array $qualifiedTables): array
    {
        $path = $basePath.'/database/migrations/0000_00_00_000000_create_initial_schemas.php';
        $schemas = $this->neededMigrationSchemas($basePath, $qualifiedTables);
        $upStatements = array_map(
            static fn (string $schema): string => "        DB::statement('create schema if not exists {$schema};');",
            $schemas,
        );
        $downStatements = array_map(
            static fn (string $schema): string => "        DB::statement('drop schema if exists {$schema};');",
            $schemas,
        );

        file_put_contents($path, implode("\n", [
            '<?php',
            '',
            'use Illuminate\Database\Migrations\Migration;',
            'use Illuminate\Support\Facades\DB;',
            '',
            'return new class extends Migration',
            '{',
            '    public function up(): void',
            '    {',
            ...$upStatements,
            '    }',
            '',
            '    public function down(): void',
            '    {',
            ...$downStatements,
            '    }',
            '};',
            '',
        ]));

        return $schemas;
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     * @return array{updated_tables: array<int, array{file: string, table: string}>, omitted_tables: array<int, array{file: string, table: string}>, missing_migrations: array<int, string>}
     */
    private function updateDefaultLaravelTableMigrations(string $basePath, array $qualifiedTables): array
    {
        $updatedTables = [];
        $omittedTables = [];
        $missingMigrations = [];

        foreach ($this->defaultLaravelTableMigrationPatterns() as $pattern => $required) {
            $files = glob($basePath.'/database/migrations/'.$pattern) ?: [];

            if ($files === []) {
                if ($required) {
                    $missingMigrations[] = $pattern;
                }

                continue;
            }

            foreach ($files as $path) {
                $contents = file_get_contents($path) ?: '';
                $expectedKeys = $this->expectedManagedKeysForDefaultMigration(basename($path));
                $statementIndex = 0;
                $seenOmitted = [];
                $seenUpdated = [];
                $recordUpdatedTable = function (string $qualifiedTable) use ($path, &$seenUpdated, &$updatedTables): void {
                    if (isset($seenUpdated[$qualifiedTable])) {
                        return;
                    }

                    $updatedTables[] = [
                        'file' => basename($path),
                        'table' => $qualifiedTable,
                    ];
                    $seenUpdated[$qualifiedTable] = true;
                };
                $contents = preg_replace_callback(
                    '/(Schema::(?:create|table|dropIfExists)\(\s*)\'([^\']+)\'/',
                    function (array $matches) use ($path, $qualifiedTables, $expectedKeys, &$statementIndex, &$seenOmitted, &$omittedTables, $recordUpdatedTable): string {
                        $managedKey = $this->managedKeyForMigrationTable($matches[2])
                            ?? $expectedKeys[$statementIndex % count($expectedKeys)];
                        $statementIndex++;

                        if ($managedKey === null || ! array_key_exists($managedKey, $qualifiedTables)) {
                            if (! isset($seenOmitted[$matches[2]])) {
                                $omittedTables[] = [
                                    'file' => basename($path),
                                    'table' => $matches[2],
                                ];
                                $seenOmitted[$matches[2]] = true;
                            }

                            return $matches[0];
                        }

                        $qualifiedTable = $qualifiedTables[$managedKey];
                        $recordUpdatedTable($qualifiedTable);

                        return "{$matches[1]}'{$qualifiedTable}'";
                    },
                    $contents,
                );

                if ($contents === null) {
                    throw new RuntimeException('Unable to update default Laravel table migration ['.basename($path).'].');
                }

                $contents = $this->updateConstrainedTablesInMigration($contents, $qualifiedTables, $recordUpdatedTable);

                file_put_contents($path, $contents);
            }
        }

        return [
            'updated_tables' => $updatedTables,
            'omitted_tables' => $omittedTables,
            'missing_migrations' => $missingMigrations,
        ];
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     * @return array<int, string>
     */
    private function neededMigrationSchemas(string $basePath, array $qualifiedTables): array
    {
        $migrationSchema = $this->schemaNameFromQualifiedTable($qualifiedTables['migrations'], '');
        $schemas = [];

        foreach ($qualifiedTables as $key => $qualifiedTable) {
            if ($key === 'migrations') {
                continue;
            }

            $schemas[] = $this->schemaNameFromQualifiedTable($qualifiedTable, '');
        }

        foreach (glob($basePath.'/database/migrations/*create_*_table.php') ?: [] as $path) {
            preg_match_all('/Schema::create\(\s*\'([^\']+)\'/', file_get_contents($path) ?: '', $matches);

            foreach ($matches[1] as $table) {
                $schemas[] = $this->schemaNameFromQualifiedTable($table, '');
            }
        }

        $schemas = array_values(array_unique(array_filter($schemas, static fn (string $schema): bool => $schema !== '' && $schema !== $migrationSchema)));

        sort($schemas);

        return $schemas;
    }

    /**
     * @return array<string, bool>
     */
    private function defaultLaravelTableMigrationPatterns(): array
    {
        return [
            '*_create_users_table.php' => true,
            '*_add_two_factor_columns_to_users_table.php' => false,
            '*_create_teams_table.php' => false,
            '*_add_current_team_id_to_users_table.php' => false,
            '*_create_cache_table.php' => true,
            '*_create_jobs_table.php' => true,
        ];
    }

    private function managedKeyForMigrationTable(string $table): ?string
    {
        $tableName = $this->tableNameFromQualifiedTable($table, $table);

        foreach ($this->managedMigrationTableSettings() as $key => $setting) {
            if ($setting['default_table'] === $tableName) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<int, string|null>
     */
    private function expectedManagedKeysForDefaultMigration(string $migrationFile): array
    {
        return match (true) {
            str_ends_with($migrationFile, '_create_users_table.php') => ['users', 'password_reset_tokens', 'sessions'],
            str_ends_with($migrationFile, '_add_two_factor_columns_to_users_table.php') => ['users'],
            str_ends_with($migrationFile, '_create_teams_table.php') => ['teams', 'team_members', 'team_invitations', 'team_invitations', 'team_members', 'teams'],
            str_ends_with($migrationFile, '_add_current_team_id_to_users_table.php') => ['users'],
            str_ends_with($migrationFile, '_create_cache_table.php') => ['cache', 'cache_locks'],
            str_ends_with($migrationFile, '_create_jobs_table.php') => ['jobs', 'job_batches', 'failed_jobs'],
            default => [null],
        };
    }

    /**
     * @param  array<string, string>  $qualifiedTables
     * @param  callable(string): void  $recordUpdatedTable
     */
    private function updateConstrainedTablesInMigration(string $contents, array $qualifiedTables, callable $recordUpdatedTable): string
    {
        $contents = preg_replace_callback(
            '/(\$table->foreignId\(\s*\'([^\']+)\'\s*\)(?:(?!;).)*?->constrained\(\s*)(?:\'([^\']+)\'\s*)?(\))/s',
            function (array $matches) use ($qualifiedTables, $recordUpdatedTable): string {
                $managedKey = $this->managedKeyForForeignIdColumn($matches[2])
                    ?? (isset($matches[3]) ? $this->managedKeyForMigrationTable($matches[3]) : null);

                if ($managedKey === null || ! array_key_exists($managedKey, $qualifiedTables)) {
                    return $matches[0];
                }

                $qualifiedTable = $qualifiedTables[$managedKey];
                $recordUpdatedTable($qualifiedTable);

                return "{$matches[1]}'{$qualifiedTable}'{$matches[4]}";
            },
            $contents,
        );

        if ($contents === null) {
            throw new RuntimeException('Unable to update foreign key constraints in starter migrations.');
        }

        return $contents;
    }

    private function managedKeyForForeignIdColumn(string $column): ?string
    {
        return match ($column) {
            'team_id', 'current_team_id' => 'teams',
            'user_id', 'invited_by' => 'users',
            default => null,
        };
    }

    private function currentUserModelPath(string $basePath): ?string
    {
        $path = $basePath.'/app/Models/User.php';

        if (is_file($path)) {
            return $path;
        }

        $matches = glob($basePath.'/app/Models/*/User.php') ?: [];

        sort($matches);

        return $matches[0] ?? null;
    }

    /**
     * @return array<string, array{file: string, class: string}>
     */
    private function starterKitModelTableSettings(): array
    {
        return [
            'users' => [
                'file' => 'User.php',
                'class' => 'User',
            ],
            'teams' => [
                'file' => 'Team.php',
                'class' => 'Team',
            ],
            'team_members' => [
                'file' => 'Membership.php',
                'class' => 'Membership',
            ],
            'team_invitations' => [
                'file' => 'TeamInvitation.php',
                'class' => 'TeamInvitation',
            ],
        ];
    }

    private function defaultUserModelContents(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'client.users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
PHP;
    }

    private function syncModelTableDeclaration(string $contents, string $className, string $qualifiedTable, ?int $laravelMajorVersion): string
    {
        if ($this->usesModelAttributes($laravelMajorVersion)) {
            $contents = $this->removeTableProperty($contents);
            $contents = $this->ensureUseStatement($contents, 'Illuminate\Database\Eloquent\Attributes\Table');

            return $this->ensureClassAttribute($contents, $className, "Table('{$qualifiedTable}')");
        }

        $contents = $this->removeClassAttribute($contents, 'Table');
        $contents = $this->removeUseStatement($contents, 'Illuminate\Database\Eloquent\Attributes\Table');

        return $this->ensureTableProperty($contents, $qualifiedTable);
    }

    private function configuredModelTable(string $contents): ?string
    {
        if (preg_match('/protected\s+\$table\s*=\s*\'([^\']+)\';/', $contents, $matches)) {
            return $matches[1];
        }

        if (preg_match('/#\[Table\(\s*(?:name:\s*)?\'([^\']+)\'/', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function syncTeamModelPivotTableReferences(string $contents, string $qualifiedTable): string
    {
        return preg_replace_callback(
            '/(belongsToMany\(\s*[^,]+,\s*)\'([^\']+)\'/',
            function (array $matches) use ($qualifiedTable): string {
                if ($this->managedKeyForMigrationTable($matches[2]) !== 'team_members') {
                    return $matches[0];
                }

                return "{$matches[1]}'{$qualifiedTable}'";
            },
            $contents,
        ) ?? $contents;
    }

    private function usesModelAttributes(?int $laravelMajorVersion): bool
    {
        return ($laravelMajorVersion ?? $this->currentLaravelMajorVersion()) >= 13;
    }

    private function currentLaravelMajorVersion(): int
    {
        if (class_exists('\Illuminate\Foundation\Application')) {
            return (int) Str::before(Application::VERSION, '.');
        }

        return 12;
    }

    private function ensureTableProperty(string $contents, string $qualifiedTable): string
    {
        if (preg_match('/protected\s+\$table\s*=\s*\'[^\']+\';/', $contents)) {
            return preg_replace('/protected\s+\$table\s*=\s*\'[^\']+\';/', "protected \$table = '{$qualifiedTable}';", $contents, 1) ?? $contents;
        }

        $property = <<<PHP

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected \$table = '{$qualifiedTable}';

PHP;

        if (preg_match('/\n\s+protected \$fillable\s*=/', $contents)) {
            return preg_replace('/(\n\s+protected \$fillable\s*=)/', $property.'$1', $contents, 1) ?? $contents;
        }

        return preg_replace('/(class \w+(?:\s+extends\s+[^{]+)?\s*\{\n)/', '$1'.$property, $contents, 1) ?? $contents;
    }

    private function ensureUseStatement(string $contents, string $class): string
    {
        if (str_contains($contents, "use {$class};")) {
            return $contents;
        }

        if (preg_match_all('/^use [^;]+;$/m', $contents, $matches, PREG_OFFSET_CAPTURE) > 0) {
            $lastUse = end($matches[0]);

            if ($lastUse === false) {
                return $contents;
            }

            $position = $lastUse[1] + strlen($lastUse[0]);

            return substr($contents, 0, $position)."\nuse {$class};".substr($contents, $position);
        }

        return preg_replace('/^(namespace [^;]+;\n)/m', "$1\nuse {$class};\n", $contents) ?? $contents;
    }

    private function removeUseStatement(string $contents, string $class): string
    {
        return preg_replace('/^use '.preg_quote($class, '/').";\n/m", '', $contents) ?? $contents;
    }

    private function ensureClassAttribute(string $contents, string $className, string $attribute): string
    {
        $attributePattern = preg_quote(Str::before($attribute, '('), '/');

        if (preg_match("/^#\\[{$attributePattern}(?:\\([^\\]]*\\))?\\]$/m", $contents)) {
            return preg_replace(
                "/^#\\[{$attributePattern}(?:\\([^\\]]*\\))?\\]$/m",
                "#[{$attribute}]",
                $contents,
                1,
            ) ?? $contents;
        }

        return preg_replace("/^class {$className}\\b/m", "#[{$attribute}]\nclass {$className}", $contents, 1) ?? $contents;
    }

    private function removeClassAttribute(string $contents, string $attribute): string
    {
        return preg_replace('/^#\['.preg_quote($attribute, '/').'(?:\([^\]]+\))?\]\n/m', '', $contents) ?? $contents;
    }

    private function removeTableProperty(string $contents): string
    {
        return preg_replace(
            '/\n\s*(?:\/\*\*.*?\*\/\s*)?protected \$table = [^;]+;\n/s',
            "\n",
            $contents,
        ) ?? $contents;
    }

    private function ensureConfigEnvCompatibility(string $basePath): void
    {
        foreach ($this->tableSettings() as $setting) {
            $this->replaceConfigValue(
                basePath: $basePath,
                configFile: $setting['config_file'],
                anchor: $setting['anchor'],
                configKey: $setting['config_key'],
                expression: "env('{$setting['env']}', '{$setting['default_table']}')",
            );
        }
    }

    private function replaceConfigValue(
        string $basePath,
        string $configFile,
        string $anchor,
        string $configKey,
        string $expression,
    ): void {
        [$path, $lines, $index, $matches] = $this->findConfigValueLineParts($basePath, $configFile, $anchor, $configKey);

        $lines[$index] = "{$matches[1]}'{$configKey}' => {$expression},";

        file_put_contents($path, implode("\n", $lines));
    }

    private function findConfigValueLine(string $basePath, string $configFile, string $anchor, string $configKey): string
    {
        [, $lines, $index] = $this->findConfigValueLineParts($basePath, $configFile, $anchor, $configKey);

        return $lines[$index];
    }

    /**
     * @return array{0: string, 1: array<int, string>, 2: int, 3: array<int, string>}
     */
    private function findConfigValueLineParts(string $basePath, string $configFile, string $anchor, string $configKey): array
    {
        $path = $basePath.'/config/'.$configFile;
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read config file [{$configFile}].");
        }

        $lines = explode("\n", $contents);
        $anchorIndex = null;

        foreach ($lines as $index => $line) {
            if (str_contains($line, $anchor)) {
                $anchorIndex = $index;

                break;
            }
        }

        if ($anchorIndex === null) {
            throw new RuntimeException("Unable to find [{$anchor}] in config file [{$configFile}].");
        }

        for ($index = $anchorIndex + 1; $index < count($lines); $index++) {
            if (! preg_match('/^(\s*)\''.preg_quote($configKey, '/').'\'\s*=>\s*.+,\s*$/', $lines[$index], $matches)) {
                continue;
            }

            return [$path, $lines, $index, $matches];
        }

        throw new RuntimeException("Unable to find [{$configKey}] after [{$anchor}] in config file [{$configFile}].");
    }

    private function writeEnvValue(string $contents, string $key, string $value): string
    {
        $safeValue = preg_match('/\s/', $value) ? '"'.$value.'"' : $value;
        $line = "{$key}={$safeValue}";

        $contents = preg_replace('/^#?\s*'.preg_quote($key, '/').'=.*$/m', $line, $contents, 1, $count);

        if ($contents === null) {
            throw new RuntimeException("Unable to update env key [{$key}].");
        }

        if ($count === 0) {
            $contents = rtrim($contents)."\n{$line}\n";
        }

        return $contents;
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function removeEnvValues(string $contents, array $keys): string
    {
        foreach ($keys as $key) {
            $contents = preg_replace('/^#?\s*'.preg_quote($key, '/').'=.*(?:\R|$)/m', '', $contents) ?? $contents;
        }

        $contents = preg_replace("/\n{3,}/", "\n\n", $contents) ?? $contents;

        return $contents === '' ? '' : rtrim($contents)."\n";
    }

    private function phpString(string $value): string
    {
        return var_export($value, true);
    }
}
