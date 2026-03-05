<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $token) {}

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

        $email = method_exists($notifiable, 'getEmailForPasswordReset')
            ? (string) $notifiable->getEmailForPasswordReset()
            : (string) ($notifiable->email ?? '');

        $resetPath = route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], absolute: false);

        $resetUrl = rtrim((string) config('app.url'), '/').$resetPath;
        $passwordBroker = (string) config('auth.defaults.passwords', 'users');
        $expiresInMinutes = (int) config("auth.passwords.{$passwordBroker}.expire", 60);
        $minuteLabel = $expiresInMinutes === 1 ? 'minute' : 'minutes';
        $expiryText = "This reset link will stay active for {$expiresInMinutes} {$minuteLabel}.";

        $message = (new MailMessage)
            ->subject('Reset your SpendLess password')
            ->view('emails.password-reset', [
                'logoSrc' => $logoSrc,
                'resetUrl' => $resetUrl,
                'expiryText' => $expiryText,
            ]);

        if (is_file($logoPath)) {
            $message->withSymfonyMessage(function (Email $emailMessage) use ($logoCid, $logoPath): void {
                $emailMessage->embedFromPath($logoPath, $logoCid, 'image/png');
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
            'type' => 'password_reset',
        ];
    }
}
