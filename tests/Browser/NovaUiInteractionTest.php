<?php

declare(strict_types=1);

namespace Opscale\Actions\Tests\Browser;

use Laravel\Dusk\Browser;
use Opscale\Actions\Tests\DuskTestCase;
use PDO;
use PHPUnit\Framework\Attributes\Test;

/**
 * Drives the full Nova Action pipeline through a real Chrome instance.
 *
 * The flow:
 *   1. The browser hits /nova/login and gets the XSRF cookie + session.
 *   2. It posts the login form via fetch() to authenticate.
 *   3. It posts the Nova Action endpoint (/nova-api/users/action) to invoke
 *      the workbench's `ResetPassword` action — i.e. the same Action contract
 *      this package exposes everywhere else, but routed through the Nova
 *      ActionController → ActionRequest → handleRequest → asNovaAction stack.
 *   4. The test reaches into the workbench's SQLite database to assert the
 *      admin user's password hash actually changed, proving the action's
 *      side-effect ran end-to-end through the running browser.
 *
 * This is intentionally not a DOM-driven test: the testbench-dusk skeleton
 * does not always serve the published Vue bundles back to the browser, so
 * the rendered Nova form may be empty. fetch() runs in the browser context
 * regardless of Vue, so the same browser session and cookie jar drive the
 * Nova HTTP API directly — that's still "execute the action as a Nova
 * action" because every server-side hop (auth, CSRF, ActionController,
 * ActionRequest, our NovaActionAdapter, and Nova's DispatchAction) fires.
 */
final class NovaUiInteractionTest extends DuskTestCase
{
    #[Test]
    final public function the_browser_renders_the_server_side_inertia_payload_for_nova_login(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/nova/login');

            $source = $browser->driver->getPageSource();

            $this->assertStringContainsString('id="app"', $source);
            $this->assertStringContainsString('Nova.Login', $source);
            $this->assertStringContainsString('&quot;uriKey&quot;:&quot;users&quot;', $source);
        });
    }

    #[Test]
    final public function the_browser_can_authenticate_against_the_running_nova_app(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginToNovaViaFetch($browser);

            $whoami = $browser->driver->executeScript(<<<'JS'
                return fetch('/nova-api/users', {
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                }).then(async r => ({status: r.status, body: (await r.text()).slice(0, 200)}));
                JS);

            $this->assertContains(
                $whoami['status'] ?? 0,
                [200, 302],
                'whoami got '.($whoami['status'] ?? 'null').': '.($whoami['body'] ?? '')
            );
        });
    }

    #[Test]
    final public function the_reset_password_action_runs_end_to_end_through_the_nova_action_endpoint(): void
    {
        $newPassword = 'browser-driven-'.bin2hex(random_bytes(4));

        $pdo = $this->workbenchPdo();
        $admin = $pdo->query("SELECT id, password FROM users WHERE email = 'admin@laravel.com'")
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($admin, 'Workbench admin user is missing — re-run testbench workbench:build.');
        $beforeHash = $admin['password'];
        $adminId = (int) $admin['id'];

        $this->browse(function (Browser $browser) use ($newPassword, $adminId): void {
            $this->loginToNovaViaFetch($browser);

            $script = <<<JS
                const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
                const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : '';
                return fetch('/nova-api/users/action?action=reset-password', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        resources: '{$adminId}',
                        email: 'admin@laravel.com',
                        password: '{$newPassword}',
                        password_confirmation: '{$newPassword}',
                    }),
                }).then(async r => ({status: r.status, body: await r.text()}));
                JS;

            $result = $browser->driver->executeScript($script);

            $this->assertSame(
                200,
                $result['status'] ?? null,
                'Nova action endpoint did not accept the request. Body: '.($result['body'] ?? '')
            );
        });

        $afterHash = $pdo->query("SELECT password FROM users WHERE id = {$adminId}")
            ->fetchColumn();

        $this->assertNotSame(
            $beforeHash,
            $afterHash,
            'The admin password hash did not change — the Nova action did not reach the database.'
        );
        $this->assertTrue(
            password_verify($newPassword, (string) $afterHash),
            'The stored password hash does not verify against the new password the Nova action submitted.'
        );
    }

    #[Test]
    final public function the_reset_password_action_is_rejected_when_required_fields_are_missing(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->loginToNovaViaFetch($browser);

            $pdo = $this->workbenchPdo();
            $adminId = (int) $pdo->query("SELECT id FROM users WHERE email = 'admin@laravel.com'")
                ->fetchColumn();

            $script = <<<JS
                const xsrf = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
                const token = xsrf ? decodeURIComponent(xsrf.split('=')[1]) : '';
                return fetch('/nova-api/users/action?action=reset-password', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        resources: '{$adminId}',
                        email: 'admin@laravel.com',
                    }),
                }).then(async r => ({status: r.status, body: await r.text()}));
                JS;

            $result = $browser->driver->executeScript($script);

            $this->assertSame(422, $result['status'] ?? null);
            $this->assertStringContainsString('password', (string) ($result['body'] ?? ''));
        });
    }
}
