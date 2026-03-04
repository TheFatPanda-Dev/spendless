<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ConfirmEmailChangeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $userId,
        public string $newEmail,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $confirmationUrl = URL::temporarySignedRoute(
            'profile.email.confirm',
            now()->addMinutes(60),
            [
                'user' => $this->userId,
                'email' => $this->newEmail,
            ],
        );

        return (new MailMessage)
            ->subject('Confirm your new email address')
            ->line('You requested to change the email address on your Spendless account.')
            ->line('Confirm this new email address to finish the update.')
            ->action('Confirm new email address', $confirmationUrl)
            ->line('If you did not request this change, you can safely ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->userId,
            'new_email' => $this->newEmail,
        ];
    }
}
