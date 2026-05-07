<?php

use EsFredDerick\UsefulArtisanCommands\Services\PgsqlVerificationService;

require_once dirname(__DIR__, 2).'/src/Services/PgsqlVerificationService.php';

it('only verifies migration commands', function (string $command, bool $expected) {
    expect((new PgsqlVerificationService)->shouldVerifyCommand($command))->toBe($expected);
})->with([
    'migrate' => ['migrate', true],
    'migrate fresh' => ['migrate:fresh', true],
    'migrate rollback' => ['migrate:rollback', true],
    'db seed' => ['db:seed', false],
    'migrate status without namespace' => ['migrate-status', false],
    'test' => ['test', false],
]);

it('quotes PostgreSQL identifiers', function () {
    $service = new PgsqlVerificationService;
    $method = new ReflectionMethod(PgsqlVerificationService::class, 'quoteIdentifier');
    $method->setAccessible(true);

    expect($method->invoke($service, 'database'))->toBe('"database"')
        ->and($method->invoke($service, 'domain-schema'))->toBe('"domain-schema"')
        ->and($method->invoke($service, 'with"quote'))->toBe('"with""quote"');
});
