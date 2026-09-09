<?php

declare(strict_types=1);

namespace App\Domain\Suppliers\Actions;

use App\Models\Supplier;

class DeleteSupplierAction
{
    public function execute(Supplier $supplier): void
    {
        $supplier->delete();
    }
}
