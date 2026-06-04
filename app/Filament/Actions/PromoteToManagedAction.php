<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PromoteToManagedAction
{
    /**
     * Row action that promotes a Project from Running/Completed to Managed
     * Services. Visibility is restricted to super_admin/admin and to records
     * whose current status allows promotion. The handler re-validates the
     * status as a defense-in-depth measure against race conditions.
     *
     * @see Requirements 4.1-4.10
     */
    public static function make(): Action
    {
        return Action::make('promote_to_managed')
            ->label('Promote to Managed')
            ->icon('heroicon-m-arrow-up-circle')
            ->color('primary')
            ->visible(fn (Project $record): bool => in_array(
                $record->project_status,
                [ProjectStatus::Running, ProjectStatus::Completed],
                true
            ) && (auth()->user()?->hasRole(['super_admin', 'admin']) ?? false))
            ->requiresConfirmation()
            ->modalHeading('Promote ke Managed Services')
            ->modalDescription('Project ini akan dipindahkan ke Managed Services. Lanjutkan?')
            ->modalSubmitActionLabel('Promote')
            ->action(function (Project $record): void {
                // Re-validate the current status to defend against race
                // conditions where a user re-opens a stale page and tries to
                // promote a record that has since transitioned out of the
                // eligible set.
                if (! in_array(
                    $record->project_status,
                    [ProjectStatus::Running, ProjectStatus::Completed],
                    true
                )) {
                    Notification::make()
                        ->title('Invalid project status')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update(['project_status' => ProjectStatus::Managed]);

                Notification::make()
                    ->title('Project promoted to Managed Services')
                    ->success()
                    ->send();
            });
    }
}
