<?php

namespace App\Domain\Items\Actions;

use App\Models\Item;

class RestoreItemAction
{
    /**
     * Restores an archived supply item back to active inventory catalog.
     */
    public function execute(Item $item): bool
    {
        return $item->restore();
    }
}
