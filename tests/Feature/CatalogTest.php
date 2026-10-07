<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Branch $branch;
    private Branch $foreignBranch;
    private ServiceCategory $category;
    private ServiceCategory $foreignCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->owner = User::create(['full_name' => 'Owner', 'email' => 'owner@example.test', 'password_hash' => Hash::make('Secret123!')]);
        $profile = DB::table('business_owner_profiles')->insertGetId(['user_id' => $this->owner->id]);
        $business = DB::table('businesses')->insertGetId(['owner_id' => $profile, 'name' => 'My salon', 'slug' => 'mine']);
        $other = DB::table('businesses')->insertGetId(['owner_id' => $profile, 'name' => 'Other salon', 'slug' => 'other']);
        $this->branch = Branch::create(['business_id' => $business, 'name' => 'Authorized branch']);
        $this->foreignBranch = Branch::create(['business_id' => $other, 'name' => 'Hidden branch']);
        $this->category = ServiceCategory::create(['business_id' => $business, 'name' => 'Hair', 'slug' => 'hair']);
        $this->foreignCategory = ServiceCategory::create(['business_id' => $other, 'name' => 'Other', 'slug' => 'other']);
        $this->owner->roles()->attach(Role::where('code', 'BUSINESS_OWNER')->sole()->id, ['business_id' => $business]);
        $this->actingAs($this->owner);
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['name' => 'Chăm sóc tóc', 'description' => 'Mô tả', 'category_id' => $this->category->id,
            'price' => '123456.78', 'duration_minutes' => 60, 'status' => 'ACTIVE', 'bookable' => 1], $extra);
    }

    private function createService(Branch $branch, ServiceCategory $category): Service
    {
        $service = new Service($this->payload(['category_id' => $category->id]));
        $service->business_id = $branch->business_id;
        $branch->services()->save($service);
        return $service;
    }

    public function test_branch_list_hides_ungranted_businesses(): void
    {
        $this->get('/salon/branches')->assertOk()->assertSee('Authorized branch')->assertDontSee('Hidden branch');
    }

    public function test_creation_derives_tenant_from_authorized_route(): void
    {
        $this->post(route('catalog.store', $this->branch), $this->payload(['business_id' => $this->foreignBranch->business_id, 'branch_id' => $this->foreignBranch->id]))
            ->assertRedirect(route('catalog.index', $this->branch));
        $service = Service::sole();
        $this->assertSame($this->branch->id, $service->branch_id);
        $this->assertSame($this->branch->business_id, $service->business_id);
        $this->assertSame('123456.78', $service->price);
    }

    public function test_cross_tenant_creation_and_category_are_rejected(): void
    {
        $this->post(route('catalog.store', $this->foreignBranch), $this->payload())->assertForbidden();
        $this->post(route('catalog.store', $this->branch), $this->payload(['category_id' => $this->foreignCategory->id]))
            ->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('services', 0);
    }

    public function test_nested_service_id_cannot_escape_branch_scope(): void
    {
        $service = $this->createService($this->foreignBranch, $this->foreignCategory);
        $this->put(route('catalog.update', [$this->branch, $service]), $this->payload())->assertNotFound();
        $this->assertSame($this->foreignBranch->id, $service->fresh()->branch_id);
    }

    public function test_update_rejects_bad_price_and_duration_then_allows_inactive(): void
    {
        $service = $this->createService($this->branch, $this->category);
        $this->put(route('catalog.update', [$this->branch, $service]), $this->payload(['price' => '-1', 'duration_minutes' => 601]))
            ->assertSessionHasErrors(['price', 'duration_minutes']);
        $this->put(route('catalog.update', [$this->branch, $service]), $this->payload(['status' => 'INACTIVE', 'bookable' => 0]))
            ->assertRedirect();
        $this->assertSame('INACTIVE', $service->fresh()->status);
        $this->assertFalse($service->fresh()->bookable);
    }

    public function test_catalog_and_edit_views_escape_names(): void
    {
        $service = $this->createService($this->branch, $this->category);
        $service->update(['name' => '<script>alert(1)</script>']);
        $this->get(route('catalog.index', $this->branch))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('catalog.edit', [$this->branch, $service]))->assertOk();
        $this->get(route('catalog.create', $this->branch))->assertOk();
    }

    public function test_staff_can_read_but_not_modify_catalog(): void
    {
        $this->owner->roles()->detach();
        $this->owner->roles()->attach(Role::where('code', 'STAFF')->sole()->id, ['business_id' => $this->branch->business_id, 'branch_id' => $this->branch->id]);
        $this->get(route('catalog.index', $this->branch))->assertOk();
        $this->post(route('catalog.store', $this->branch), $this->payload())->assertForbidden();
    }

    public function test_provision_command_creates_pending_salon_without_default_password(): void
    {
        $this->artisan('glowbook:create-salon', ['email' => $this->owner->email, '--name' => 'New salon'])->assertSuccessful();
        $this->assertDatabaseHas('businesses', ['name' => 'New salon', 'status' => 'PENDING']);
        $this->assertDatabaseCount('users', 1);
    }
}
