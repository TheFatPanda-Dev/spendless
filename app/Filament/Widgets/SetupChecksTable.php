<?php

namespace App\Filament\Widgets;

use App\Support\Admin\SetupChecks;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * "Did I set it up right?": server, cron, queue, mail and Enable Banking checks.
 * Lazy, because the Enable Banking checks call the bank API.
 */
class SetupChecksTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Setup checks')
            ->description('Green works, amber works but is worth fixing, red is broken.')
            ->records(function (): array {
                $checks = app(SetupChecks::class);

                return collect([...$checks->local(request()), ...$checks->enableBanking()])
                    ->keyBy('key')
                    ->all();
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('status')
                    ->label('')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ok' => 'OK',
                        'warn' => 'Check',
                        default => 'Broken',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ok' => 'success',
                        'warn' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('label')
                    ->label('Check')
                    ->weight('medium'),
                TextColumn::make('detail')
                    ->label('Details')
                    ->wrap()
                    ->color('gray'),
            ]);
    }
}
