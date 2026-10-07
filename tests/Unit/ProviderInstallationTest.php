<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Tests\Support\PackageInstallationTestCase;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;

it('activates installed runtime once after metadata has already booted', function (): void {
    PackageInstallationTestCase::assertInProcessInstallation('notes', function (Application $app, Closure $refresh): void {
        $finder = $app->make(Factory::class)->getFinder();
        throw_unless($finder instanceof FileViewFinder, RuntimeException::class);
        expect(CapellCore::getModels())->not->toHaveKey('Note');
        $refresh();
        expect(CapellCore::getModels())->toHaveKey('Note')
            ->and(CapellCore::getProtectedTables())->toContain('notes')
            ->and(CapellAdmin::getUserMenuItemDefinitions())->toHaveKey('capell-notes.inbox')
            ->and(collect($app->make(Schedule::class)->events())->filter(fn ($event): bool => str_contains($event->command ?? '', 'capell:notes:send-due-reminders')))->toHaveCount(1);

        $schedule = $app->make(Schedule::class);
        $scheduledEvents = $schedule->events();
        $listeners = $app->make(Dispatcher::class)->getRawListeners();
        $views = $finder->getHints();
        $refresh();
        expect($app->make(Dispatcher::class)->getRawListeners())->toBe($listeners)
            ->and($finder->getHints())->toBe($views)
            ->and($schedule->events())->toBe($scheduledEvents);
    });
});
