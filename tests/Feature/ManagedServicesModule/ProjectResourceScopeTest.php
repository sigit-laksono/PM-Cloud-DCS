<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Pages\ProjectBoard;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
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
 * Generate a randomized dataset of projects with a mixed distribution of all
 * four ProjectStatus values. Returns the list of created projects so callers
 * can compute expectations from the same source-of-truth.
 *
 * @return \Illuminate\Support\Collection<int, Project>
 */
function makeMixedProjectDataset(): \Illuminate\Support\Collection
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

    // Ensure the dataset always contains at least one Managed record so the
    // exclusion property is non-trivial.
    $projects->push(Project::factory()->create([
        'project_status' => ProjectStatus::Managed->value,
    ]));

    return $projects;
}

// Feature: managed-services-module, Property 2: ProjectResource excludes Managed records
// Validates: Requirements 2.1
//
// For any dataset of Project rows containing a mix of statuses, the query
// produced by ProjectResource::getEloquentQuery() must not contain any record
// whose project_status equals ProjectStatus::Managed.
it('never returns Managed projects from ProjectResource::getEloquentQuery()', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        makeMixedProjectDataset();

        $rows = ProjectResource::getEloquentQuery()->get();

        // Property: no row in the resource query may be a Managed record.
        foreach ($rows as $row) {
            expect($row->project_status)
                ->not->toBe(
                    ProjectStatus::Managed,
                    "Iteration {$i}: project #{$row->id} with status Managed leaked into ProjectResource"
                );
        }

        Project::query()->delete();
    }
});

// Feature: managed-services-module, Property 4: Direct URL to Managed project via ProjectResource returns 404
// Validates: Requirements 2.3
//
// For any Project whose project_status equals ProjectStatus::Managed,
// hitting the ProjectResource view URL must return HTTP 404.
it('returns 404 when accessing a Managed project via the ProjectResource view URL', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $project = Project::factory()->create([
            'project_status' => ProjectStatus::Managed->value,
        ]);

        $url = ProjectResource::getUrl('view', ['record' => $project]);

        $this->get($url)->assertNotFound();

        $project->delete();
    }
});

// Feature: managed-services-module, Property 3: ProjectBoard dropdown excludes Managed records
// Validates: Requirements 2.2
//
// For any dataset of Project rows, the project list rendered as the
// ProjectBoard selector for any user shall not include a project whose
// project_status equals ProjectStatus::Managed.
it('never includes Managed projects in the ProjectBoard dropdown', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        makeMixedProjectDataset();

        /** @var \Livewire\Features\SupportTesting\Testable $component */
        $component = Livewire::test(ProjectBoard::class);

        /** @var \Illuminate\Support\Collection<int, Project> $projects */
        $projects = $component->get('projects');

        foreach ($projects as $project) {
            expect($project->project_status)
                ->not->toBe(
                    ProjectStatus::Managed,
                    "Iteration {$i}: Managed project #{$project->id} leaked into ProjectBoard dropdown"
                );
        }

        Project::query()->delete();
    }
});
