<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('href="http://study-tracker.test/favicon.svg"', false)
            ->assertSee('aria-label="Study Tracker"', false)
            ->assertSee('Acesse seu painel')
            ->assertSee('Entrar')
            ->assertSee('study-tracker-theme')
            ->assertSee('themeToggle()', false)
            ->assertSee('name="remember" value="1"', false)
            ->assertSee('Manter conectado')
            ->assertDontSee('Forgot your password?');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_remember_cookie_restores_login_without_the_original_session(): void
    {
        config(['session.secure' => true]);
        $user = User::factory()->create();
        $cookieName = Auth::guard('web')->getRecallerName();
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

        $response->assertRedirect(route('dashboard', absolute: false))->assertCookieNotExpired($cookieName);
        $this->assertAuthenticatedAs($user);
        $cookie = $response->getCookie($cookieName, false);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(time() + 7200, $cookie->getExpiresTime());
        $this->assertNotEmpty($user->fresh()->getRememberToken());
        $this->assertNotSame($response->getCookie($cookieName)->getValue(), $cookie->getValue());

        $this->app['session.store']->invalidate();
        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookieName, $cookie->getValue())->get('/login')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Auth::guard('web')->viaRemember());
    }

    public function test_login_regenerates_the_session_and_preserves_the_intended_redirect(): void
    {
        $user = User::factory()->create();
        $this->get(route('dashboard.stats'))->assertRedirect(route('login'));
        $sessionId = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
            ->assertRedirect(route('dashboard.stats'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
    }

    public function test_logout_invalidates_the_session_and_revokes_the_remember_cookie(): void
    {
        $user = User::factory()->create();
        $cookieName = Auth::guard('web')->getRecallerName();
        $login = $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1']);
        $cookie = $login->getCookie($cookieName, false);
        $rememberToken = $user->fresh()->getRememberToken();
        $sessionId = session()->getId();
        $csrfToken = session()->token();

        $this->withUnencryptedCookie($cookieName, $cookie->getValue())
            ->withSession(['private_marker' => 'sensitive'])->post('/logout')
            ->assertRedirect('/')->assertCookieExpired($cookieName)->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertNotSame($csrfToken, session()->token());
        $this->assertNotSame($rememberToken, $user->fresh()->getRememberToken());

        $this->app['session.store']->invalidate();
        Auth::forgetGuards();
        $this->withUnencryptedCookie($cookieName, $cookie->getValue())->get(route('dashboard.stats'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_issue_a_remember_cookie(): void
    {
        $user = User::factory()->create(['remember_token' => null]);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password', 'remember' => '1'])
            ->assertSessionHasErrors('email')->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertGuest();
        $this->assertEmpty($user->fresh()->getRememberToken());
    }

    public function test_remembered_login_is_blocked_after_five_failures_and_recovers_after_the_lockout(): void
    {
        $user = User::factory()->create();
        Event::fake([Lockout::class]);
        $this->freezeTime();
        $credentials = ['email' => $user->email, 'password' => 'wrong-password', 'remember' => '1'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', $credentials)->assertSessionHasErrors(['email' => trans('auth.failed')]);
        }

        $credentials['password'] = 'password';
        $this->post('/login', $credentials)
            ->assertSessionHasErrors(['email' => trans('auth.throttle', ['seconds' => 60, 'minutes' => 1])])
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertGuest();
        Event::assertDispatched(Lockout::class);

        $this->travel(61)->seconds();
        $this->post('/login', $credentials)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->travelBack();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
