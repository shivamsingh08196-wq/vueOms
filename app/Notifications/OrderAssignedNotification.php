<?php

namespace App\Notifications;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Purchase $purchase)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_assigned',
            'order_id' => $this->purchase->id,
            'order_number' => $this->purchase->purchase_number,
            'message' => "New purchase #{$this->purchase->purchase_number} assigned to you.",
        ];
    }
}

