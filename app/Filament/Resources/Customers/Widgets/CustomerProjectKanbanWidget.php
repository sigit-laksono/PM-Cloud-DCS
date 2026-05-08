<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Widgets;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class CustomerProjectKanbanWidget extends Widget
{
    protected string $view = 'filament.resources.customers.widgets.customer-project-kanban-widget';

    protected static bool $isLazy = false;

    public ?Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public function getProjectsByStatus(): array
    {
        if (!$this->record) {
            return [];
        }

        $projects = $this->record->projects()->get();

        $grouped = [];
        foreach (ProjectStatus::cases() as $status) {
            $grouped[$status->value] = [
                'label' => $status->getLabel(),
                'color' => $status->getColor(),
                'projects' => $projects->where('project_status', $status)->values(),
            ];
        }

        return $grouped;
    }

    public function updateProjectStatus(string $projectId, string $newStatus): void
    {
        $status = ProjectStatus::tryFrom($newStatus);

        if (!$status) {
            return;
        }

        $project = Project::find($projectId);

        if (!$project || $project->customer_id !== $this->record?->id) {
            return;
        }

        $project->update(['project_status' => $status]);

        Notification::make()
            ->title("{$project->name} moved to {$status->getLabel()}")
            ->success()
            ->send();
    }
}
