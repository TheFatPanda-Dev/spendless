<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminStats;
use App\Filament\Widgets\SetupChecksTable;
use App\Models\AppSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Overview';

    protected static ?string $navigationLabel = 'Overview';

    public function getWidgets(): array
    {
        return [
            AdminStats::class,
            SetupChecksTable::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        $open = AppSetting::registrationOpen();

        return [
            Action::make('toggleRegistration')
                ->label($open ? 'Close sign-ups' : 'Open sign-ups')
                ->icon($open ? Heroicon::OutlinedLockClosed : Heroicon::OutlinedLockOpen)
                ->color($open ? 'gray' : 'primary')
                ->requiresConfirmation()
                ->modalHeading($open ? 'Close new sign-ups?' : 'Open new sign-ups?')
                ->modalDescription($open
                    ? 'Nobody new can create an account, by email or with Google or GitHub. Existing users are not affected.'
                    : 'Anyone with the link can create an account.')
                ->action(function (): void {
                    $open = ! AppSetting::registrationOpen();
                    AppSetting::putValue(AppSetting::REGISTRATION_OPEN, $open ? '1' : '0');

                    Notification::make()
                        ->title($open ? 'New sign-ups are open.' : 'New sign-ups are closed.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
