<?php

namespace App\Notifications;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Organization $organization,
        private readonly OrganizationRole $role,
        private readonly string $plainToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/')
            .'/invitations/accept?token='.urlencode($this->plainToken);

        return (new MailMessage)
            ->subject("Invitation to {$this->organization->name}")
            ->greeting('You have been invited')
            ->line("Join {$this->organization->name} as {$this->role->label()}.")
            ->action('Accept invitation', $url)
            ->line('This invitation expires in seven days.');
    }
}
