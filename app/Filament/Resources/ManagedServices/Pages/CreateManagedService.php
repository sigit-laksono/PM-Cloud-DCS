<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManagedServices\Pages;

use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateManagedService extends CreateRecord
{
    protected static string $resource = ManagedServiceResource::class;

    /**
     * Pre-fill customer_id from query string when creating a managed service
     * from the Customer Detail page (e.g. /managed-services/create?customer_id=123).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($customerId = request()->query('customer_id')) {
            $data['customer_id'] = $customerId;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $createDefaultStatuses = $this->data['create_default_statuses'] ?? true;

        if ($createDefaultStatuses) {
            $defaultStatuses = [
                ['name' => 'Backlog', 'color' => '#6B7280', 'sort_order' => 0],
                ['name' => 'To Do', 'color' => '#F59E0B', 'sort_order' => 1],
                ['name' => 'In Progress', 'color' => '#3B82F6', 'sort_order' => 2],
                ['name' => 'Review', 'color' => '#8B5CF6', 'sort_order' => 3],
                ['name' => 'Done', 'color' => '#10B981', 'sort_order' => 4, 'is_completed' => true],
            ];

            foreach ($defaultStatuses as $status) {
                $this->record->ticketStatuses()->create($status);
            }
        }

        // Pastikan user yang membuat langsung terdaftar sebagai member
        if (auth()->check()) {
            $this->record->members()->syncWithoutDetaching(auth()->id());
        }
    }

    protected function getRedirectUrl(): string
    {
        return ManagedServiceResource::getUrl('view', ['record' => $this->record]);
    }
}
