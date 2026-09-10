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
            'item.create',
            'item.update',
            'item.view',
            'item.viewAny',
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

    public function test_can_create_item_with_supplier_id_persisted(): void
    {
        $supplier = Supplier::create([
            'name' => 'Direct Dental Supply',
            'contact_person' => 'Bob Smith',
        ]);

        $response = $this->postJson('/api/v1/items', [
            'name' => 'Composite Syringe A2',
            'sku' => 'DEN-RES-A2',
            'category' => 'Restorative',
            'unit_of_measure' => 'Syringe',
            'minimum_threshold' => 10,
            'supplier_id' => $supplier->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('items', [
            'sku' => 'DEN-RES-A2',
            'supplier_id' => $supplier->id,
        ]);

        $response->assertJsonPath('data.supplier_id', $supplier->id);
        $response->assertJsonPath('data.supplier.name', 'Direct Dental Supply');
    }

    public function test_can_update_item_to_unassign_supplier_to_null(): void
    {
        $supplier = Supplier::create([
            'name' => 'Supplier To Be Removed',
        ]);

        $item = Item::create([
            'name' => 'Disposable Saliva Ejector',
            'sku' => 'DEN-DISP-001',
            'category' => 'General Supplies',
            'unit_of_measure' => 'Pack',
            'minimum_threshold' => 5,
            'supplier_id' => $supplier->id,
        ]);

        $this->assertEquals($supplier->id, $item->supplier_id);

        $response = $this->putJson("/api/v1/items/{$item->id}", [
            'name' => 'Disposable Saliva Ejector Updated',
            'supplier_id' => null,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'supplier_id' => null,
        ]);
        $response->assertJsonPath('data.supplier_id', null);
        $response->assertJsonPath('data.supplier', null);
    }

    public function test_partial_item_update_preserves_existing_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Persistent Dental Supplier',
        ]);

        $item = Item::create([
            'name' => 'Dental Mirror Size 4',
            'sku' => 'DEN-MIR-004',
            'category' => 'General Supplies',
            'unit_of_measure' => 'Piece',
            'minimum_threshold' => 10,
            'supplier_id' => $supplier->id,
        ]);

        // Partial update: updating only the item's name, omitting supplier_id entirely
        $response = $this->putJson("/api/v1/items/{$item->id}", [
            'name' => 'Dental Mirror Size 4 (Rhodium Coated)',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'name' => 'Dental Mirror Size 4 (Rhodium Coated)',
            'supplier_id' => $supplier->id, // Existing supplier MUST NOT be wiped out
        ]);
    }

    public function test_cannot_create_supplier_without_required_name(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'email' => 'contact@supplier.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_create_supplier_with_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'name' => 'Valid Supplier Name',
            'email' => 'not-an-email-address',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}

