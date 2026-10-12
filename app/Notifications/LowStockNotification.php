<?php

namespace App\Notifications;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(public Item $item)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'order_id' => null,
            'order_number' => null,
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'message' => "Item {$this->item->name} is low on stock.",
        ];
    }
}

