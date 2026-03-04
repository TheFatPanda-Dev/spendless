<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class PasswordChangedNotification extends Notification
{
    use Queueable;

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
        $logoPath = public_path('images/spendless_logo.png');
        $logoCid = 'spendless-logo';
        $defaultLogoUrl = rtrim((string) config('app.url'), '/').'/images/spendless_logo.png';
        $logoSrc = is_file($logoPath) ? 'cid:'.$logoCid : $defaultLogoUrl;

        $message = (new MailMessage)
            ->subject('Your SpendLess password was changed')
            ->view('emails.password-changed', [
                'logoSrc' => $logoSrc,
            ]);

        if (is_file($logoPath)) {
            $message->withSymfonyMessage(function (Email $email) use ($logoCid, $logoPath): void {
                $email->embedFromPath($logoPath, $logoCid, 'image/png');
            });
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'password_changed',
        ];
    }
}
