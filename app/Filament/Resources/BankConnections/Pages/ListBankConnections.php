<?php

namespace App\Filament\Resources\BankConnections\Pages;

use App\Filament\Resources\BankConnections\BankConnectionResource;
use Filament\Resources\Pages\ListRecords;

class ListBankConnections extends ListRecords
{
    protected static string $resource = BankConnectionResource::class;
}
