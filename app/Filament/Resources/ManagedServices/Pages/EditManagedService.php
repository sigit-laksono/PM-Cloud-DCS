<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManagedServices\Pages;

use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use Filament\Resources\Pages\EditRecord;

class EditManagedService extends EditRecord
{
    protected static string $resource = ManagedServiceResource::class;

    /**
     * No header actions: Delete is forbidden by ManagedServicePolicy::delete()
     * and project_status transitions are managed via DemoteFromManagedAction
     * on the list page rather than from the edit form.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
