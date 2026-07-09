<?php

declare(strict_types=1);

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
