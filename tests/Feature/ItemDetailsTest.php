<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cost, storage location and notes used to be collected by the client and
 * silently dropped. These pin down that they now round-trip.
 */
class ItemDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['item.viewAny', 'item.view', 'item.create', 'item.update', 'item.delete'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'api']);
        }

        $role = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $role->givePermissionTo(Permission::where('guard_name', 'api')->get());

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_create_persists_cost_location_and_notes(): void
    {
        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/items', [
            'name' => 'Composite Resin A2',
            'sku' => 'res-a2',
            'category' => 'Restorative',
            'unit_of_measure' => 'Syringe',
            'unit_cost' => 12.5,
            'storage_location' => 'Cabinet B, Shelf 2',
            'notes' => 'Keep below 25°C',
        ])->assertCreated()
            ->assertJsonPath('data.unit_cost', 12.5)
            ->assertJsonPath('data.storage_location', 'Cabinet B, Shelf 2')
            ->assertJsonPath('data.notes', 'Keep below 25°C');

        $this->assertDatabaseHas('items', [
            'sku' => 'RES-A2',
            'storage_location' => 'Cabinet B, Shelf 2',
        ]);
    }

    public function test_cost_defaults_to_zero_when_omitted(): void
    {
        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/items', [
            'name' => 'Cotton Rolls',
            'sku' => 'COT-1',
            'category' => 'Disposables',
            'unit_of_measure' => 'Pack',
        ])->assertCreated()
            ->assertJsonPath('data.unit_cost', 0.0)
            ->assertJsonPath('data.storage_location', null);
    }

    public function test_update_changes_cost_and_clears_location_but_keeps_notes(): void
    {
        Passport::actingAs($this->admin);

        $item = Item::create([
            'name' => 'Lidocaine 2%',
            'sku' => 'LIDO-2',
            'category' => 'Anesthetics',
            'unit_of_measure' => 'Carpule',
            'minimum_threshold' => 10,
            'unit_cost' => 8,
            'storage_location' => 'Fridge 1',
            'notes' => 'Refrigerate',
        ]);

        $this->putJson("/api/v1/items/{$item->id}", [
            'unit_cost' => 9.75,
            'storage_location' => null,
        ])->assertOk()
            ->assertJsonPath('data.unit_cost', 9.75)
            ->assertJsonPath('data.storage_location', null)
            ->assertJsonPath('data.notes', 'Refrigerate');
    }

    public function test_negative_cost_is_rejected(): void
    {
        Passport::actingAs($this->admin);

        $this->postJson('/api/v1/items', [
            'name' => 'Gloves',
            'sku' => 'GLV-1',
            'category' => 'PPE',
            'unit_of_measure' => 'Box',
            'unit_cost' => -1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('unit_cost');
    }
}
