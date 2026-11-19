<?php

namespace App\Filament\Resources\SyncRuns\Pages;

use App\Filament\Resources\SyncRuns\BankSyncRunResource;
use Filament\Resources\Pages\ListRecords;

class ListBankSyncRuns extends ListRecords
{
    protected static string $resource = BankSyncRunResource::class;
}
