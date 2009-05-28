<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Core\Models\Page;
use Capell\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;
use Orchestra\Testbench\Contracts\Config as ConfigContract;
use Orchestra\Testbench\Foundation\Application;
use Orchestra\Testbench\Foundation\Bootstrap\SyncTestbenchCachedRoutes;
use Orchestra\Testbench\Foundation\Config;
use Orchestra\Testbench\Workbench\Workbench;
use Orchestra\Workbench\AuthServiceProvider;
use Orchestra\Workbench\WorkbenchServiceProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$root = dirname(__DIR__, 4);
require $root . '/vendor/autoload.php';

// Testbench disables getenv loading. Populate its immutable environment before
// loading the same YAML and skeleton used by the screenshot HTTP server.
foreach (['APP_KEY', 'APP_CONFIG_CACHE', 'APP_PACKAGES_CACHE', 'APP_SERVICES_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'LARAVEL_STORAGE_PATH', 'VIEW_COMPILED_PATH'] as $key) {
    $value = getenv($key);
    if (is_string($value)) {
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}

$_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $argv[1];
$_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
$_ENV['APP_RUNNING_IN_CONSOLE'] = $_SERVER['APP_RUNNING_IN_CONSOLE'] = 'false';
define('TESTBENCH_WORKING_PATH', $root);

try {
    $config = Config::loadFromYaml(workingPath: $root, filename: $argv[3]);
    $basePath = $config['laravel'];

    if (! is_string($basePath) || $basePath === '') {
        throw new LogicException('Workbench configuration must specify a Laravel skeleton path.');
    }

    // Use Testbench's HTTP bootstrap with a freshly generated configuration rather
    // than relying on the checkout's ignored screenshot preparation artefacts.
    $app = Application::create(
        basePath: $basePath,
        options: ['load_environment_variables' => false, 'extra' => $config->getExtraAttributes()],
        resolvingCallback: static function (LaravelApplication $app) use ($config): void {
            $app->instance(ConfigContract::class, $config);

            if ($config->getWorkbenchAttributes()['auth'] === true) {
                $app->register(AuthServiceProvider::class);
            }

            $app->register(WorkbenchServiceProvider::class);
            Workbench::discoverRoutes($app, $config);
        },
    );
    (new SyncTestbenchCachedRoutes)->bootstrap($app);
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();

    // Let the outer JSON boundary report request failures as well as boot/setup
    // failures; Laravel's HTML exception handler otherwise hides their cause.
    $app->singleton(ExceptionHandler::class, static fn (LaravelApplication $app): Handler => new class($app) extends Handler
    {
        #[Override]
        public function render(mixed $request, Throwable $e): Response
        {
            throw $e;
        }
    });

    // Match the package HTTP tests' withoutVite(): this checks server-rendered
    // menu behaviour and does not require the screenshot asset build.
    $app->instance(Vite::class, new class extends Vite
    {
        #[Override]
        public function __invoke(mixed $entrypoints, mixed $buildDirectory = null): HtmlString
        {
            return new HtmlString('');
        }
    });
    $user = User::factory()->create(['email' => $argv[2]]);
    Role::findOrCreate(Utils::getSuperAdminName(), 'web');
    $user->assignRole([Utils::getPanelUserRoleName(), Utils::getSuperAdminName()]);
    User::factory()->create();
    Page::factory()->create();
    $app->make(ConsoleKernel::class)->call('capell:notes-demo', ['--force' => true]);
    auth()->setUser($user);

    if (($argv[4] ?? '') === '--fail-request') {
        $app->make(Router::class)->get('/admin/notes', static fn (): never => throw new RuntimeException('Workbench request failed.'));
    }

    $response = $kernel->handle(Request::create('http://127.0.0.1:8247/admin/notes'));

    echo json_encode([
        'error' => null,
        'status' => $response->getStatusCode(),
        'html' => $response->getContent(),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode([
        'error' => $exception::class . ': ' . $exception->getMessage(),
        'status' => $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500,
        'html' => '',
    ], JSON_THROW_ON_ERROR);
    fwrite(STDERR, (string) $exception . PHP_EOL);
}
