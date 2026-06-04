<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Widgets\CustomerProjectKanbanWidget;
use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createProject')
                ->label('New Project')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn (): string => ProjectResource::getUrl('create', [
                    'customer_id' => $this->record->id,
                ]))
                ->visible(fn (): bool => ProjectResource::canCreate()),
            Action::make('createManagedService')
                ->label('New Managed Service')
                ->icon('heroicon-o-briefcase')
                ->color('info')
                ->url(fn (): string => ManagedServiceResource::getUrl('create', [
                    'customer_id' => $this->record->id,
                ]))
                ->visible(fn (): bool => ManagedServiceResource::canCreate()),
            EditAction::make(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CustomerProjectKanbanWidget::class,
        ];
    }
}
