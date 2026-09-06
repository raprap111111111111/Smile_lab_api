<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\TreatmentConsumable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeleteItemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['item.viewAny', 'item.view', 'item.create', 'item.update', 'item.delete'] as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'api']);
        }

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $adminRole->givePermissionTo(Permission::where('guard_name', 'api')->get());

        $this->admin = User::factory()->create();
        $this->admin->assignRole($adminRole);

        $this->branch = Branch::create([
            'name' => 'Main Clinic',
            'branch_code' => 'MAIN',
            'address' => '123 Smile Ave',
        ]);
    }

    public function test_archiving_item_preserves_ledger_history_and_auto_zeros_stock(): void
    {
        Passport::actingAs($this->admin);

        $item = Item::create([
            'name' => 'Dental Composite Resin',
            'sku' => 'DEN-RES-001',
            'category' => 'Restorative',
            'unit_of_measure' => 'Syringe',
            'minimum_threshold' => 10,
        ]);

        $inventory = Inventory::create([
            'branch_id' => $this->branch->id,
            'item_id' => $item->id,
            'quantity' => 15,
        ]);

        $batch = InventoryBatch::create([
            'branch_id' => $this->branch->id,
            'item_id' => $item->id,
            'lot_number' => 'LOT-1234',
            'expiry_date' => now()->addYear()->toDateString(),
            'quantity_received' => 15,
            'quantity_remaining' => 15,
            'received_at' => now()->toDateString(),
        ]);

        $initialMovement = StockMovement::create([
            'branch_id' => $this->branch->id,
            'item_id' => $item->id,
            'inventory_batch_id' => $batch->id,
            'type' => StockMovementType::STOCK_IN,
            'quantity_delta' => 15,
            'balance_after' => 15,
            'reason' => 'Opening balance',
        ]);

        // Archive the item via DELETE /api/v1/items/{id}
        $response = $this->deleteJson("/api/v1/items/{$item->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // 1. The item is soft-deleted, not hard-deleted
        $this->assertSoftDeleted('items', ['id' => $item->id]);

        // 2. The stock movement ledger is 100% PRESERVED
        $this->assertDatabaseHas('stock_movements', ['id' => $initialMovement->id]);

        // 3. An automatic adjustment movement was recorded to write off remaining stock
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'type' => StockMovementType::ADJUSTMENT->value,
            'quantity_delta' => -15,
            'balance_after' => 0,
        ]);

        // 4. Physical inventory row is updated to 0 quantity
        $inventory->refresh();
        $this->assertSame(0, $inventory->quantity);

        // 5. Default item list excludes the archived item
        $indexResponse = $this->getJson('/api/v1/items');
        $indexResponse->assertOk();
        $ids = collect($indexResponse->json('data.records'))->pluck('id');
        $this->assertFalse($ids->contains($item->id));

        // 6. Querying ?status=archived returns the archived item
        $archivedResponse = $this->getJson('/api/v1/items?status=archived');
        $archivedResponse->assertOk();
        $archivedIds = collect($archivedResponse->json('data.records'))->pluck('id');
        $this->assertTrue($archivedIds->contains($item->id));

        // 7. Restoring the item via POST /api/v1/items/{id}/restore
        $restoreResponse = $this->postJson("/api/v1/items/{$item->id}/restore");
        $restoreResponse->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotSoftDeleted('items', ['id' => $item->id]);
    }

    public function test_deleting_item_referenced_in_treatment_consumables_is_protected(): void
    {
        Passport::actingAs($this->admin);

        $item = Item::create([
            'name' => 'Lidocaine 2%',
            'sku' => 'DEN-ANES-002',
            'category' => 'Surgical & Anesthetics',
            'unit_of_measure' => 'Carpule',
            'minimum_threshold' => 20,
        ]);

        $treatment = Treatment::create([
            'name' => 'Tooth Extraction',
            'price' => 2000,
            'estimated_duration_minutes' => 30,
            'is_active' => true,
        ]);

        TreatmentConsumable::create([
            'treatment_id' => $treatment->id,
            'item_id' => $item->id,
            'quantity_per_use' => 2,
            'is_optional' => false,
        ]);

        $response = $this->deleteJson("/api/v1/items/{$item->id}");

        $response->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('items', ['id' => $item->id]);
        $this->assertNotSoftDeleted('items', ['id' => $item->id]);
    }
}
