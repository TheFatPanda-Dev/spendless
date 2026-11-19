<?php

namespace App\Filament\Resources\BankConnections;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\BankConnections\Pages\ListBankConnections;
use App\Models\BankConnection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every synced bank, with whose it is, its status and when its access runs out.
 * Read-only: people reconnect or disconnect their own banks in the app.
 */
class BankConnectionResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = BankConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Bank connections';

    protected static ?int $navigationSort = 2;

    public const STATUS_LABELS = [
        BankConnection::STATUS_CONNECTED => 'Connected',
        BankConnection::STATUS_SYNCING => 'Syncing',
        BankConnection::STATUS_SYNC_FAILED => 'Sync failed',
        BankConnection::STATUS_NEEDS_REAUTH => 'Needs reconnecting',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('user:id,name,email')
            ->withCount('accounts')
            ->whereIn('provider', BankConnection::SYNCED_PROVIDERS)
            ->where('status', '!=', BankConnection::STATUS_PENDING);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('institution_name')
            ->columns([
                TextColumn::make('institution_name')
                    ->label('Bank')
                    ->description(fn (BankConnection $record): string => match ($record->provider) {
                        BankConnection::PROVIDER_ENABLE_BANKING => 'Enable Banking',
                        BankConnection::PROVIDER_PLAID => 'Plaid',
                        BankConnection::PROVIDER_IBKR => 'Interactive Brokers',
                        default => (string) $record->provider,
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('Owner')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        BankConnection::STATUS_CONNECTED => 'success',
                        BankConnection::STATUS_SYNCING => 'info',
                        BankConnection::STATUS_NEEDS_REAUTH => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('accounts_count')
                    ->label('Accounts')
                    ->numeric(),
                TextColumn::make('last_synced_at')
                    ->label('Last sync')
                    ->since()
                    ->dateTimeTooltip('j M Y, H:i')
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('consent_valid_until')
                    ->label('Access until')
                    ->date('j M Y')
                    ->placeholder('No limit')
                    ->color(fn (BankConnection $record): ?string => $record->consentExpiresSoon() ? 'warning' : null)
                    ->sortable(),
                TextColumn::make('last_sync_error')
                    ->label('Last error')
                    ->limit(80)
                    ->tooltip(fn (BankConnection $record): ?string => $record->last_sync_error)
                    ->wrap()
                    ->placeholder('None')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUS_LABELS),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankConnections::route('/'),
        ];
    }
}
