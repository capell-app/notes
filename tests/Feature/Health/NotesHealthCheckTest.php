<?php

declare(strict_types=1);

use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Notes\Health\NotesHealthCheck;
use Illuminate\Support\Facades\Schema;

require_once dirname(__DIR__, 2) . '/NotesTestCase.php';

it('reports a compatible capell api version', function (): void {
    expect(NotesHealthCheck::compatibleCapellApiVersion())->toBe('^1.0');
});

it('runs real diagnostics returning check results', function (): void {
    $results = NotesHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(2)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue();
});

it('passes when the storage tables and morph aliases are present', function (): void {
    $results = NotesHealthCheck::runDiagnostics();

    expect(NotesHealthCheck::passed())->toBeTrue()
        ->and($results->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue();
});

it('confirms the notes models are registered in the morph map', function (): void {
    $check = new NotesHealthCheck;

    expect($check->unregisteredMorphAliases())->toBe([])
        ->and($check->modelMorphAliasCheck()->passed)->toBeTrue();
});

it('fails the storage table check when a notes table is missing', function (): void {
    Schema::drop('note_reminders');

    $check = new NotesHealthCheck;

    expect($check->missingTables())->toContain('note_reminders')
        ->and($check->storageTablesCheck()->passed)->toBeFalse()
        ->and(NotesHealthCheck::passed())->toBeFalse();
});
