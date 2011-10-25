<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Notes\Models\Note;
use Capell\Notes\Models\NoteAssignment;
use Capell\Notes\Models\NoteMention;
use Capell\Tests\Fixtures\Models\User;

it('refuses to seed notes demo records in production without an override', function (): void {
    $originalEnvironment = app()->make('env');
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $this->artisan('capell:notes-demo')
            ->expectsOutputToContain('Notes demo seeding is blocked in the production environment.')
            ->assertExitCode(1);
    } finally {
        app()->detectEnvironment(static fn (): string => is_string($originalEnvironment) ? $originalEnvironment : 'testing');
    }
});

it('seeds populated screenshot inbox records idempotently with --force', function (): void {
    $user = User::factory()->create();
    User::factory()->create();
    Page::factory()->create();

    $this->artisan('capell:notes-demo', ['--force' => true])->assertSuccessful();
    $this->artisan('capell:notes-demo', ['--force' => true])->assertSuccessful();

    expect(Note::query()->get()->filter(fn ($note): bool => str_starts_with((string) $note->body, '[Notes demo]'))->count())->toBe(4)
        ->and(NoteAssignment::query()->where('assignee_id', $user->getKey())->whereNull('completed_at')->count())->toBe(2)
        ->and(NoteMention::query()->where('mentioned_id', $user->getKey())->count())->toBe(1);
});
