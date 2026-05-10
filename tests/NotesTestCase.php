<?php

declare(strict_types=1);

namespace Capell\Notes\Tests;

use Capell\Admin\Providers\AdminServiceProvider;
use Capell\Admin\Providers\Filament\AdminPanelProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Notes\Providers\NotesServiceProvider;
use Capell\Notes\Support\NotesManager;
use Capell\Tests\AbstractTestCase;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Foundation\Application;
use Livewire\LivewireServiceProvider;
use Override;

class NotesTestCase extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerAndMigrateSettings(
            CapellCore::getSettingMigrations(),
            __DIR__ . '/../../../vendor/capell-app/core/database/settings',
        );

        $notes = resolve(NotesManager::class);
        $notes->clear();
        $notes->registerSubject(User::class);
        $notes->registerParticipant(User::class);
    }

    protected function getPackageServiceName(): string
    {
        return 'capell-notes';
    }

    /**
     * @param  Application  $app
     * @return class-string[]
     */
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ...parent::getPackageProviders($app),
            NotesServiceProvider::class,
            AdminPanelProvider::class,
            AdminServiceProvider::class,
            LivewireServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);

        CapellCore::forcePackageInstalled(AdminServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(NotesServiceProvider::$packageName);
    }
}
