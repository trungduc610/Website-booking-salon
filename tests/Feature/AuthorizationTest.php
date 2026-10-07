<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_roles_are_scoped_and_expiry_is_respected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::create(['full_name' => 'Manager', 'email' => 'manager@example.test', 'password_hash' => Hash::make('Secret123!')]);
        $owner = DB::table('business_owner_profiles')->insertGetId(['user_id' => $user->id]);
        $business = DB::table('businesses')->insertGetId(['owner_id' => $owner, 'name' => 'Salon', 'slug' => 'salon']);
        $one = Branch::create(['business_id' => $business, 'name' => 'One']);
        $two = Branch::create(['business_id' => $business, 'name' => 'Two']);
        $role = Role::where('code', 'BRANCH_MANAGER')->sole();
        $user->roles()->attach($role->id, ['business_id' => $business, 'branch_id' => $one->id, 'expires_at' => now()->addDay()]);
        $this->assertTrue(Gate::forUser($user)->allows('update', $one));
        $this->assertFalse(Gate::forUser($user)->allows('view', $two));
        $this->travel(2)->days();
        $this->assertFalse(Gate::forUser($user)->allows('view', $one));
        $this->travelBack();
    }

    public function test_duplicate_global_role_is_blocked_by_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::create(['full_name' => 'Customer', 'email' => 'c@example.test', 'password_hash' => Hash::make('Secret123!')]);
        $role = Role::where('code', 'CUSTOMER')->sole();
        $user->roles()->attach($role->id);
        $this->expectException(QueryException::class);
        $user->roles()->attach($role->id);
    }

    public function test_missing_customer_role_rolls_back_registration(): void
    {
        $this->withoutExceptionHandling();
        try {
            app(\App\Services\RegisterCustomer::class)->handle([
                'full_name' => 'Customer', 'email' => 'c@example.test', 'password' => 'Secret123!',
            ]);
            $this->fail('Missing role must fail closed.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('customer_profiles', 0);
        }
    }
}
