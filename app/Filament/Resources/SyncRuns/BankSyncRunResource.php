<?php

namespace App\Filament\Resources\SyncRuns;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\SyncRuns\Pages\ListBankSyncRuns;
use App\Models\BankSyncRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The last 30 days of bank syncs (older runs are pruned): when, what came in, and
 * the error when one failed.
 */
class BankSyncRunResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = BankSyncRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $slug = 'sync-runs';

    protected static ?string $navigationLabel = 'Sync runs';

    protected static ?string $modelLabel = 'sync run';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('bankConnection.user:id,email');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('bankConnection.institution_name')
                    ->label('Bank')
                    ->description(fn (BankSyncRun $record): ?string => $record->bankConnection?->user?->email),
                TextColumn::make('sync_type')
                    ->label('Trigger')
                    ->formatStateUsing(fn (?string $state): string => ucfirst(str_replace('_', ' ', (string) $state))),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'succeeded', 'success', 'completed' => 'success',
                        'running' => 'info',
                        default => 'danger',
                    }),
                TextColumn::make('added_count')
                    ->label('Added')
                    ->numeric(),
                TextColumn::make('modified_count')
                    ->label('Updated')
                    ->numeric(),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(80)
                    ->tooltip(fn (BankSyncRun $record): ?string => $record->error_message)
                    ->wrap()
                    ->placeholder('None')
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'running' => 'Running',
                    'succeeded' => 'Succeeded',
                    'failed' => 'Failed',
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankSyncRuns::route('/'),
        ];
    }
}
