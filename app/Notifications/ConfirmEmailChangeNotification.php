<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

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
        $confirmationPath = URL::temporarySignedRoute(
            'profile.email.confirm',
            now()->addMinutes(5),
            [
                'user' => $this->userId,
                'email' => $this->newEmail,
            ],
            absolute: false,
        );

        $confirmationUrl = rtrim((string) config('app.url'), '/').$confirmationPath;

        $logoPath = public_path('images/spendless_logo.png');
        $logoCid = 'spendless-logo';
        $defaultLogoUrl = rtrim((string) config('app.url'), '/').'/images/spendless_logo.png';
        $logoSrc = is_file($logoPath) ? 'cid:'.$logoCid : $defaultLogoUrl;

        $message = (new MailMessage)
            ->subject('Confirm your new email address')
            ->view('emails.confirm-email-change', [
                'confirmationUrl' => $confirmationUrl,
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
            'user_id' => $this->userId,
            'new_email' => $this->newEmail,
        ];
    }
}
