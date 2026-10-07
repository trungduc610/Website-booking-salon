<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function customer(array $overrides = []): User
    {
        return User::create(array_merge([
            'full_name' => 'Nguyễn An',
            'email' => 'an@example.test',
            'password_hash' => Hash::make('Secret123!'),
        ], $overrides));
    }

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Nguyễn & An',
            'email' => 'AN@example.test',
            'password' => ' Secret123! ',
            'password_confirmation' => ' Secret123! ',
        ], $overrides);
    }

    public function test_auth_pages_render_and_include_csrf_fields(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="_token"', false);
        $this->get('/register')->assertOk()->assertSee('Tạo tài khoản');
    }

    public function test_registration_creates_customer_and_ignores_posted_privileges(): void
    {
        $this->post('/register', $this->registration(['is_active' => false, 'role' => 'PLATFORM_ADMIN']))
            ->assertRedirect('/dashboard');
        $user = User::where('email', 'an@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check(' Secret123! ', $user->password_hash));
        $this->assertSame('Nguyễn & An', $user->full_name);
        $this->assertTrue($user->hasRole('CUSTOMER'));
        $this->assertFalse($user->hasRole('PLATFORM_ADMIN'));
        $this->assertDatabaseHas('customer_profiles', ['user_id' => $user->id]);
    }

    public function test_soft_deleted_email_is_still_reserved(): void
    {
        $this->customer()->delete();
        $this->post('/register', $this->registration())->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $this->customer(['phone' => '0901234567']);
        $this->post('/register', $this->registration(['email' => 'new@example.test', 'phone' => '0901234567']))
            ->assertSessionHasErrors('phone');
    }

    public function test_invalid_and_array_inputs_are_validation_errors(): void
    {
        $this->post('/register', $this->registration(['email' => ['invalid'], 'full_name' => ['invalid']]))
            ->assertSessionHasErrors(['email', 'full_name']);
        $this->post('/login', ['email' => ['invalid'], 'password' => ['invalid']])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_bcrypt_password_limit_is_enforced_in_bytes(): void
    {
        $password = str_repeat('ế', 30);
        $this->post('/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))
            ->assertSessionHasErrors('password');
    }

    public function test_legacy_bcrypt_hash_can_log_in(): void
    {
        $user = $this->customer(['password_hash' => password_hash('Legacy123!', PASSWORD_BCRYPT, ['cost' => 12])]);
        $this->post('/login', ['email' => 'AN@example.test', 'password' => 'Legacy123!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $this->customer()->forceFill(['is_active' => false])->save();
        $this->post('/login', ['email' => 'an@example.test', 'password' => 'Secret123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabling_an_existing_session_revokes_access(): void
    {
        $user = $this->customer();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_throttle_blocks_correct_password_after_five_failures(): void
    {
        $this->customer();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'an@example.test', 'password' => 'incorrect'])
                ->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'an@example.test', 'password' => 'Secret123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_requires_post_and_invalidates_authentication(): void
    {
        $this->actingAs($this->customer());
        $this->get('/logout')->assertStatus(405);
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_csrf_is_enforced_outside_testing_environment(): void
    {
        // Laravel intentionally bypasses CSRF while running unit tests.
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => 'an@example.test', 'password' => 'Secret123!'])
            ->assertStatus(419);
    }

    public function test_customer_cannot_visit_admin_dashboard(): void
    {
        $this->actingAs($this->customer())->get('/admin/dashboard')->assertForbidden();
    }

    public function test_expired_admin_role_does_not_authorize_access(): void
    {
        $user = $this->customer();
        $user->roles()->attach(Role::where('code', 'PLATFORM_ADMIN')->sole()->id, ['expires_at' => now()->subMinute()]);
        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_name_is_escaped_in_blade(): void
    {
        $user = $this->customer(['full_name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get('/dashboard')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
