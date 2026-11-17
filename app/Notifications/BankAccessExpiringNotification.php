<?php

namespace App\Notifications;

use App\Models\BankConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class BankAccessExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public BankConnection $bankConnection) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $logoPath = public_path('images/spendless_logo.png');
        $logoCid = 'spendless-logo';
        $defaultLogoUrl = rtrim((string) config('app.url'), '/').'/images/spendless_logo.png';
        $logoSrc = is_file($logoPath) ? 'cid:'.$logoCid : $defaultLogoUrl;
        $institutionName = (string) ($this->bankConnection->institution_name ?: $this->bankConnection->aspsp_name);

        $message = (new MailMessage)
            ->subject(sprintf('Reconnect %s to keep SpendLess syncing', $institutionName))
            ->view('emails.bank-access-expiring', [
                'logoSrc' => $logoSrc,
                'institutionName' => $institutionName,
                'expiresOn' => $this->bankConnection->consent_valid_until?->format('j F Y') ?? 'soon',
                'reconnectUrl' => $this->bankConnection->wallet_id !== null
                    ? route('wallets.show', $this->bankConnection->wallet_id)
                    : route('wallets.index'),
            ]);

        if (is_file($logoPath)) {
            $message->withSymfonyMessage(function (Email $email) use ($logoCid, $logoPath): void {
                $email->embedFromPath($logoPath, $logoCid, 'image/png');
            });
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bank_access_expiring',
            'bank_connection_id' => $this->bankConnection->id,
        ];
    }
}
