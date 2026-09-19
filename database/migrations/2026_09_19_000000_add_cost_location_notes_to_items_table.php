<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            // Catalogue reference cost per unit, used for stock valuation.
            $table->decimal('unit_cost', 12, 2)->default(0)->after('maximum_threshold');
            $table->string('storage_location', 150)->nullable()->after('unit_cost');
            $table->text('notes')->nullable()->after('storage_location');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'storage_location', 'notes']);
        });
    }
};
