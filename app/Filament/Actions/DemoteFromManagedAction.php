<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DemoteFromManagedAction
{
    /**
     * Row action that demotes a Managed Service back to Running. Visibility
     * is restricted to users with role super_admin AND the
     * `manage_managed_service` permission, on records that currently sit in
     * the Managed status. The handler re-validates authorization as a
     * defense-in-depth measure.
     *
     * @see Requirements 5.1-5.8
     */
    public static function make(): Action
    {
        return Action::make('demote_from_managed')
            ->label('Demote to Running')
            ->icon('heroicon-m-arrow-down-circle')
            ->color('warning')
            ->visible(function (Project $record): bool {
                $user = auth()->user();

                if ($user === null) {
                    return false;
                }

                return $record->project_status === ProjectStatus::Managed
                    && $user->hasRole('super_admin')
                    && $user->can('manage_managed_service');
            })
            ->requiresConfirmation()
            ->modalHeading('Demote dari Managed Services')
            ->modalDescription('Project akan dikembalikan ke status Running. Lanjutkan?')
            ->modalSubmitActionLabel('Demote')
            ->action(function (Project $record): void {
                $user = auth()->user();

                // Defense-in-depth: re-check authorization in the handler in
                // case the action is somehow invoked outside the visibility
                // gate (e.g. directly via a stale Livewire request).
                if (
                    $user === null
                    || ! $user->hasRole('super_admin')
                    || ! $user->can('manage_managed_service')
                ) {
                    Notification::make()
                        ->title('Permission denied')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update(['project_status' => ProjectStatus::Running]);

                Notification::make()
                    ->title('Service demoted to Running')
                    ->success()
                    ->send();
            });
    }
}
