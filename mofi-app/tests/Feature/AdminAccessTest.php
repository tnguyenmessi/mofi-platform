<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_and_regular_members_cannot_view_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_page_escapes_names_and_never_exposes_passwords(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $user->forceFill(['role' => 'admin'])->save();
        $this->actingAs($user)->get('/admin')->assertOk()->assertSee('Khu vực quản trị')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee($user->password, false);
    }

    public function test_registration_and_profile_cannot_assign_admin_role(): void
    {
        $this->post('/register', ['name' => 'Test', 'email' => 'new@example.com', 'password' => 'test-password-long', 'password_confirmation' => 'test-password-long', 'role' => 'admin'])->assertRedirect('/dashboard');
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->post('/workspace/settings', ['name' => 'Edited', 'role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('user', $user->fresh()->role);
        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_seed_is_explicit_idempotent_and_preserves_password(): void
    {
        config(['demo.enabled' => true, 'demo.admin_password' => 'admin-only-test-password', 'demo.login_password' => 'member-test-password']);
        $this->seed(AdminSeeder::class);
        $admin = User::where('email', 'admin@mofi.local')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('admin-only-test-password', $admin->password));
        $hash = $admin->password;
        $this->seed(AdminSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_seed_refuses_to_promote_existing_member(): void
    {
        config(['demo.enabled' => true, 'demo.admin_password' => 'admin-only-test-password']);
        $user = User::factory()->create(['email' => 'admin@mofi.local']);
        try {
            $this->seed(AdminSeeder::class);
            $this->fail('An existing member must not be promoted by seeding.');
        } catch (\RuntimeException) {
            $this->assertSame('user', $user->fresh()->role);
        }
    }
}
