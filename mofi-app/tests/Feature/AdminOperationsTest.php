<?php

namespace Tests\Feature;

use App\Models\Instrument;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_inertia_logout_performs_full_navigation_to_public_home(): void
    {
        $member = User::factory()->create();
        $this->actingAs($member)->withHeader('X-Inertia', 'true')->post('/logout')
            ->assertStatus(409)->assertHeader('X-Inertia-Location', '/');
        $this->assertGuest();
    }

    public function test_plain_logout_redirects_to_public_home(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_lock_unlock_are_persisted_and_repeated_submission_is_safe(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $url = '/admin/users/'.$member->id.'/toggle';
        $this->actingAs($admin)->post($url, ['active' => 0])->assertRedirect();
        $this->assertFalse($member->fresh()->active);
        $this->post($url, ['active' => 0])->assertRedirect();
        $this->assertDatabaseCount('admin_audit_logs', 1);
        $this->assertDatabaseHas('admin_audit_logs', ['admin_id' => $admin->id, 'action' => 'user.locked', 'target_id' => $member->id]);
        $this->post($url, ['active' => 1])->assertRedirect();
        $this->assertTrue($member->fresh()->active);
        $this->assertDatabaseCount('admin_audit_logs', 2);
        $this->get('/admin')->assertOk()->assertSee('Nhật ký quản trị')->assertSee('Mở khóa tài khoản');
    }

    public function test_admin_can_filter_users_and_instruments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['name' => 'Người cần tìm', 'email' => 'find@example.com']);
        $member->forceFill(['active' => false])->save();
        $instrument = Instrument::factory()->create(['symbol' => 'FIND']);

        $this->actingAs($admin)->get('/admin?user_search=find&active=0')
            ->assertOk()->assertSee('find@example.com')->assertDontSee($admin->email);
        $this->get('/admin?instrument_search=FIND')
            ->assertOk()->assertSee('FIND');
    }

    public function test_locked_member_cannot_login_or_use_existing_session(): void
    {
        $member = User::factory()->create(['active' => false, 'password' => 'locked-test-password']);
        $this->post('/login', ['email' => $member->email, 'password' => 'locked-test-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($member)->get('/dashboard')->assertForbidden();
        $this->assertGuest();
        $this->actingAs($member)->post('/workspace/settings', ['name' => 'Changed'])->assertForbidden();
        $this->assertNotSame('Changed', $member->fresh()->name);
    }

    public function test_guest_member_and_admin_self_lock_are_denied(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $instrument = Instrument::factory()->create();
        $this->post('/admin/users/'.$member->id.'/toggle', ['active' => 0])->assertRedirect('/login');
        $this->post('/admin/instruments/'.$instrument->id.'/toggle', ['tradable' => 0])->assertRedirect('/login');
        $this->actingAs($member)->post('/admin/users/'.$admin->id.'/toggle', ['active' => 0])->assertForbidden();
        $this->post('/admin/instruments/'.$instrument->id.'/toggle', ['tradable' => 0])->assertForbidden();
        $this->actingAs($admin)->post('/admin/users/'.$admin->id.'/toggle', ['active' => 0])->assertStatus(422);
        $this->assertTrue($admin->fresh()->active);
        $this->assertDatabaseCount('admin_audit_logs', 0);
    }

    public function test_instrument_status_is_persisted_and_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $instrument = Instrument::factory()->create(['tradable' => true]);
        $url = '/admin/instruments/'.$instrument->id.'/toggle';
        $marketKey = 'mofi.demo.market.v2.'.config('demo.simulation_date');
        Cache::put($marketKey, ['stale' => true], 120);
        $this->actingAs($admin)->post($url, ['tradable' => 'invalid'])->assertSessionHasErrors('tradable');
        $this->assertTrue($instrument->fresh()->tradable);
        $this->assertTrue(Cache::has($marketKey));
        $this->post($url, ['tradable' => 0])->assertRedirect();
        $this->assertFalse($instrument->fresh()->tradable);
        $this->assertFalse(Cache::has($marketKey));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'instrument.disabled', 'target_id' => $instrument->id]);
        $this->post($url, ['tradable' => 1])->assertRedirect();
        $this->assertTrue($instrument->fresh()->tradable);
    }

    public function test_audit_failure_rolls_back_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        DB::statement("CREATE TRIGGER fail_audit BEFORE INSERT ON admin_audit_logs BEGIN SELECT RAISE(ABORT, 'test audit failure'); END");
        try {
            $this->actingAs($admin)->post('/admin/users/'.$member->id.'/toggle', ['active' => 0])->assertStatus(500);
            $this->assertTrue($member->fresh()->active);
            $this->assertDatabaseCount('admin_audit_logs', 0);
        } finally {
            DB::statement('DROP TRIGGER fail_audit');
        }
    }
}
