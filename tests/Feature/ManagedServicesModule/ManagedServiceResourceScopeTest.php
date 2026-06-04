<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use App\Filament\Resources\ManagedServices\Pages\ListManagedServices;
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
 * Generate a randomized dataset of Project rows with a mixed distribution of
 * all four ProjectStatus values, guaranteeing at least one Managed record
 * per iteration so the inclusion property is non-trivial.
 *
 * Note: a similarly named helper exists in ProjectResourceScopeTest.php; we
 * use a distinct name here to avoid clashing with Pest top-level helpers.
 *
 * @return \Illuminate\Support\Collection<int, Project>
 */
function makeMixedManagedServiceDataset(): \Illuminate\Support\Collection
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

    // Guarantee at least one Managed project so the resource query is non-empty.
    $projects->push(Project::factory()->create([
        'project_status' => ProjectStatus::Managed->value,
    ]));

    return $projects;
}

// Feature: managed-services-module, Property 5: ManagedServiceResource includes only Managed records
// Validates: Requirements 3.1
//
// For any dataset of Project rows containing a mix of statuses, the query
// produced by ManagedServiceResource::getEloquentQuery() shall return
// exclusively records whose project_status equals ProjectStatus::Managed.
it('returns only Managed projects from ManagedServiceResource::getEloquentQuery()', function () {
    $this->actingAs($this->superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $dataset = makeMixedManagedServiceDataset();

        $rows = ManagedServiceResource::getEloquentQuery()->get();

        // Primary property: every returned row MUST be a Managed project.
        foreach ($rows as $row) {
            expect($row->project_status)
                ->toBe(
                    ProjectStatus::Managed,
                    "Iteration {$i}: project #{$row->id} with status ".
                    ($row->project_status?->value ?? 'null').
                    ' leaked into ManagedServiceResource'
                );
        }

        // Sanity: row count must equal the number of seeded Managed projects
        // for this iteration, ensuring no Managed records were accidentally
        // dropped either.
        $expectedManagedCount = $dataset->where(
            'project_status',
            ProjectStatus::Managed
        )->count();

        expect($rows)->toHaveCount($expectedManagedCount);

        Project::query()->delete();
    }
});

// Feature: managed-services-module, Property 19: Edit action visibility on ManagedServiceResource
// Validates: Requirements 8.7
//
// For any combination of (Managed Project, authenticated user), the table-row
// EditAction on ManagedServiceResource shall be visible if and only if the
// user has permission `manage_managed_service`.
//
// The visibility closure depends only on the current user's permission, not
// on the record. Spatie role-inherited permissions cannot be revoked at the
// user level, so for super_admin/admin the user-level revoke is a no-op and
// the test computes the expected visibility from the *actual* `can()` value
// after role + direct permission mutation.
it('shows the EditAction iff the user has manage_managed_service permission', function () {
    $roles = ['super_admin', 'admin', 'member'];

    for ($i = 0; $i < 100; $i++) {
        $role = $roles[array_rand($roles)];
        $shouldGrantManagePermission = (bool) random_int(0, 1);

        $user = User::factory()->create();
        $user->assignRole($role);

        if ($shouldGrantManagePermission) {
            $user->givePermissionTo('manage_managed_service');
        } else {
            $user->revokePermissionTo('manage_managed_service');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = $user->fresh();

        $project = Project::factory()->create([
            'project_status' => ProjectStatus::Managed->value,
        ]);

        // For non-super_admin/admin roles, ManagedServiceResource scopes the
        // query by project_members. Attach the user so the row is visible in
        // the table; otherwise assertTable* would fail on a missing record.
        if (! $user->hasRole(['super_admin', 'admin'])) {
            $project->members()->attach($user->id);
        }

        $this->actingAs($user);

        $expectedVisible = $user->can('manage_managed_service');

        $component = Livewire::test(ListManagedServices::class);

        if ($expectedVisible) {
            $component->assertTableActionVisible('edit', $project);
        } else {
            $component->assertTableActionHidden('edit', $project);
        }

        $project->members()->detach();
        $project->delete();
    }
});
