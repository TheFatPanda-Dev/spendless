<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Http\Controllers\Banking\EnableBankingConnectionController;
use App\Models\BankConnection;
use App\Models\User;
use App\Services\EnableBanking\EnableBankingClient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Everyone with an account. People sign up themselves (when sign-ups are open), so
 * there is no create or edit form: an admin can only change the admin role or delete.
 */
class UserResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'wallets',
                'bankConnections as banks_count' => fn (Builder $query) => $query
                    ->whereIn('provider', BankConnection::SYNCED_PROVIDERS)
                    ->where('status', '!=', BankConnection::STATUS_PENDING),
            ]))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Name')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'preferred_name', 'email']),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                IconColumn::make('email_verified_at')
                    ->label('Email verified')
                    ->state(fn (User $record): bool => $record->email_verified_at !== null)
                    ->boolean(),
                IconColumn::make('two_factor_confirmed_at')
                    ->label('Two-factor')
                    ->state(fn (User $record): bool => $record->two_factor_confirmed_at !== null)
                    ->boolean(),
                TextColumn::make('wallets_count')
                    ->label('Wallets')
                    ->numeric(),
                TextColumn::make('banks_count')
                    ->label('Synced banks')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('j M Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('toggleAdmin')
                    ->label(fn (User $record): string => $record->is_admin ? 'Remove admin' : 'Make admin')
                    ->icon(fn (User $record) => $record->is_admin ? Heroicon::OutlinedShieldExclamation : Heroicon::OutlinedShieldCheck)
                    ->color('gray')
                    ->hidden(fn (User $record): bool => $record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->forceFill(['is_admin' => ! $record->is_admin])->save();

                        Notification::make()
                            ->title(sprintf('%s is %s an admin.', $record->email, $record->is_admin ? 'now' : 'no longer'))
                            ->success()
                            ->send();
                    }),
                Action::make('deleteUser')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->hidden(fn (User $record): bool => $record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => "Delete {$record->email}?")
                    ->modalDescription('Their wallets, transactions, budgets and tags are deleted, and their bank consents are closed. This cannot be undone.')
                    ->modalSubmitActionLabel('Delete user and data')
                    ->action(function (User $record, EnableBankingClient $client): void {
                        $record->bankConnections()
                            ->where('provider', BankConnection::PROVIDER_ENABLE_BANKING)
                            ->get()
                            ->each(fn (BankConnection $connection) => EnableBankingConnectionController::closeBankSession($client, $connection));

                        $email = $record->email;
                        $record->delete();

                        Notification::make()
                            ->title("{$email} and all their data were deleted.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
