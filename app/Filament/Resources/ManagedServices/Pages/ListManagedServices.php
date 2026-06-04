<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManagedServices\Pages;

use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListManagedServices extends ListRecords
{
    protected static string $resource = ManagedServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => auth()->user()?->can('manage_managed_service') ?? false),
        ];
    }
}
