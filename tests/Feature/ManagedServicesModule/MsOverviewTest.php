<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Pages\MsOverview;
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

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
});

/**
 * Build a randomized but coherent dataset of customers, projects (mixed
 * statuses with at least one Managed), ticket statuses, tickets, and
 * project members.
 *
 * Returns the raw counts/expectations that a downstream property test can
 * compare against the page's reported aggregates.
 *
 * @return array{
 *   projects: \Illuminate\Support\Collection<int, Project>,
 *   managed_count: int,
 *   customers_with_managed: int,
 *   open_tickets: int,
 *   per_customer: \Illuminate\Support\Collection<int, array<string, mixed>>,
 * }
 */
function makeMsOverviewDataset(int $minProjects = 4, int $maxProjects = 12): array
{
    $statuses = ProjectStatus::cases();
    $totalProjects = fake()->numberBetween($minProjects, $maxProjects);

    // Pre-create a small pool of customers so multiple Managed projects can
    // share a customer (exercising the groupBy logic).
    $customerCount = fake()->numberBetween(1, 4);
    $customers = collect();
    for ($c = 0; $c < $customerCount; $c++) {
        $customers->push(Customer::factory()->create());
    }

    $projects = collect();

    for ($i = 0; $i < $totalProjects; $i++) {
        $status = $statuses[array_rand($statuses)];
        // ~40% null customer to stress the customer != null filter.
        $customer = fake()->boolean(60) ? $customers->random() : null;

        $projects->push(Project::factory()->create([
            'project_status' => $status->value,
            'customer_id' => $customer?->id,
        ]));
    }

    // Guarantee at least one Managed project tied to a customer so the
    // per-customer aggregation is non-trivial.
    $projects->push(Project::factory()->create([
        'project_status' => ProjectStatus::Managed->value,
        'customer_id' => $customers->random()->id,
    ]));

    // For each project, create a small set of ticket statuses (some
    // completed, some not), and a random number of tickets distributed
    // across those statuses.
    foreach ($projects as $project) {
        $statusCount = fake()->numberBetween(1, 4);
        $createdStatuses = collect();
        for ($s = 0; $s < $statusCount; $s++) {
            $createdStatuses->push(TicketStatus::create([
                'project_id' => $project->id,
                'name' => fake()->unique()->words(2, true),
                'sort_order' => $s,
                'color' => fake()->hexColor(),
                'is_completed' => fake()->boolean(40),
            ]));
        }

        $ticketCount = fake()->numberBetween(0, 6);
        for ($t = 0; $t < $ticketCount; $t++) {
            $ticketStatus = $createdStatuses->random();
            Ticket::create([
                'project_id' => $project->id,
                'ticket_status_id' => $ticketStatus->id,
                'name' => fake()->sentence(3),
                'description' => fake()->sentence(),
            ]);
        }

        // Random membership: attach 0..3 distinct users.
        $memberCount = fake()->numberBetween(0, 3);
        if ($memberCount > 0) {
            $memberIds = User::factory()->count($memberCount)->create()->pluck('id')->all();
            $project->members()->attach($memberIds);
        }
    }

    // Compute baseline expectations directly from the database, mirroring
    // the requirement definitions.
    $managedProjects = Project::query()
        ->where('project_status', ProjectStatus::Managed->value)
        ->get();

    $managedIds = $managedProjects->pluck('id');

    $customersWithManaged = $managedProjects
        ->whereNotNull('customer_id')
        ->pluck('customer_id')
        ->unique()
        ->count();

    $openTickets = Ticket::query()
        ->whereIn('project_id', $managedIds)
        ->whereHas('status', fn ($q) => $q->where('is_completed', false))
        ->count();

    // Per-customer baseline: only customers that own at least one Managed
    // project AND whose customer record is loadable (customer != null).
    $perCustomer = $managedProjects
        ->whereNotNull('customer_id')
        ->groupBy('customer_id')
        ->map(function ($group, $customerId) {
            $serviceCount = $group->count();
            $projectIds = $group->pluck('id');

            $openTickets = Ticket::query()
                ->whereIn('project_id', $projectIds)
                ->whereHas('status', fn ($q) => $q->where('is_completed', false))
                ->count();

            $uniqueMembers = DB::table('project_members')
                ->whereIn('project_id', $projectIds)
                ->distinct('user_id')
                ->count('user_id');

            $customer = Customer::find($customerId);

            return [
                'customer_id' => (int) $customerId,
                'customer_name' => $customer?->name,
                'service_count' => $serviceCount,
                'open_tickets' => $openTickets,
                'unique_members' => (int) $uniqueMembers,
            ];
        })
        ->values();

    return [
        'projects' => $projects,
        'managed_count' => $managedProjects->count(),
        'customers_with_managed' => $customersWithManaged,
        'open_tickets' => $openTickets,
        'per_customer' => $perCustomer,
    ];
}

/**
 * Reset all tables touched by makeMsOverviewDataset() so each iteration
 * starts from a clean slate.
 */
function resetMsOverviewState(): void
{
    DB::table('project_members')->delete();
    Ticket::query()->delete();
    TicketStatus::query()->delete();
    Project::query()->delete();
    Customer::query()->delete();
    // Users that are not the seeded super_admin are recreated each iteration.
    User::query()->whereDoesntHave('roles', fn ($q) => $q->where('name', 'super_admin'))
        ->delete();
}

// Feature: managed-services-module, Property 14: MsOverview header aggregates equal database aggregates
// Validates: Requirements 7.2
//
// For any dataset of Project/Customer/Ticket/TicketStatus rows, the three
// header metrics on MsOverview shall equal the aggregates computed
// independently against the underlying tables.
it('header aggregates match independent database aggregates', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $expected = makeMsOverviewDataset();

        $component = Livewire::test(MsOverview::class);

        expect($component->get('totalManagedServices'))->toBe(
            $expected['managed_count'],
            "Iteration {$i}: totalManagedServices mismatch"
        );

        expect($component->get('totalCustomers'))->toBe(
            $expected['customers_with_managed'],
            "Iteration {$i}: totalCustomers mismatch"
        );

        expect($component->get('totalOpenTickets'))->toBe(
            $expected['open_tickets'],
            "Iteration {$i}: totalOpenTickets mismatch"
        );

        resetMsOverviewState();
    }
});

// Feature: managed-services-module, Property 15: MsOverview per-customer card aggregates
// Validates: Requirements 7.3, 7.4
//
// For each customer with at least one Managed project, the rendered card
// shall expose customer_name, service_count, open_tickets, unique_members
// matching independent baselines. No card is rendered for customers
// without a Managed project.
it('renders per-customer cards with aggregates matching the database', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $expected = makeMsOverviewDataset();

        $component = Livewire::test(MsOverview::class);

        /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $cards */
        $cards = $component->get('customers');

        // Same number of cards as customers with >=1 Managed project.
        expect($cards)->toHaveCount(
            $expected['per_customer']->count(),
            "Iteration {$i}: number of customer cards mismatch"
        );

        $cardsByCustomerId = $cards->keyBy('customer_id');

        foreach ($expected['per_customer'] as $expectedCard) {
            $customerId = $expectedCard['customer_id'];

            expect($cardsByCustomerId->has($customerId))->toBeTrue(
                "Iteration {$i}: missing card for customer #{$customerId}"
            );

            $card = $cardsByCustomerId[$customerId];

            expect($card['customer_name'])->toBe(
                $expectedCard['customer_name'],
                "Iteration {$i}: card name mismatch for customer #{$customerId}"
            );

            expect($card['service_count'])->toBe(
                $expectedCard['service_count'],
                "Iteration {$i}: service_count mismatch for customer #{$customerId}"
            );

            expect($card['open_tickets'])->toBe(
                $expectedCard['open_tickets'],
                "Iteration {$i}: open_tickets mismatch for customer #{$customerId}"
            );

            expect($card['unique_members'])->toBe(
                $expectedCard['unique_members'],
                "Iteration {$i}: unique_members mismatch for customer #{$customerId}"
            );
        }

        // No card rendered for a customer without any Managed project.
        $expectedCustomerIds = $expected['per_customer']->pluck('customer_id')->all();
        foreach ($cards as $card) {
            expect($card['customer_id'])->toBeIn(
                $expectedCustomerIds,
                "Iteration {$i}: card for customer #{$card['customer_id']} ".
                'rendered despite no Managed project'
            );
        }

        resetMsOverviewState();
    }
});

// Feature: managed-services-module, Property 16: MsOverview avoids N+1 queries
// Validates: Requirements 7.6
//
// For datasets of growing size (10, 50, 100 Managed projects across
// multiple customers), the query count of MsOverview::loadOverview() shall
// be bounded by a small constant (max delta <= 2 across sizes).
it('keeps query count bounded as the Managed dataset grows (no N+1)', function () {
    $this->actingAs($this->superAdmin);

    $sizes = [10, 50, 100];
    $queryCounts = [];

    foreach ($sizes as $size) {
        // Seed a dataset of $size Managed projects spread across 5 customers,
        // each with 2 ticket statuses and a few tickets. The cardinality
        // varies but the eager-loading strategy should produce a constant
        // number of queries.
        $customerCount = 5;
        $customers = Customer::factory()->count($customerCount)->create();

        for ($p = 0; $p < $size; $p++) {
            $project = Project::factory()->create([
                'project_status' => ProjectStatus::Managed->value,
                'customer_id' => $customers->random()->id,
            ]);

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

            for ($t = 0; $t < 3; $t++) {
                Ticket::create([
                    'project_id' => $project->id,
                    'ticket_status_id' => $statusOpen->id,
                    'name' => fake()->sentence(3),
                ]);
            }

            $memberIds = User::factory()->count(2)->create()->pluck('id')->all();
            $project->members()->attach($memberIds);
        }

        // Boot the page once via Livewire so all bindings/middleware are in
        // place, then measure ONLY the loadOverview() invocation.
        $component = Livewire::test(MsOverview::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('loadOverview');
        $queryCounts[$size] = count(DB::getQueryLog());
        DB::disableQueryLog();

        resetMsOverviewState();
    }

    // The maximum spread between any two sizes must be small (<= 2). This is
    // the defining property of an N+1-free implementation: the query count
    // depends on the number of relations loaded, not on N.
    $min = min($queryCounts);
    $max = max($queryCounts);
    $delta = $max - $min;

    expect($delta)->toBeLessThanOrEqual(
        2,
        'Query count grows with dataset size: '.json_encode($queryCounts)
    );
});

// Feature: managed-services-module, Property 17: MsOverview refresh reflects current database state
// Validates: Requirements 7.8
//
// Mount MsOverview, mutate the database (insert/update/delete a Managed
// project, ticket, or member), call refresh; the new state shall match a
// fresh mount against the post-mutation database.
it('refresh() yields the same state as a fresh mount after database mutations', function () {
    $this->actingAs($this->superAdmin);

    $mutations = ['insert', 'update', 'delete', 'ticket', 'member'];

    for ($i = 0; $i < 100; $i++) {
        // Seed a small initial dataset.
        makeMsOverviewDataset(2, 6);

        $component = Livewire::test(MsOverview::class);

        // Apply a single random mutation.
        $mutation = $mutations[array_rand($mutations)];
        applyMsOverviewMutation($mutation);

        $component->call('refresh');

        // Compute the post-mutation expectation by mounting a fresh
        // component (since loadOverview is the same code path, this gives
        // us the source of truth derived directly from the new DB state).
        $fresh = Livewire::test(MsOverview::class);

        expect($component->get('totalManagedServices'))->toBe(
            $fresh->get('totalManagedServices'),
            "Iteration {$i} ({$mutation}): totalManagedServices drift"
        );

        expect($component->get('totalCustomers'))->toBe(
            $fresh->get('totalCustomers'),
            "Iteration {$i} ({$mutation}): totalCustomers drift"
        );

        expect($component->get('totalOpenTickets'))->toBe(
            $fresh->get('totalOpenTickets'),
            "Iteration {$i} ({$mutation}): totalOpenTickets drift"
        );

        $refreshedCards = $component->get('customers')->map(fn ($c) => [
            'customer_id' => $c['customer_id'],
            'service_count' => $c['service_count'],
            'open_tickets' => $c['open_tickets'],
            'unique_members' => $c['unique_members'],
        ])->sortBy('customer_id')->values()->all();

        $freshCards = $fresh->get('customers')->map(fn ($c) => [
            'customer_id' => $c['customer_id'],
            'service_count' => $c['service_count'],
            'open_tickets' => $c['open_tickets'],
            'unique_members' => $c['unique_members'],
        ])->sortBy('customer_id')->values()->all();

        expect($refreshedCards)->toBe(
            $freshCards,
            "Iteration {$i} ({$mutation}): per-customer cards drift after refresh"
        );

        resetMsOverviewState();
    }
});

/**
 * Apply a single-flavor mutation to the database for Property 17.
 *
 * Keeps the mutations small and focused so a property failure can be
 * traced back to the exact kind of change that broke refresh parity.
 */
function applyMsOverviewMutation(string $kind): void
{
    switch ($kind) {
        case 'insert':
            // Insert a brand-new Managed project (with a customer).
            $customer = Customer::factory()->create();
            $project = Project::factory()->create([
                'project_status' => ProjectStatus::Managed->value,
                'customer_id' => $customer->id,
            ]);
            $status = TicketStatus::create([
                'project_id' => $project->id,
                'name' => 'New Status',
                'sort_order' => 0,
                'color' => '#3B82F6',
                'is_completed' => false,
            ]);
            Ticket::create([
                'project_id' => $project->id,
                'ticket_status_id' => $status->id,
                'name' => 'Inserted ticket',
            ]);
            $project->members()->attach(User::factory()->create()->id);
            break;

        case 'update':
            // Promote an existing non-Managed project to Managed (or demote
            // a Managed back to Running, alternately).
            $candidate = Project::query()
                ->where('project_status', ProjectStatus::Running->value)
                ->inRandomOrder()
                ->first();

            if ($candidate !== null) {
                $candidate->update(['project_status' => ProjectStatus::Managed->value]);
            } else {
                $managed = Project::query()
                    ->where('project_status', ProjectStatus::Managed->value)
                    ->inRandomOrder()
                    ->first();
                $managed?->update(['project_status' => ProjectStatus::Running->value]);
            }
            break;

        case 'delete':
            // Delete a random Managed project (and its tickets / members
            // via the FK cascade defined in the migration).
            $managed = Project::query()
                ->where('project_status', ProjectStatus::Managed->value)
                ->inRandomOrder()
                ->first();
            $managed?->delete();
            break;

        case 'ticket':
            // Toggle a ticket status between completed/open to flip the
            // open_tickets aggregate.
            $status = TicketStatus::query()->inRandomOrder()->first();
            if ($status !== null) {
                $status->update(['is_completed' => ! $status->is_completed]);
            }
            break;

        case 'member':
            // Attach a fresh user to a random Managed project.
            $managed = Project::query()
                ->where('project_status', ProjectStatus::Managed->value)
                ->inRandomOrder()
                ->first();

            if ($managed !== null) {
                $managed->members()->syncWithoutDetaching([User::factory()->create()->id]);
            }
            break;
    }
}
