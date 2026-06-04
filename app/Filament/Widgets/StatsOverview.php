<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use App\Models\Ticket;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $user = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $projectIds = $isSuperAdmin
            ? Project::pluck('id')
            : $user->projects()->pluck('projects.id');

        // Projects at Risk: ending ≤ 7 days (including already past end_date) with open tickets
        $projectsAtRisk = Project::whereIn('id', $projectIds)
            ->whereNotNull('end_date')
            ->where('end_date', '<=', today()->addDays(7))
            ->whereHas('tickets', fn ($q) => $q->whereHas('status', fn ($s) => $s->where('is_completed', false)))
            ->count();

        // Overdue tickets: past due date, still open
        $overdueTickets = Ticket::whereIn('project_id', $projectIds)
            ->whereNotNull('due_date')
            ->where('due_date', '<', today())
            ->where(fn ($q) =>
                $q->whereHas('status', fn ($s) => $s->where('is_completed', false))
                  ->orWhereNull('ticket_status_id')
            )
            ->count();

        // Completed this week
        $completedThisWeek = Ticket::whereIn('project_id', $projectIds)
            ->whereHas('status', fn ($q) => $q->where('is_completed', true))
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        // Open tickets with no assignee
        $unassignedTickets = Ticket::whereIn('project_id', $projectIds)
            ->where(fn ($q) =>
                $q->whereHas('status', fn ($s) => $s->where('is_completed', false))
                  ->orWhereNull('ticket_status_id')
            )
            ->doesntHave('assignees')
            ->count();

        return [
            Stat::make('Projects at Risk', $projectsAtRisk)
                ->description('Deadline ≤ 7 days with open tickets')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($projectsAtRisk > 0 ? 'danger' : 'success'),

            Stat::make('Overdue Tickets', $overdueTickets)
                ->description('Past due date, not yet completed')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdueTickets > 0 ? 'danger' : 'success'),

            Stat::make('Completed This Week', $completedThisWeek)
                ->description('Tickets resolved in the last 7 days')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($completedThisWeek > 0 ? 'success' : 'gray'),

            Stat::make('Unassigned Tickets', $unassignedTickets)
                ->description('Open tickets with no assignee')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color($unassignedTickets > 0 ? 'warning' : 'success'),
        ];
    }
}
