<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests;

use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\Dusk\TestCase as BaseTestCase;
use Override;
use PDO;
use RuntimeException;

abstract class DuskTestCase extends BaseTestCase
{
    use WithWorkbench;

    protected static $baseServePort = 8089;

    /**
     * Reset the seeded admin user's password before each test so suites that
     * mutate it can run repeatedly without re-seeding the workbench DB.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $pdo = $this->workbenchPdo();
        $pdo->prepare('UPDATE users SET password = :password WHERE email = :email')
            ->execute([
                ':password' => Hash::make('password'),
                ':email' => 'admin@laravel.com',
            ]);
    }

    /**
     * Login through the Nova form (used by tests that exercise the rendered DOM).
     */
    final protected function loginToNova(Browser $browser): Browser
    {
        $browser->visit('/nova');

        if ($browser->element('input[name="email"]')) {
            $browser->type('email', 'admin@laravel.com')
                ->type('password', 'password')
                ->press('Log In')
                ->waitForText('Get Started');
        }

        return $browser;
    }

    /**
     * Authenticate against Nova via the running Dusk server using a fetch() call
     * from the browser, since the Vue bundles are not always rendered in the
     * testbench-dusk skeleton. Returns once the session cookie is set.
     */
    final protected function loginToNovaViaFetch(Browser $browser, string $email = 'admin@laravel.com', string $password = 'password'): void
    {
        $browser->visit('/nova/login');

        $script = <<<JS
            const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
            const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : '';
            return fetch('/nova/login', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({email: {$this->jsString($email)}, password: {$this->jsString($password)}}),
            }).then(r => ({status: r.status}));
            JS;

        $result = $browser->driver->executeScript($script);

        if (! \in_array($result['status'] ?? 0, [200, 204, 302], true)) {
            throw new RuntimeException('Nova login through Dusk server failed with status '.($result['status'] ?? 'null'));
        }
    }

    /**
     * Open a PDO connection to the workbench's SQLite database that the Dusk
     * server reads from. Used to verify side-effects of actions executed
     * through the running browser.
     */
    final protected function workbenchPdo(): PDO
    {
        $path = realpath(__DIR__.'/../vendor/orchestra/testbench-dusk/laravel/database/database.sqlite');

        if ($path === false) {
            throw new RuntimeException('Workbench SQLite database is missing — run `vendor/bin/testbench workbench:build` first.');
        }

        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:WLZE9KQpmVeMygRQj/vi16NGiyks4BWnYR1elIKAaiI=');
    }

    private function jsString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
