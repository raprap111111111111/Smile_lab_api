<?php

namespace Tests\Feature;

use App\Domain\Inventories\DTOs\RecordMovementDTO;
use App\Domain\Inventories\Services\FefoAllocator;
use App\Domain\Inventories\Services\StockLedger;
use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\InventoryBatch;
use App\Models\Item;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proof that FefoAllocator's row lock is real.
 *
 * The rest of the suite runs on SQLite, where lockForUpdate() compiles to
 * nothing at all — so every other test would pass just as happily with the lock
 * removed. Without it, two appointments completing at the same moment can both
 * read "4 remaining" and both draw 4, spending stock that was never there.
 *
 * These tests therefore require MySQL and skip elsewhere. Run them with:
 *
 *   DB_CONNECTION=mysql DB_DATABASE=smilelab_test \
 *     php artisan test --filter=StockConcurrencyTest
 *
 * DatabaseMigrations rather than RefreshDatabase on purpose: RefreshDatabase
 * wraps each test in a transaction that is never committed, so a second
 * connection could not see the fixtures at all.
 *
 * @group mysql
 */
class StockConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    /** Second connection, standing in for the other request. */
    private const OTHER = 'mysql_concurrent';

    private Branch $branch;
    private Item $item;
    private InventoryBatch $batch;

    protected function setUp(): void
    {
        // Skipped BEFORE the app boots, deliberately.
        //
        // DatabaseMigrations hooks a migrate:rollback into tearDown as soon as
        // parent::setUp() runs, and rolling this schema back on SQLite fails
        // outright — SQLite cannot drop a column that an index still references.
        // Bailing out first means the trait never engages at all.
        if (($_SERVER['DB_CONNECTION'] ?? $_ENV['DB_CONNECTION'] ?? 'sqlite') !== 'mysql') {
            $this->markTestSkipped(
                'Row locking is a no-op on SQLite. Re-run against MySQL — see the class docblock.'
            );
        }

        parent::setUp();

        config([
            'database.connections.' . self::OTHER => config('database.connections.mysql'),
        ]);

        $this->branch = Branch::create([
            'name' => 'Main', 'branch_code' => 'MAIN', 'address' => '1 Test St',
        ]);

        $this->item = Item::create([
            'name' => 'Lidocaine 2% carpule', 'sku' => 'ANES-LIDO-2',
            'category' => 'Anesthetics', 'unit_of_measure' => 'carpule',
            'minimum_threshold' => 20,
        ]);

        app(StockLedger::class)->record(new RecordMovementDTO(
            branchId: $this->branch->id,
            itemId: $this->item->id,
            type: StockMovementType::STOCK_IN,
            quantityDelta: 4,
            lotNumber: 'A1',
            expiryDate: '2027-01-01',
        ));

        $this->batch = InventoryBatch::sole();
    }

    protected function tearDown(): void
    {
        // Nothing to unwind when the skip fired before the app booted.
        if ($this->app === null) {
            parent::tearDown();

            return;
        }

        // Only the default connection exists when setUp skipped out before
        // registering the second one.
        $names = config('database.connections.' . self::OTHER) === null
            ? [null]
            : [null, self::OTHER];

        // A failed assertion must not leave a transaction open on either
        // connection, or the next test blocks on the same rows.
        foreach ($names as $name) {
            $connection = DB::connection($name);

            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }

        if (count($names) > 1) {
            DB::purge(self::OTHER);
        }

        parent::tearDown();
    }

    public function test_allocating_locks_the_batch_against_a_second_request(): void
    {
        $other = DB::connection(self::OTHER);

        // Without this the second request would simply hang until the first
        // commits, and so would this test. One second is long enough to prove
        // the lock is held and short enough to fail fast.
        $other->statement('SET SESSION innodb_lock_wait_timeout = 1');

        DB::beginTransaction();

        $result = app(FefoAllocator::class)->allocate(
            $this->branch->id,
            $this->item->id,
            4,
        );

        $this->assertSame(4, $result['allocations'][0]['quantity']);

        $blocked = false;

        $other->beginTransaction();

        try {
            $other->table('inventory_batches')
                ->where('id', $this->batch->id)
                ->lockForUpdate()
                ->get();
        } catch (QueryException $e) {
            $blocked = true;
            $this->assertStringContainsString('Lock wait timeout', $e->getMessage());
        }

        $other->rollBack();
        DB::rollBack();

        $this->assertTrue(
            $blocked,
            'The second request read the batch while the first held it — two '
            . 'simultaneous completions could both spend the same units.',
        );
    }

    public function test_without_a_lock_the_second_request_reads_straight_through(): void
    {
        $other = DB::connection(self::OTHER);
        $other->statement('SET SESSION innodb_lock_wait_timeout = 1');

        DB::beginTransaction();

        // The same query the allocator runs, minus lockForUpdate(). This is the
        // control: it shows the timeout above comes from the lock and not from
        // some other property of the fixture.
        InventoryBatch::query()
            ->where('branch_id', $this->branch->id)
            ->where('item_id', $this->item->id)
            ->open()
            ->fefo()
            ->get();

        $other->beginTransaction();

        $rows = $other->table('inventory_batches')
            ->where('id', $this->batch->id)
            ->lockForUpdate()
            ->get();

        $other->rollBack();
        DB::rollBack();

        $this->assertCount(1, $rows);
    }

    public function test_a_committed_allocation_is_visible_to_the_next_request(): void
    {
        $other = DB::connection(self::OTHER);

        DB::transaction(function (): void {
            app(StockLedger::class)->record(new RecordMovementDTO(
                branchId: $this->branch->id,
                itemId: $this->item->id,
                type: StockMovementType::CONSUMPTION,
                quantityDelta: -3,
            ));
        });

        // Once the lock is released the next request sees the drawn-down
        // figure, not the stale one it would have read a moment earlier.
        $remaining = $other->table('inventory_batches')
            ->where('id', $this->batch->id)
            ->value('quantity_remaining');

        $this->assertSame(1, (int) $remaining);
    }
}
