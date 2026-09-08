<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SecurityAndRbacAuditTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1';

    private Branch $branch1;
    private Branch $branch2;
    private Item $item;
    private User $receptionist;
    private User $admin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $perms = [
            'inventory.viewAny',
            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
            'inventory.stock-in',
            'inventory.stock-out',
            'inventory.adjust',
            'inventory.transfer',
            'role.viewAny',
            'role.view',
            'role.create',
            'role.update',
            'role.delete',
            'user.viewAny',
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
        ];

        foreach ($perms as $perm) {
            Permission::findOrCreate($perm, 'api');
        }

        $superRole = Role::findOrCreate('super-admin', 'api');
        $adminRole = Role::findOrCreate('admin', 'api');
        $recRole = Role::findOrCreate('receptionist', 'api');

        $superRole->givePermissionTo(Permission::all());
        $adminRole->givePermissionTo([
            'inventory.viewAny', 'inventory.view', 'inventory.stock-in',
            'inventory.stock-out', 'inventory.adjust', 'inventory.transfer',
            'role.viewAny', 'user.viewAny', 'user.view',
        ]);
        $recRole->givePermissionTo(['inventory.viewAny', 'inventory.view']);

        $this->branch1 = Branch::create([
            'name' => 'Branch 1', 'branch_code' => 'B1', 'address' => 'Street 1',
        ]);
        $this->branch2 = Branch::create([
            'name' => 'Branch 2', 'branch_code' => 'B2', 'address' => 'Street 2',
        ]);

        $this->item = Item::create([
            'name' => 'Dental Composite A2',
            'sku' => 'COMP-A2',
            'category' => 'Restorative',
            'unit_of_measure' => 'syringe',
            'minimum_threshold' => 10,
        ]);

        $this->superAdmin = User::factory()->create([
            'email' => 'super@smilelab.com',
            'password' => bcrypt('password123'),
        ]);
        $this->superAdmin->assignRole($superRole);

        $this->admin = User::factory()->create([
            'email' => 'admin@smilelab.com',
            'password' => bcrypt('password123'),
        ]);
        $this->admin->assignRole($adminRole);
        $this->admin->branches()->attach([$this->branch1->id]);

        $this->receptionist = User::factory()->create([
            'email' => 'receptionist@smilelab.com',
            'password' => bcrypt('password123'),
        ]);
        $this->receptionist->assignRole($recRole);
        $this->receptionist->branches()->attach([$this->branch1->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(self::BASE . '/inventories');
        $response->assertStatus(401);
    }

    public function test_receptionist_cannot_stock_in_returns_403(): void
    {
        Passport::actingAs($this->receptionist, ['*'], 'api');

        $response = $this->postJson(self::BASE . '/inventories/stock-in', [
            'branch_id' => $this->branch1->id,
            'item_id' => $this->item->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_stock_in_to_branch_they_do_not_belong_to(): void
    {
        Passport::actingAs($this->admin, ['*'], 'api');

        // Admin has inventory.stock-in, but only belongs to branch1, not branch2
        $response = $this->postJson(self::BASE . '/inventories/stock-in', [
            'branch_id' => $this->branch2->id,
            'item_id' => $this->item->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(403);
    }

    public function test_receptionist_cannot_writeoff_stock_returns_403(): void
    {
        Passport::actingAs($this->receptionist, ['*'], 'api');

        $response = $this->postJson(self::BASE . '/inventories/writeoff', [
            'branch_id' => $this->branch1->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'reason' => 'Damaged',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_writeoff_stock_at_unassigned_branch_returns_403(): void
    {
        Passport::actingAs($this->admin, ['*'], 'api');

        // Admin has inventory.adjust, but is only assigned to branch1, not branch2
        $response = $this->postJson(self::BASE . '/inventories/writeoff', [
            'branch_id' => $this->branch2->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
            'reason' => 'Damaged',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_writeoff_stock_at_own_branch(): void
    {
        Passport::actingAs($this->admin, ['*'], 'api');

        // Give initial stock at branch1
        $this->postJson(self::BASE . '/inventories/stock-in', [
            'branch_id' => $this->branch1->id,
            'item_id' => $this->item->id,
            'quantity' => 10,
        ])->assertStatus(201);

        $response = $this->postJson(self::BASE . '/inventories/writeoff', [
            'branch_id' => $this->branch1->id,
            'item_id' => $this->item->id,
            'quantity' => 2,
            'reason' => 'Damaged during delivery',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.inventory.quantity', 8);
    }

    public function test_receptionist_cannot_sync_role_permissions_returns_403(): void
    {
        Passport::actingAs($this->receptionist, ['*'], 'api');

        $response = $this->postJson(self::BASE . '/roles/receptionist/permissions/sync', [
            'permissions' => ['inventory.stock-in', 'role.create'],
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_sync_role_permissions(): void
    {
        Passport::actingAs($this->superAdmin, ['*'], 'api');

        $response = $this->postJson(self::BASE . '/roles/receptionist/permissions/sync', [
            'permissions' => ['inventory.viewAny'],
        ]);

        $response->assertStatus(200);
    }

    public function test_receptionist_cannot_delete_user_returns_403(): void
    {
        Passport::actingAs($this->receptionist, ['*'], 'api');

        $victim = User::factory()->create();

        $response = $this->deleteJson(self::BASE . '/users/' . $victim->id);

        $response->assertStatus(403);
    }

    public function test_receptionist_cannot_delete_role_returns_403(): void
    {
        Passport::actingAs($this->receptionist, ['*'], 'api');

        $testRole = Role::create(['name' => 'custom-role', 'guard_name' => 'api']);

        $response = $this->deleteJson(self::BASE . '/roles/' . $testRole->id);

        $response->assertStatus(403);
    }

    public function test_cannot_delete_system_critical_role(): void
    {
        Passport::actingAs($this->superAdmin, ['*'], 'api');

        $receptionistRole = Role::findByName('receptionist', 'api');

        $response = $this->deleteJson(self::BASE . '/roles/' . $receptionistRole->id);

        $response->assertStatus(422);
    }

    public function test_receptionist_cannot_delete_permission_returns_403(): void
    {
        Passport::actingAs($this->receptionist, ['*'], 'api');

        $testPerm = Permission::create(['name' => 'temp.perm', 'guard_name' => 'api']);

        $response = $this->deleteJson(self::BASE . '/permissions/' . $testPerm->id);

        $response->assertStatus(403);
    }

    public function test_audit_login_and_refresh_token_flow(): void
    {
        // 1. Create personal access client so Passport can issue tokens
        \Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test Personal Client', '--no-interaction' => true]);

        // Login
        $response = $this->postJson(self::BASE . '/auth/login', [
            'email' => 'receptionist@smilelab.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data['access_token']);
        $this->assertNotEmpty($data['refresh_token']);

        // Now attempt to use that refresh token
        $refreshResponse = $this->postJson(self::BASE . '/auth/refresh', [
            'refresh_token' => $data['refresh_token'],
        ]);

        dump('Refresh token response status: ' . $refreshResponse->status());
        dump('Refresh token response body: ' . json_encode($refreshResponse->json()));
    }

    public function test_audit_logout_revokes_token(): void
    {
        \Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test Personal Client', '--no-interaction' => true]);

        $response = $this->postJson(self::BASE . '/auth/login', [
            'email' => 'receptionist@smilelab.com',
            'password' => 'password123',
        ]);
        $token = $response->json('data.access_token');

        // Verify authenticated request works with this token
        $check1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(self::BASE . '/users/me');
        $check1->assertStatus(200);

        // Logout
        $logout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson(self::BASE . '/auth/logout');
        $logout->assertStatus(204);

        // Let's inspect the database row for the token:
        $tokenRows = \DB::table('oauth_access_tokens')->get();
        dump('Token rows in DB after logout: ' . json_encode($tokenRows));

        // Subsequent request with the same token should be rejected (401)
        // Forget in-memory guard cache to simulate a fresh HTTP request
        $this->app['auth']->forgetGuards();

        $check2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(self::BASE . '/users/me');
        dump('Post-logout authenticated request status (with fresh guard): ' . $check2->status());
        $this->assertEquals(401, $check2->status());
    }

    public function test_dynamic_role_change_immediately_updates_permissions(): void
    {
        \Artisan::call('passport:client', ['--personal' => true, '--name' => 'Test Personal Client', '--no-interaction' => true]);

        // Receptionist logs in
        $response = $this->postJson(self::BASE . '/auth/login', [
            'email' => 'receptionist@smilelab.com',
            'password' => 'password123',
        ]);
        $token = $response->json('data.access_token');

        // Receptionist currently has inventory.viewAny
        $check1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(self::BASE . '/inventories');
        $check1->assertStatus(200);

        // Now Admin revokes inventory.viewAny from receptionist role
        $role = Role::findByName('receptionist', 'api');
        $role->revokePermissionTo('inventory.viewAny');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Forget test guard cache to simulate next HTTP request over network
        $this->app['auth']->forgetGuards();

        // Receptionist sends next request with their existing token
        $check2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(self::BASE . '/inventories');

        dump('Post-role-change request status: ' . $check2->status());
        $this->assertEquals(403, $check2->status());
    }
}
