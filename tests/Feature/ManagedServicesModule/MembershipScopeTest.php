<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Pages\MsOverview;
use App\Filament\Pages\ServiceBoard;
use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

/**
 * Build a randomized dataset of Managed projects with random membership
 * attachments. Returns the projects together with the ids the given user
 * is a member of, so the test can compare resource scoping against an
 * independently computed baseline.
 *
 * @return array{
 *   managed_projects: \Illuminate\Support\Collection<int, Project>,
 *   member_project_ids: int[],
 * }
 */
function makeMembershipDataset(User $user): array
{
    // Pool of customers so multiple Managed projects can share a customer
    // (exercising the per-customer aggregation in MsOverview).
    $customerCount = fake()->numberBetween(1, 4);
    $customers = collect();
    for ($c = 0; $c < $customerCount; $c++) {
        $customers->push(Customer::factory()->create());
    }

    // Mix all four statuses to ensure non-Managed records do not leak into
    // any MS module entry.
    $statuses = ProjectStatus::cases();
    $totalProjects = fake()->numberBetween(4, 10);
    $managedProjects = collect();

    for ($i = 0; $i < $totalProjects; $i++) {
        $status = $statuses[array_rand($statuses)];
        $project = Project::factory()->create([
            'project_status' => $status->value,
            'customer_id' => $customers->random()->id,
        ]);

        if ($status === ProjectStatus::Managed) {
            $managedProjects->push($project);
        }
    }

    // Guarantee at least two Managed projects so the membership subset is
    // strictly smaller than "all Managed" with non-trivial probability.
    while ($managedProjects->count() < 2) {
        $managedProjects->push(Project::factory()->create([
            'project_status' => ProjectStatus::Managed->value,
            'customer_id' => $customers->random()->id,
        ]));
    }

    // For every project (Managed or not) seed ticket statuses + tickets so
    // the open-tickets aggregate is non-trivial. Some statuses are
    // completed, some open.
    foreach (Project::query()->get() as $project) {
        $statusOpen = TicketStatus::create([
            'project_id' => $project->id,
            'name' => 'Open',
            'sort_order' => 0,
            'color' => '#3B82F6',
            'is_completed' => false,
        ]);
        TicketStatus::create([
            'project_id' => $project->id,
            'name' => 'Done',
            'sort_order' => 1,
            'color' => '#10B981',
            'is_completed' => true,
        ]);

        $ticketCount = fake()->numberBetween(0, 4);
        for ($t = 0; $t < $ticketCount; $t++) {
            Ticket::create([
                'project_id' => $project->id,
                'ticket_status_id' => $statusOpen->id,
                'name' => fake()->sentence(3),
            ]);
        }
    }

    // Randomly attach the user to a strict subset of Managed projects.
    $memberProjectIds = $managedProjects
        ->random(fake()->numberBetween(1, $managedProjects->count()))
        ->pluck('id')
        ->all();

    foreach ($memberProjectIds as $projectId) {
        DB::table('project_members')->insert([
            'project_id' => $projectId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // Attach a few unrelated members to each Managed project so the
    // unique_members aggregation is non-trivial regardless of the test user.
    foreach ($managedProjects as $project) {
        $extras = User::factory()->count(fake()->numberBetween(0, 2))->create()->pluck('id')->all();
        foreach ($extras as $extraId) {
            DB::table('project_members')->insertOrIgnore([
                'project_id' => $project->id,
                'user_id' => $extraId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    return [
        'managed_projects' => $managedProjects,
        'member_project_ids' => $memberProjectIds,
    ];
}

/**
 * Reset all tables touched by makeMembershipDataset() between iterations.
 */
function resetMembershipState(): void
{
    DB::table('project_members')->delete();
    Ticket::query()->delete();
    TicketStatus::query()->delete();
    Project::query()->delete();
    Customer::query()->delete();
    // Drop all users except the seeded role-bearing ones; the test creates
    // role-bearing users at the top of each iteration.
    User::query()->delete();
}

// Feature: managed-services-module, Property 13: Membership scope on Managed Services entries
// Validates: Requirements 6.7, 7.9, 9.1, 9.2, 9.3, 9.4
//
// For role `member`, ManagedServiceResource::getEloquentQuery(),
// ServiceBoard dropdown, and MsOverview aggregates must contain only the
// Managed projects in which the user is attached via project_members. For
// `super_admin` and `admin`, all Managed projects must be visible.
it('scopes Managed Services entries by project_members for members and shows all for admins', function () {
    $roles = ['super_admin', 'admin', 'member'];

    for ($i = 0; $i < 100; $i++) {
        $role = $roles[array_rand($roles)];

        $user = User::factory()->create();
        $user->assignRole($role);
        // Members need both view_managed_service (granted by RoleSeeder) and
        // role membership; admin/super_admin are already privileged. We do
        // not mutate permissions further to keep the property focused on
        // membership scope.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = $user->fresh();

        $dataset = makeMembershipDataset($user);

        /** @var \Illuminate\Support\Collection<int, Project> $managedProjects */
        $managedProjects = $dataset['managed_projects'];
        $memberProjectIds = $dataset['member_project_ids'];

        // The expected visible Managed project ids for this user/role.
        $expectedIds = $user->hasRole(['super_admin', 'admin'])
            ? $managedProjects->pluck('id')->sort()->values()->all()
            : collect($memberProjectIds)->sort()->values()->all();

        $this->actingAs($user);

        // ---- 1. ManagedServiceResource::getEloquentQuery() ----
        $resourceIds = ManagedServiceResource::getEloquentQuery()
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        expect($resourceIds)->toBe(
            $expectedIds,
            "Iteration {$i} ({$role}): ManagedServiceResource scope mismatch. ".
            'Expected: '.json_encode($expectedIds).' Got: '.json_encode($resourceIds)
        );

        // ---- 2. ServiceBoard dropdown ----
        $component = Livewire::test(ServiceBoard::class);
        $boardIds = $component->get('projects')->pluck('id')->sort()->values()->all();

        expect($boardIds)->toBe(
            $expectedIds,
            "Iteration {$i} ({$role}): ServiceBoard dropdown scope mismatch. ".
            'Expected: '.json_encode($expectedIds).' Got: '.json_encode($boardIds)
        );

        // ---- 3. MsOverview aggregates ----
        $overview = Livewire::test(MsOverview::class);

        // Independent baseline computed from the visible project ids.
        $visibleProjects = Project::query()
            ->whereIn('id', $expectedIds)
            ->get();

        $expectedTotalManagedServices = $visibleProjects->count();
        $expectedTotalCustomers = $visibleProjects
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique()
            ->count();
        $expectedTotalOpenTickets = Ticket::query()
            ->whereIn('project_id', $expectedIds)
            ->whereHas('status', fn ($q) => $q->where('is_completed', false))
            ->count();

        expect($overview->get('totalManagedServices'))->toBe(
            $expectedTotalManagedServices,
            "Iteration {$i} ({$role}): totalManagedServices mismatch"
        );

        expect($overview->get('totalCustomers'))->toBe(
            $expectedTotalCustomers,
            "Iteration {$i} ({$role}): totalCustomers mismatch"
        );

        expect($overview->get('totalOpenTickets'))->toBe(
            $expectedTotalOpenTickets,
            "Iteration {$i} ({$role}): totalOpenTickets mismatch"
        );

        // Per-customer card customer_ids must equal the distinct customer
        // ids of the visible Managed projects (with non-null customer).
        $expectedCustomerIds = $visibleProjects
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $cardCustomerIds = $overview->get('customers')
            ->pluck('customer_id')
            ->sort()
            ->values()
            ->all();

        expect($cardCustomerIds)->toBe(
            $expectedCustomerIds,
            "Iteration {$i} ({$role}): MsOverview card customer_ids mismatch"
        );

        auth()->logout();
        resetMembershipState();
    }
});
