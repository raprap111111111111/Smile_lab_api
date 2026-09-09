<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo([
            'supplier.viewAny',
            'supplier.view',
            'supplier.create',
            'supplier.update',
            'supplier.delete',
            'inventory.stock-in',
        ]);
        Passport::actingAs($this->admin);
    }

    public function test_can_create_and_list_suppliers(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'Henry Schein Dental',
            'contact_person' => 'Jane Doe',
            'email' => 'jane@henryschein.com',
            'phone' => '+123456789',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('suppliers', ['name' => 'Henry Schein Dental']);

        $list = $this->getJson('/api/v1/suppliers');
        $list->assertOk()->assertJsonFragment(['name' => 'Henry Schein Dental']);
    }

    public function test_can_update_and_delete_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Old Supplier Name',
            'contact_person' => 'John',
        ]);

        $updateRes = $this->putJson("/api/v1/suppliers/{$supplier->id}", [
            'name' => 'Updated Supplier Name',
            'contact_person' => 'John Doe',
        ]);

        $updateRes->assertOk();
        $this->assertDatabaseHas('suppliers', ['name' => 'Updated Supplier Name']);

        $deleteRes = $this->deleteJson("/api/v1/suppliers/{$supplier->id}");
        $deleteRes->assertOk();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_stock_in_persists_supplier_to_batch(): void
    {
        $branch = Branch::first();
        $this->admin->branches()->attach($branch->id);

        $item = Item::create([
            'name' => 'Test Anesthetic',
            'sku' => 'TEST-001',
            'category' => 'Surgical & Anesthetics',
            'unit_of_measure' => 'Carpule',
            'minimum_threshold' => 10,
        ]);
        $supplier = Supplier::create(['name' => 'Dental Supply Co']);

        $res = $this->postJson('/api/v1/inventories/stock-in', [
            'branch_id' => $branch->id,
            'item_id' => $item->id,
            'supplier_id' => $supplier->id,
            'quantity' => 25,
            'lot_number' => 'LOT-999',
            'received_at' => now()->toDateString(),
        ]);

        $res->assertCreated();
        $this->assertDatabaseHas('inventory_batches', [
            'item_id' => $item->id,
            'supplier_id' => $supplier->id,
            'lot_number' => 'LOT-999',
            'quantity_remaining' => 25,
        ]);
    }
}
