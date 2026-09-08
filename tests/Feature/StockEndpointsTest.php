<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The four stock verbs at the HTTP surface, plus the ledger read.
 *
 * These permissions (`inventory.stock-in`, `.stock-out`, `.adjust`,
 * `.transfer`) were seeded long ago and granted to admin, but had no code
 * behind them at all. The authorization tests here are what make them real.
 */
class StockEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-08-19 08:00:00';
    private const BASE = '/api/v1';

    private Branch $main;
    private Branch $satellite;
    private Item $item;
    private User $manager;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::NOW));

        foreach ([
            'inventory.viewAny',
            'inventory.stock-in',
            'inventory.stock-out',
            'inventory.adjust',
            'inventory.transfer',
        ] as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'api']);
        }

        $stockKeeper = Role::create(['name' => 'stock-keeper', 'guard_name' => 'api']);
        $stockKeeper->givePermissionTo(Permission::where('guard_name', 'api')->get());

        $readOnly = Role::create(['name' => 'stock-viewer', 'guard_name' => 'api']);
        $readOnly->givePermissionTo('inventory.viewAny');

        $this->main = Branch::create([
            'name' => 'Main', 'branch_code' => 'MAIN', 'address' => '1 Test St',
        ]);
        $this->satellite = Branch::create([
            'name' => 'Satellite', 'branch_code' => 'SAT', 'address' => '2 Test St',
        ]);

        $this->manager = User::factory()->create();
        $this->manager->assignRole($stockKeeper);

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($readOnly);

        // Branch membership is now part of authorization, not just a filter.
        // Without these the permissions alone get you a 403.
        $this->manager->branches()->attach([$this->main->id, $this->satellite->id]);
        $this->viewer->branches()->attach([$this->main->id, $this->satellite->id]);

        $this->item = Item::create([
            'name' => 'Lidocaine 2% carpule',
            'sku' => 'ANES-LIDO-2',
            'category' => 'Anesthetics',
            'unit_of_measure' => 'carpule',
            'minimum_threshold' => 20,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Stock in ──────────────────────────────────────

    public function test_stock_in_creates_a_batch_and_returns_the_refreshed_row(): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');

        $response = $this->postJson(self::BASE . '/inventories/stock-in', [
            'branch_id'   => $this->main->id,
            'item_id'     => $this->item->id,
            'quantity'    => 50,
            'lot_number'  => 'A1',
            'expiry_date' => '2027-02-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.balance_after', 50)
            ->assertJsonPath('data.shortfall', 0)
            ->assertJsonPath('data.inventory.quantity', 50)
            ->assertJsonPath('data.movements.0.type', 'stock_in')
            ->assertJsonPath('data.movements.0.batch.lot_number', 'A1');

        $this->assertSame(50, InventoryBatch::sole()->quantity_remaining);
    }

    public function test_stock_in_rejects_an_expiry_already_past(): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/stock-in', [
            'branch_id'   => $this->main->id,
            'item_id'     => $this->item->id,
            'quantity'    => 10,
            'expiry_date' => '2020-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors('expiry_date');
    }

    public function test_stock_in_records_who_did_it(): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/stock-in', [
            'branch_id' => $this->main->id,
            'item_id'   => $this->item->id,
            'quantity'  => 5,
        ])->assertCreated();

        $this->assertSame($this->manager->id, StockMovement::sole()->performed_by);
    }

    // ── Usage ─────────────────────────────────────────

    public function test_usage_draws_stock_down(): void
    {
        $this->seedStock(30, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/usage', [
            'branch_id' => $this->main->id,
            'item_id'   => $this->item->id,
            'quantity'  => 12,
            'reason'    => 'Spilled a tray.',
        ])->assertOk()
            ->assertJsonPath('data.balance_after', 18)
            ->assertJsonPath('data.shortfall', 0);
    }

    public function test_usage_beyond_stock_succeeds_and_reports_the_shortfall(): void
    {
        $this->seedStock(4, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        $response = $this->postJson(self::BASE . '/inventories/usage', [
            'branch_id' => $this->main->id,
            'item_id'   => $this->item->id,
            'quantity'  => 6,
        ]);

        // Deliberately 200, not 422: the supplies are already gone. Refusing
        // would leave the ledger disagreeing with the cupboard.
        $response->assertOk()
            ->assertJsonPath('data.shortfall', 2)
            ->assertJsonPath('data.balance_after', -2);

        $this->assertStringContainsString('short by 2', $response->json('message'));
    }

    // ── Adjust ────────────────────────────────────────

    public function test_adjusting_down_writes_the_difference(): void
    {
        $this->seedStock(30, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/adjust', [
            'branch_id'        => $this->main->id,
            'item_id'          => $this->item->id,
            'counted_quantity' => 27,
            'reason'           => 'Physical count on 19 Aug.',
        ])->assertOk()->assertJsonPath('data.balance_after', 27);

        $adjustment = StockMovement::where('type', 'adjustment')->sole();
        $this->assertSame(-3, $adjustment->quantity_delta);
        $this->assertSame('Physical count on 19 Aug.', $adjustment->reason);
    }

    public function test_an_adjustment_without_a_reason_is_refused(): void
    {
        $this->seedStock(30, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/adjust', [
            'branch_id'        => $this->main->id,
            'item_id'          => $this->item->id,
            'counted_quantity' => 27,
        ])->assertStatus(422)->assertJsonValidationErrors('reason');
    }

    public function test_a_matching_count_writes_nothing(): void
    {
        $this->seedStock(30, '2027-01-01', 'A1');
        $before = StockMovement::count();
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/adjust', [
            'branch_id'        => $this->main->id,
            'item_id'          => $this->item->id,
            'counted_quantity' => 30,
            'reason'           => 'Count matched.',
        ])->assertOk()->assertJsonPath('data.movements', []);

        $this->assertSame($before, StockMovement::count());
    }

    public function test_adjust_accepts_quantity_alias_for_counted_quantity(): void
    {
        $this->seedStock(30, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/adjust', [
            'branch_id' => $this->main->id,
            'item_id'   => $this->item->id,
            'quantity'  => 25,
            'reason'    => 'Count adjustment using quantity field.',
        ])->assertOk()->assertJsonPath('data.balance_after', 25);
    }

    // ── Transfer ──────────────────────────────────────

    public function test_transfer_moves_stock_preserving_lot_and_expiry(): void
    {
        $this->seedStock(20, '2027-03-15', 'LOT-9');
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/transfer', [
            'from_branch_id' => $this->main->id,
            'to_branch_id'   => $this->satellite->id,
            'item_id'        => $this->item->id,
            'quantity'       => 8,
        ])->assertOk()
            ->assertJsonPath('data.source.balance_after', 12)
            ->assertJsonPath('data.destination.balance_after', 8);

        $received = InventoryBatch::where('branch_id', $this->satellite->id)->sole();
        $this->assertSame('LOT-9', $received->lot_number, 'the lot must survive the move');
        $this->assertSame('2027-03-15', $received->expiry_date->toDateString(), 'so must the expiry');
        $this->assertSame(8, $received->quantity_remaining);

        $this->assertSame(12, Inventory::where('branch_id', $this->main->id)->sole()->quantity);
        $this->assertSame(8, Inventory::where('branch_id', $this->satellite->id)->sole()->quantity);
    }

    public function test_transfer_beyond_stock_is_refused_and_rolls_back(): void
    {
        $this->seedStock(5, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        // Unlike usage, a transfer is a promise about the future — you cannot
        // ship what is not on the shelf.
        $this->postJson(self::BASE . '/inventories/transfer', [
            'from_branch_id' => $this->main->id,
            'to_branch_id'   => $this->satellite->id,
            'item_id'        => $this->item->id,
            'quantity'       => 9,
        ])->assertStatus(409);

        $this->assertSame(5, Inventory::where('branch_id', $this->main->id)->sole()->quantity);
        $this->assertSame(5, InventoryBatch::where('branch_id', $this->main->id)->sole()->quantity_remaining);
        $this->assertSame(0, InventoryBatch::where('branch_id', $this->satellite->id)->count());
    }

    public function test_transfer_to_the_same_branch_is_refused(): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/transfer', [
            'from_branch_id' => $this->main->id,
            'to_branch_id'   => $this->main->id,
            'item_id'        => $this->item->id,
            'quantity'       => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('to_branch_id');
    }

    // ── Ledger read ───────────────────────────────────

    public function test_the_ledger_lists_movements_newest_first(): void
    {
        $this->seedStock(20, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/usage', [
            'branch_id' => $this->main->id,
            'item_id'   => $this->item->id,
            'quantity'  => 5,
        ])->assertOk();

        $response = $this->getJson(self::BASE . '/stock-movements?item_id=' . $this->item->id);

        $response->assertOk()->assertJsonPath('data.total', 2);

        $types = collect($response->json('data.records'))->pluck('type')->all();
        $this->assertSame(['manual_usage', 'stock_in'], $types);
    }

    public function test_the_ledger_filters_by_type(): void
    {
        $this->seedStock(20, '2027-01-01', 'A1');
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/usage', [
            'branch_id' => $this->main->id,
            'item_id'   => $this->item->id,
            'quantity'  => 5,
        ])->assertOk();

        $this->getJson(self::BASE . '/stock-movements?type=stock_in')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.records.0.type', 'stock_in');
    }

    // ── Authorization ─────────────────────────────────

    /**
     * The whole point of this phase: these permission names existed but gated
     * nothing.
     */
    public function test_each_stock_verb_requires_its_own_permission(): void
    {
        Passport::actingAs($this->viewer, ['*'], 'api');

        $body = ['branch_id' => $this->main->id, 'item_id' => $this->item->id, 'quantity' => 1];

        $this->postJson(self::BASE . '/inventories/stock-in', $body)->assertForbidden();
        $this->postJson(self::BASE . '/inventories/usage', $body)->assertForbidden();

        $this->postJson(self::BASE . '/inventories/adjust', [
            'branch_id' => $this->main->id,
            'item_id' => $this->item->id,
            'counted_quantity' => 1,
            'reason' => 'x',
        ])->assertForbidden();

        $this->postJson(self::BASE . '/inventories/transfer', [
            'from_branch_id' => $this->main->id,
            'to_branch_id' => $this->satellite->id,
            'item_id' => $this->item->id,
            'quantity' => 1,
        ])->assertForbidden();

        // Reading is what this role CAN do.
        $this->getJson(self::BASE . '/stock-movements')->assertOk();
    }

    public function test_the_ledger_is_read_only(): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');

        // No store/update/destroy exists — an append-only ledger callers can
        // edit is not a ledger.
        $this->postJson(self::BASE . '/stock-movements', [])->assertStatus(405);
    }

    public function test_inventory_batches_can_be_queried_with_large_limit_and_open_only(): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');
        $this->seedStock(50, '2026-12-31', 'LOT-BATCH-001');

        $response = $this->getJson(self::BASE . '/inventory-batches?open_only=true&limit=200&order_by=expiry_date')
            ->assertOk();

        $response->assertJsonStructure([
            'success',
            'data' => [
                'records' => [
                    '*' => [
                        'id',
                        'branch_id',
                        'item_id',
                        'lot_number',
                        'expiry_date',
                        'quantity_received',
                        'quantity_remaining',
                        'received_at',
                        'is_expired',
                    ],
                ],
                'total',
            ],
            'message',
        ]);
    }

    public function test_inventory_batches_limits_are_clamped_defensively(): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');

        // Even with a limit exceeding 250, prepareForValidation defensively clamps it without 422.
        $this->getJson(self::BASE . '/inventory-batches?limit=500')
            ->assertOk();
    }


    // ── Helpers ───────────────────────────────────────

    private function seedStock(int $quantity, ?string $expiry, ?string $lot): void
    {
        Passport::actingAs($this->manager, ['*'], 'api');

        $this->postJson(self::BASE . '/inventories/stock-in', [
            'branch_id'   => $this->main->id,
            'item_id'     => $this->item->id,
            'quantity'    => $quantity,
            'lot_number'  => $lot,
            'expiry_date' => $expiry,
        ])->assertCreated();
    }
}
