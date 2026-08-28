<?php

namespace App\Notifications;

use App\Models\ComplianceItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComplianceExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ComplianceItem $complianceItem,
        private readonly int $thresholdDays,
    ) {
    }

    /**
     * Both in-app (database) and email, per brief §24: "Notifications may
     * be sent through: In-app notification, Email."
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray($notifiable): array
    {
        return [
            'compliance_item_id' => $this->complianceItem->uuid,
            'title' => $this->complianceItem->title,
            'type' => $this->complianceItem->type,
            'expiry_date' => $this->complianceItem->expiry_date?->toDateString(),
            'threshold_days' => $this->thresholdDays,
            'message' => $this->message(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Compliance Item Expiry Alert: '.$this->complianceItem->title)
            ->line($this->message())
            ->line('Expiry date: '.$this->complianceItem->expiry_date?->toDateString());
    }

    private function message(): string
    {
        if ($this->thresholdDays === 0) {
            return "\"{$this->complianceItem->title}\" has expired.";
        }

        return "\"{$this->complianceItem->title}\" expires within {$this->thresholdDays} days.";
    }
}
