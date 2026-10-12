<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Reserve stock for a purchase (called when status becomes confirmed/processing/ready/completed).
     *
     * @throws ValidationException
     */
    public function reserve(Purchase $purchase): void
    {
        if ($purchase->stock_reserved) {
            return;
        }

        $lines = $purchase->lines()->with('item')->get();

        // 1. Check stock availability for all lines
        $insufficient = [];
        foreach ($lines as $line) {
            $item = $line->item;
            if ($item && $item->stock < $line->quantity) {
                $insufficient[] = "{$item->name} (available {$item->stock})";
            }
        }

        if (! empty($insufficient)) {
            throw ValidationException::withMessages([
                'stock' => ['Insufficient stock for: ' . implode(', ', $insufficient) . '.'],
            ])->status(422);
        }

        // 2. Decrement stock atomically (locking ordered by id to prevent deadlocks)
        $lineItemIds = $lines->pluck('item_id')->unique()->sort()->values();
        $items = Item::whereIn('id', $lineItemIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($lines as $line) {
            $item = $items->get($line->item_id);
            if ($item) {
                $item->decrement('stock', $line->quantity);
                $item->refresh();

                // Low stock alert (threshold <= 5)
                if ($item->stock <= 5) {
                    $admins = User::role('admin')->get();
                    foreach ($admins as $adminUser) {
                        $adminUser->notify(new LowStockNotification($item));
                    }
                }
            }
        }

        $purchase->stock_reserved = true;
        $purchase->save();
    }

    /**
     * Restore stock when a reserved purchase is cancelled.
     */
    public function restore(Purchase $purchase): void
    {
        if (! $purchase->stock_reserved) {
            return;
        }

        $lines = $purchase->lines()->with('item')->get();
        $lineItemIds = $lines->pluck('item_id')->unique()->sort()->values();
        $items = Item::whereIn('id', $lineItemIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($lines as $line) {
            $item = $items->get($line->item_id);
            if ($item) {
                $item->increment('stock', $line->quantity);
            }
        }

        $purchase->stock_reserved = false;
        $purchase->save();
    }
}

