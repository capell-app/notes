<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Capell\Notes\Actions\BuildUserAttentionCountsAction;
use Capell\Notes\Filament\Pages\NotesInboxPage;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\SqliteDatabaseSnapshot;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Sinnbeck\DomAssertions\Asserts\AssertElement;
use Sinnbeck\DomAssertions\Asserts\BaseAssert;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    Gate::before(static fn (): bool => true);
    Role::findOrCreate(Utils::getPanelUserRoleName(), 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
});

it('renders the notes item and attention badge in the booted admin user menu', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Utils::getPanelUserRoleName());
    User::factory()->create();
    Page::factory()->create();
    $this->actingAs($user);
    $this->artisan('capell:notes-demo', ['--force' => true])->assertSuccessful();

    expect(BuildUserAttentionCountsAction::run($user)->total())->toBe(4)
        ->and(CapellAdmin::getUserMenuItemDefinitions())->toHaveKey('capell-notes.inbox');

    // The inbox preserves the initial attention counts while marking mentions read.
    $this->get(NotesInboxPage::getUrl())
        ->assertOk()
        ->assertElementExists(
            '.fi-user-menu .fi-dropdown-list a[href="' . NotesInboxPage::getUrl() . '"]',
            fn (AssertElement $item): BaseAssert => $item
                ->containsText((string) __('capell-notes::navigation.notes'))
                ->contains('.fi-badge.fi-color-danger', ['text' => '4'], 1),
        );

    expect(BuildUserAttentionCountsAction::run($user)->mentions)->toBe(0);
});

it('keeps the notes inbox link without a badge when the user has no attention records', function (): void {
    User::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(Utils::getPanelUserRoleName());
    Page::factory()->create();
    $this->artisan('capell:notes-demo', ['--force' => true])->assertSuccessful();
    $this->actingAs($user);

    $this->get(NotesInboxPage::getUrl())
        ->assertOk()
        ->assertElementExists(
            '.fi-user-menu .fi-dropdown-list a[href="' . NotesInboxPage::getUrl() . '"]',
            fn (AssertElement $item): BaseAssert => $item
                ->containsText((string) __('capell-notes::navigation.notes'))
                ->doesntContain('.fi-badge'),
        );
});

it('renders notes through the screenshot workbench boot path without forced installation state', function (): void {
    CapellCore::markPackageInstalled('capell-app/notes');

    $result = renderNotesWorkbenchUserMenu();

    $this->assertSame(200, $result['status'], $result['error'] ?? 'Workbench response failed.');

    new TestResponse(new Response($result['html'], $result['status']))
        ->assertOk()
        ->assertElementExists(
            '.fi-user-menu .fi-dropdown-list a[href$="/notes"]',
            fn (AssertElement $item): BaseAssert => $item
                ->containsText((string) __('capell-notes::navigation.notes'))
                ->contains('.fi-badge.fi-color-danger', ['text' => '4'], 1),
        );
});

it('reports workbench setup exceptions as JSON with their class and message', function (): void {
    $result = renderNotesWorkbenchUserMenu(withSchema: false);

    expect($result['status'])->toBe(500)
        ->and($result['html'])->toBe('')
        ->and($result['error'])->toStartWith('Illuminate\\Database\\QueryException: ')
        ->toContain('no such table: roles');
});

it('reports HTTP workbench exceptions as JSON with their class and message', function (): void {
    $result = renderNotesWorkbenchUserMenu(failRequest: true);

    expect($result['status'])->toBe(500)
        ->and($result['html'])->toBe('')
        ->and($result['error'])->toBe('RuntimeException: Workbench request failed.');
});

/** @return array{status: int, html: string, error: ?string} */
function renderNotesWorkbenchUserMenu(bool $withSchema = true, bool $failRequest = false): array
{
    $directory = sys_get_temp_dir() . '/notes-user-menu-' . bin2hex(random_bytes(8));
    File::ensureDirectoryExists($directory . '/bootstrap/cache');
    File::ensureDirectoryExists($directory . '/storage/framework/views');
    File::ensureDirectoryExists($directory . '/storage/framework/sessions');
    File::ensureDirectoryExists($directory . '/storage/framework/cache/data');
    File::ensureDirectoryExists($directory . '/storage/logs');
    $database = $directory . '/screenshots.sqlite';

    try {
        $connection = new PDO('sqlite:' . $database);

        if ($withSchema) {
            SqliteDatabaseSnapshot::capture(DB::connection()->getPdo())->restore($connection);
        }

        $configuration = $directory . '/testbench.yaml';
        new Process([
            'node',
            'scripts/screenshots/build-testbench-config.mjs',
            '--output=' . $configuration,
        ], dirname(__DIR__, 5))->setTimeout(60)->mustRun();

        $process = new Process([
            PHP_BINARY,
            __DIR__ . '/../../Fixtures/render-workbench-user-menu.php',
            $database,
            'admin@example.com',
            $configuration,
            $failRequest ? '--fail-request' : '',
        ], dirname(__DIR__, 5), [
            'APP_KEY' => config()->string('app.key'),
            'APP_CONFIG_CACHE' => $directory . '/bootstrap/cache/config.php',
            'APP_PACKAGES_CACHE' => $directory . '/bootstrap/cache/packages.php',
            'APP_SERVICES_CACHE' => $directory . '/bootstrap/cache/services.php',
            'APP_ROUTES_CACHE' => $directory . '/bootstrap/cache/routes.php',
            'APP_EVENTS_CACHE' => $directory . '/bootstrap/cache/events.php',
            'LARAVEL_STORAGE_PATH' => $directory . '/storage',
            'VIEW_COMPILED_PATH' => $directory . '/storage/framework/views',
            'DB_URL' => false,
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => $database,
        ]);
        $process->setTimeout(60)->mustRun();
        /** @var array{status: int, html: string, error: ?string} $result */
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        return $result;
    } finally {
        File::deleteDirectory($directory);
    }
}
