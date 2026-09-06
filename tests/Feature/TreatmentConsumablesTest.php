<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Treatment;
use App\Models\TreatmentConsumable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreatmentConsumablesTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1';

    private Treatment $extraction;
    private Item $anesthetic;
    private Item $cottonRoll;
    private User $dentist;
    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['treatment.view', 'treatment.viewAny', 'treatment.update'] as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'api']);
        }

        $editor = Role::create(['name' => 'dentist', 'guard_name' => 'api']);
        $editor->givePermissionTo(['treatment.view', 'treatment.viewAny', 'treatment.update']);

        $viewer = Role::create(['name' => 'assistant', 'guard_name' => 'api']);
        $viewer->givePermissionTo(['treatment.view', 'treatment.viewAny']);

        $this->dentist = User::factory()->create();
        $this->dentist->assignRole($editor);

        $this->reader = User::factory()->create();
        $this->reader->assignRole($viewer);

        $this->extraction = Treatment::create([
            'name' => 'Extraction', 'price' => 1500,
            'estimated_duration_minutes' => 45, 'is_active' => true,
        ]);

        $this->anesthetic = Item::create([
            'name' => 'Lidocaine 2% carpule', 'sku' => 'ANES-LIDO-2',
            'category' => 'Anesthetics', 'unit_of_measure' => 'carpule',
            'minimum_threshold' => 20,
        ]);

        $this->cottonRoll = Item::create([
            'name' => 'Cotton roll', 'sku' => 'CON-COT-1',
            'category' => 'Hygiene', 'unit_of_measure' => 'piece',
            'minimum_threshold' => 50,
        ]);
    }

    private function url(): string
    {
        return self::BASE . '/treatments/' . $this->extraction->id . '/consumables';
    }

    public function test_a_recipe_can_be_saved_and_read_back(): void
    {
        Passport::actingAs($this->dentist, ['*'], 'api');

        $this->putJson($this->url(), [
            'consumables' => [
                ['item_id' => $this->anesthetic->id, 'quantity_per_use' => 2],
                ['item_id' => $this->cottonRoll->id, 'quantity_per_use' => 5, 'is_optional' => true],
            ],
        ])->assertOk()->assertJsonCount(2, 'data');

        $response = $this->getJson($this->url())->assertOk();

        $this->assertSame(2, $response->json('data.0.quantity_per_use'));
        $this->assertSame('Lidocaine 2% carpule', $response->json('data.0.item.name'));
        $this->assertTrue($response->json('data.1.is_optional'));
    }

    public function test_saving_updates_existing_lines_in_place(): void
    {
        Passport::actingAs($this->dentist, ['*'], 'api');

        $this->putJson($this->url(), [
            'consumables' => [['item_id' => $this->anesthetic->id, 'quantity_per_use' => 2]],
        ])->assertOk();

        $originalId = TreatmentConsumable::sole()->id;

        $this->putJson($this->url(), [
            'consumables' => [['item_id' => $this->anesthetic->id, 'quantity_per_use' => 3]],
        ])->assertOk();

        $line = TreatmentConsumable::sole();

        // Same row, new number — not a delete-and-recreate, which would churn
        // the audit trail on every edit.
        $this->assertSame($originalId, $line->id);
        $this->assertSame(3, $line->quantity_per_use);
    }

    public function test_omitting_a_line_removes_it(): void
    {
        Passport::actingAs($this->dentist, ['*'], 'api');

        $this->putJson($this->url(), [
            'consumables' => [
                ['item_id' => $this->anesthetic->id, 'quantity_per_use' => 2],
                ['item_id' => $this->cottonRoll->id, 'quantity_per_use' => 5],
            ],
        ])->assertOk();

        $this->putJson($this->url(), [
            'consumables' => [['item_id' => $this->anesthetic->id, 'quantity_per_use' => 2]],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame(1, TreatmentConsumable::count());
    }

    public function test_an_empty_list_clears_the_recipe(): void
    {
        Passport::actingAs($this->dentist, ['*'], 'api');

        $this->putJson($this->url(), [
            'consumables' => [['item_id' => $this->anesthetic->id, 'quantity_per_use' => 2]],
        ])->assertOk();

        $this->putJson($this->url(), ['consumables' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertSame(0, TreatmentConsumable::count());
    }

    public function test_the_same_item_twice_is_refused_with_a_usable_message(): void
    {
        Passport::actingAs($this->dentist, ['*'], 'api');

        // unique(treatment_id, item_id) would otherwise fail at the database
        // with something nobody can act on.
        $this->putJson($this->url(), [
            'consumables' => [
                ['item_id' => $this->anesthetic->id, 'quantity_per_use' => 2],
                ['item_id' => $this->anesthetic->id, 'quantity_per_use' => 3],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('consumables.1.item_id');
    }

    public function test_a_zero_quantity_is_refused(): void
    {
        Passport::actingAs($this->dentist, ['*'], 'api');

        $this->putJson($this->url(), [
            'consumables' => [['item_id' => $this->anesthetic->id, 'quantity_per_use' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('consumables.0.quantity_per_use');
    }

    public function test_editing_needs_treatment_update(): void
    {
        Passport::actingAs($this->reader, ['*'], 'api');

        $this->putJson($this->url(), ['consumables' => []])->assertForbidden();

        // Reading is fine — the panel has to render for anyone who can open the
        // treatment.
        $this->getJson($this->url())->assertOk();
    }

    public function test_an_item_used_in_a_recipe_cannot_be_deleted(): void
    {
        Passport::actingAs($this->dentist, ['*'], 'api');

        $this->putJson($this->url(), [
            'consumables' => [['item_id' => $this->anesthetic->id, 'quantity_per_use' => 2]],
        ])->assertOk();

        // restrictOnDelete on treatment_consumables.item_id — deleting the item
        // would silently change what every future extraction deducts.
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->anesthetic->forceDelete();
    }
}
