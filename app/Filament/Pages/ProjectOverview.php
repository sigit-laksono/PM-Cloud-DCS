<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProjectOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'project-overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Project Management Overview';

    protected string $view = 'filament.pages.project-overview';

    public Collection $customers;

    public int $totalProjects   = 0;

    public int $totalCustomers  = 0;

    public int $totalOpenTickets = 0;

    public int $totalRunning    = 0;

    public int $totalPoc        = 0;

    public int $totalCompleted  = 0;

    public function getSubheading(): ?string
    {
        return 'Ringkasan Project per Customer';
    }

    public function mount(): void
    {
        $this->customers = collect();
        $this->loadOverview();
    }

    public function loadOverview(): void
    {
        try {
            $projects = $this->buildBaseQuery()
                ->with(['customer:id,name'])
                ->withCount(['members'])
                ->withCount(['tickets as open_tickets_count' => fn (Builder $q) => $q
                    ->whereHas('status', fn (Builder $s) => $s->where('is_completed', false)),
                ])
                ->get();

            $this->totalProjects    = $projects->count();
            $this->totalOpenTickets = (int) $projects->sum('open_tickets_count');
            $this->totalRunning     = $projects->where('project_status', ProjectStatus::Running)->count();
            $this->totalPoc         = $projects->where('project_status', ProjectStatus::Poc)->count();
            $this->totalCompleted   = $projects->where('project_status', ProjectStatus::Completed)->count();

            $this->customers      = $this->buildCustomerCards($projects);
            $this->totalCustomers = $this->customers->count();
        } catch (Throwable $e) {
            Log::error('ProjectOverview::loadOverview failed', ['exception' => $e]);

            Notification::make()
                ->title('Failed to refresh data')
                ->danger()
                ->send();
        }
    }

    public function refresh(): void
    {
        $this->loadOverview();

        Notification::make()
            ->title('Data refreshed')
            ->success()
            ->send();
    }

    public function navigateToCustomer(int $customerId): void
    {
        $url = ProjectResource::getUrl('index', [
            'tableFilters[customer_id][value]' => $customerId,
        ]);

        $this->redirect($url);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->action('refresh'),
        ];
    }

    protected function buildBaseQuery(): Builder
    {
        $user = auth()->user();

        $query = Project::query()
            ->whereIn('project_status', [
                ProjectStatus::Poc,
                ProjectStatus::Running,
                ProjectStatus::Completed,
            ]);

        if ($user && method_exists($user, 'hasRole') && ! $user->hasRole(['super_admin', 'admin'])) {
            $query->whereHas('members', fn (Builder $q) => $q->where('user_id', $user->id));
        }

        return $query;
    }

    protected function buildCustomerCards(EloquentCollection $projects): Collection
    {
        $customerProjects = $projects
            ->filter(fn (Project $p) => $p->customer !== null)
            ->groupBy('customer_id');

        if ($customerProjects->isEmpty()) {
            return collect();
        }

        $allProjectIds = $customerProjects->flatMap(fn ($group) => $group->pluck('id'))->all();

        $projectIdToCustomerId = $projects->pluck('customer_id', 'id');

        $uniqueMembersPerCustomer = DB::table('project_members')
            ->whereIn('project_id', $allProjectIds)
            ->select('project_id', 'user_id')
            ->distinct()
            ->get()
            ->groupBy(fn ($row) => $projectIdToCustomerId[$row->project_id] ?? null)
            ->map(fn ($rows) => $rows->pluck('user_id')->unique()->count());

        return $customerProjects
            ->map(function (Collection $group, $customerId) use ($uniqueMembersPerCustomer) {
                /** @var Project $first */
                $first = $group->first();

                return [
                    'customer_id'    => (int) $first->customer_id,
                    'customer_name'  => (string) $first->customer->name,
                    'project_count'  => $group->count(),
                    'running_count'  => $group->where('project_status', ProjectStatus::Running)->count(),
                    'open_tickets'   => (int) $group->sum('open_tickets_count'),
                    'unique_members' => (int) ($uniqueMembersPerCustomer[$customerId] ?? 0),
                ];
            })
            ->sortByDesc('project_count')
            ->values();
    }
}
