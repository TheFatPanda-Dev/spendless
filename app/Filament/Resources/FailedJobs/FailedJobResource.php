<?php

namespace App\Filament\Resources\FailedJobs;

use App\Filament\Concerns\SentenceCaseLabels;
use App\Filament\Resources\FailedJobs\Pages\ListFailedJobs;
use App\Models\FailedJob;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Background jobs (bank syncs, emails) that gave up. Retry them once the cause is
 * fixed, or clear them.
 */
class FailedJobResource extends Resource
{
    use SentenceCaseLabels;

    protected static ?string $model = FailedJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Failed jobs';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DB::table('failed_jobs')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('failed_at', 'desc')
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('Bank syncs and emails are running fine.')
            ->columns([
                TextColumn::make('failed_at')
                    ->label('Failed')
                    ->dateTime('j M Y, H:i')
                    ->sortable(),
                TextColumn::make('payload')
                    ->label('Job')
                    ->formatStateUsing(fn (FailedJob $record): string => $record->jobName())
                    ->weight('medium'),
                TextColumn::make('queue'),
                TextColumn::make('exception')
                    ->label('Error')
                    ->formatStateUsing(fn (FailedJob $record): string => $record->errorSummary())
                    ->wrap()
                    ->color('gray'),
            ])
            ->recordActions([
                Action::make('retry')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                        Notification::make()->title('The job was queued again.')->success()->send();
                    }),
                Action::make('forget')
                    ->label('Clear')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FailedJob $record): void {
                        Artisan::call('queue:forget', ['id' => $record->uuid]);

                        Notification::make()->title('The failed job was cleared.')->success()->send();
                    }),
            ])
            ->headerActions([
                Action::make('retryAll')
                    ->label('Retry all')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn (): bool => DB::table('failed_jobs')->exists())
                    ->action(function (): void {
                        Artisan::call('queue:retry', ['id' => ['all']]);

                        Notification::make()->title('Failed jobs were queued again.')->success()->send();
                    }),
                Action::make('clearAll')
                    ->label('Clear all')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => DB::table('failed_jobs')->exists())
                    ->action(function (): void {
                        Artisan::call('queue:flush');

                        Notification::make()->title('Failed jobs were cleared.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFailedJobs::route('/'),
        ];
    }
}
