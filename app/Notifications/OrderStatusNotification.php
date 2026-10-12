<?php

namespace App\Notifications;

use App\Models\Purchase;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Purchase $purchase,
        public string $status,
        public string $type,
        public ?string $message = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $defaultMessage = match ($this->status) {
            'confirmed' => "Purchase #{$this->purchase->purchase_number} has been confirmed.",
            'cancelled' => "Purchase #{$this->purchase->purchase_number} was cancelled.",
            default => "Purchase #{$this->purchase->purchase_number} status updated to {$this->status}."
        };

        return [
            'type' => $this->type,
            'order_id' => $this->purchase->id,
            'order_number' => $this->purchase->purchase_number,
            'message' => $this->message ?: $defaultMessage,
        ];
    }
}

