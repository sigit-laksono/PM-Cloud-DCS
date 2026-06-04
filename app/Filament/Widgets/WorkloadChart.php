<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class WorkloadChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Team Workload — Open Tickets Per Person';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['md' => 2, 'xl' => 1];

    protected ?string $maxHeight = '380px';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $projectIds = $isSuperAdmin
            ? Project::pluck('id')
            : $user->projects()->pluck('projects.id');

        if ($projectIds->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $rows = DB::table('ticket_users')
            ->join('tickets', 'ticket_users.ticket_id', '=', 'tickets.id')
            ->join('users', 'ticket_users.user_id', '=', 'users.id')
            ->join('ticket_statuses', 'tickets.ticket_status_id', '=', 'ticket_statuses.id')
            ->whereIn('tickets.project_id', $projectIds)
            ->where('ticket_statuses.is_completed', false)
            ->select('users.name', DB::raw('COUNT(DISTINCT tickets.id) as open_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('open_count')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = $rows->pluck('name')->toArray();
        $counts = $rows->pluck('open_count')->map(fn ($v) => (int) $v)->toArray();

        // Red >10, Yellow >5, Green ≤5
        $colors = array_map(
            fn ($c) => $c > 10 ? '#EF4444' : ($c > 5 ? '#F59E0B' : '#10B981'),
            $counts
        );

        return [
            'datasets' => [[
                'label'           => 'Open Tickets',
                'data'            => $counts,
                'backgroundColor' => $colors,
                'borderColor'     => $colors,
                'borderWidth'     => 1,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y', // horizontal bars — names are easier to read
            'plugins'   => ['legend' => ['display' => false]],
            'scales'    => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks'       => ['stepSize' => 1],
                ],
            ],
            'responsive'          => true,
            'maintainAspectRatio' => false,
        ];
    }
}
