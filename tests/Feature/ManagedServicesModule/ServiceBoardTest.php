<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Pages\ServiceBoard;
use App\Models\Project;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * Generate a randomized dataset of Project rows with a mixed distribution of
 * all four ProjectStatus values, guaranteeing at least one Managed record.
 *
 * @return \Illuminate\Support\Collection<int, Project>
 */
function makeMixedServiceBoardDataset(): \Illuminate\Support\Collection
{
    $statuses = ProjectStatus::cases();
    $total = fake()->numberBetween(4, 12);
    $projects = collect();

    for ($i = 0; $i < $total; $i++) {
        $status = $statuses[array_rand($statuses)];
        $projects->push(Project::factory()->create([
            'project_status' => $status->value,
        ]));
    }

    // Guarantee at least one Managed project so the dropdown property is
    // non-trivial.
    $projects->push(Project::factory()->create([
        'project_status' => ProjectStatus::Managed->value,
    ]));

    return $projects;
}

// Feature: managed-services-module, Property 10: ServiceBoard dropdown contains only Managed projects
// Validates: Requirements 6.2
//
// For any dataset of Project rows containing a mix of statuses, the project
// list rendered on ServiceBoard for any user shall contain only records
// whose project_status equals ProjectStatus::Managed.
it('only includes Managed projects in the ServiceBoard dropdown', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $dataset = makeMixedServiceBoardDataset();

        /** @var \Livewire\Features\SupportTesting\Testable $component */
        $component = Livewire::test(ServiceBoard::class);

        /** @var \Illuminate\Support\Collection<int, Project> $projects */
        $projects = $component->get('projects');

        // Property: every project rendered MUST be Managed.
        foreach ($projects as $project) {
            expect($project->project_status)
                ->toBe(
                    ProjectStatus::Managed,
                    "Iteration {$i}: project #{$project->id} with status ".
                    ($project->project_status?->value ?? 'null').
                    ' leaked into ServiceBoard dropdown'
                );
        }

        // Sanity: the count must equal the number of Managed projects in the
        // dataset, ensuring no Managed records were accidentally dropped.
        $expectedManagedCount = $dataset->where(
            'project_status',
            ProjectStatus::Managed
        )->count();

        expect($projects)->toHaveCount($expectedManagedCount);

        Project::query()->delete();
    }
});

// Feature: managed-services-module, Property 11: ServiceBoard columns mirror project ticket statuses
// Validates: Requirements 6.3
//
// For any Project with project_status = Managed selected on ServiceBoard, the
// kanban columns rendered shall correspond exactly (in count, identity, and
// ordering by sort_order) to the project's ticketStatuses() relation.
it('renders kanban columns matching the project ticketStatuses ordered by sort_order', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $project = Project::factory()->create([
            'project_status' => ProjectStatus::Managed->value,
        ]);

        // Random number of ticket statuses (1..8) with random non-unique
        // sort_order values to exercise the sorting contract.
        $statusCount = fake()->numberBetween(1, 8);
        $createdStatuses = collect();

        for ($s = 0; $s < $statusCount; $s++) {
            $createdStatuses->push(TicketStatus::create([
                'project_id' => $project->id,
                'name' => fake()->unique()->words(2, true),
                'sort_order' => fake()->numberBetween(0, 50),
                'color' => fake()->hexColor(),
                'is_completed' => fake()->boolean(20),
            ]));
        }

        /** @var \Livewire\Features\SupportTesting\Testable $component */
        $component = Livewire::test(ServiceBoard::class, ['project_id' => $project->id]);

        /** @var \Illuminate\Support\Collection<int, TicketStatus> $columns */
        $columns = $component->instance()->ticketStatuses;

        // Identity + count: the rendered column ids must equal the ids of the
        // project's ticketStatuses().
        $renderedIds = $columns->pluck('id')->all();
        $expectedIds = $project
            ->ticketStatuses()
            ->orderBy('sort_order')
            ->pluck('id')
            ->all();

        expect($renderedIds)->toBe(
            $expectedIds,
            "Iteration {$i}: ServiceBoard columns identity/order mismatch for project #{$project->id}"
        );

        // Ordering: sort_order values must be non-decreasing across rendered
        // columns (sorted asc by sort_order).
        $previous = PHP_INT_MIN;
        foreach ($columns as $column) {
            expect($column->sort_order)->toBeGreaterThanOrEqual(
                $previous,
                "Iteration {$i}: column #{$column->id} sort_order {$column->sort_order} ".
                "violates ascending sort_order ordering (previous was {$previous})"
            );
            $previous = (int) $column->sort_order;
        }

        // Count parity with the relation as a whole.
        expect($columns)->toHaveCount($createdStatuses->count());

        Project::query()->delete();
    }
});
