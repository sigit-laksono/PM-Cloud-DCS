<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ManagedServices\ManagedServiceResource;
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

class MsOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Managed Services';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'ms-overview';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Managed Services Overview';

    protected string $view = 'filament.pages.ms-overview';

    /**
     * Per-customer aggregation cards rendered on the page.
     *
     * Each entry has the shape:
     * [
     *   'customer_id'    => int,
     *   'customer_name'  => string,
     *   'service_count'  => int,
     *   'open_tickets'   => int,
     *   'unique_members' => int,
     * ]
     */
    public Collection $customers;

    public int $totalManagedServices = 0;

    public int $totalCustomers = 0;

    public int $totalOpenTickets = 0;

    public function getSubheading(): ?string
    {
        return 'Ringkasan Managed Services per Customer';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_managed_service') ?? false;
    }

    public function mount(): void
    {
        $this->customers = collect();
        $this->loadOverview();
    }

    /**
     * Load aggregations for the header stats and per-customer cards.
     *
     * Wraps DB access in try/catch so a transient failure preserves the
     * panel state and surfaces a Filament notification rather than blowing
     * up the page render (Requirement 7.7 / error-handling matrix).
     */
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

            $this->totalManagedServices = $projects->count();
            $this->totalOpenTickets = (int) $projects->sum('open_tickets_count');

            $this->customers = $this->buildCustomerCards($projects);
            $this->totalCustomers = $this->customers->count();
        } catch (Throwable $e) {
            Log::error('MsOverview::loadOverview failed', [
                'exception' => $e,
            ]);

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
        $url = ManagedServiceResource::getUrl('index', [
            'tableFilters[customer_id][value]' => $customerId,
        ]);

        $this->redirect($url);
    }

    /**
     * Header actions: only Refresh per requirement 7.7.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->action('refresh'),
        ];
    }

    /**
     * Build the base Managed-status Project query, applying membership scope
     * for non-admin/super_admin users (Requirements 7.9, 9.3, 9.4).
     */
    protected function buildBaseQuery(): Builder
    {
        $user = auth()->user();

        $query = Project::query()
            ->where('project_status', ProjectStatus::Managed);

        if ($user && method_exists($user, 'hasRole') && ! $user->hasRole(['super_admin', 'admin'])) {
            $query->whereHas('members', fn (Builder $q) => $q->where('user_id', $user->id));
        }

        return $query;
    }

    /**
     * Group the loaded projects by customer and compute the per-customer
     * aggregations rendered as cards. The unique-member count is computed
     * with a single grouped query (no N+1) regardless of customer count.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Project>  $projects
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function buildCustomerCards(EloquentCollection $projects): Collection
    {
        $customerProjects = $projects
            ->filter(fn (Project $p) => $p->customer !== null)
            ->groupBy('customer_id');

        if ($customerProjects->isEmpty()) {
            return collect();
        }

        $allProjectIds = $customerProjects->flatMap(fn ($group) => $group->pluck('id'))->all();

        // Single grouped query: distinct user_id per project_id, mapped to
        // its customer_id via the loaded projects (no N+1).
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
                    'customer_id' => (int) $first->customer_id,
                    'customer_name' => (string) $first->customer->name,
                    'service_count' => $group->count(),
                    'open_tickets' => (int) $group->sum('open_tickets_count'),
                    'unique_members' => (int) ($uniqueMembersPerCustomer[$customerId] ?? 0),
                ];
            })
            ->values();
    }
}
