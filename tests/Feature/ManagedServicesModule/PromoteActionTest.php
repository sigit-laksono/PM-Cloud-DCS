<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Actions\PromoteToManagedAction;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

// Feature: managed-services-module, Property 6: PromoteToManagedAction visibility rule
// Validates: Requirements 4.2, 4.3, 4.4, 4.5
//
// For any combination of (Project, authenticated user), PromoteToManagedAction
// shall be visible iff:
//   (a) project_status ∈ {Running, Completed}, AND
//   (b) user has role super_admin OR admin.
it('shows the promote action iff status is Running/Completed and role is super_admin/admin', function () {
    $statuses = ProjectStatus::cases();
    $roles = ['super_admin', 'admin', 'member'];

    for ($i = 0; $i < 100; $i++) {
        $status = $statuses[array_rand($statuses)];
        $role = $roles[array_rand($roles)];

        $user = User::factory()->create();
        $user->assignRole($role);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = $user->fresh();

        $project = Project::factory()->create([
            'project_status' => $status->value,
        ]);

        $this->actingAs($user);

        $action = PromoteToManagedAction::make()->record($project);

        $statusEligible = in_array(
            $status,
            [ProjectStatus::Running, ProjectStatus::Completed],
            true
        );
        $roleEligible = in_array($role, ['super_admin', 'admin'], true);
        $expectedVisible = $statusEligible && $roleEligible;

        expect($action->isVisible())->toBe(
            $expectedVisible,
            "Iteration {$i}: status={$status->value}, role={$role}, ".
            'expected visible='.($expectedVisible ? 'true' : 'false')
        );

        $project->delete();
        $user->delete();
    }
});

// Feature: managed-services-module, Property 7: PromoteToManagedAction state transition
// Validates: Requirements 4.7
//
// For any Project with project_status ∈ {Running, Completed} whose Promote
// action is invoked (and confirmed), after the action completes:
//   - project_status MUST equal ProjectStatus::Managed, AND
//   - no other persisted attribute (except updated_at, which Eloquent always
//     bumps) shall be modified compared to the pre-action snapshot.
it('transitions project_status to Managed and preserves all other fields', function () {
    $eligibleStatuses = [ProjectStatus::Running, ProjectStatus::Completed];

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $status = $eligibleStatuses[array_rand($eligibleStatuses)];

        $project = Project::factory()->create([
            'project_status' => $status->value,
        ]);

        // Snapshot all persisted attributes BEFORE the action runs. We use
        // getRawOriginal() so that enum/date casts don't interfere with the
        // equality check after the model is refreshed.
        $snapshot = $project->getRawOriginal();

        PromoteToManagedAction::make()
            ->record($project)
            ->call();

        $project->refresh();

        expect($project->project_status)->toBe(
            ProjectStatus::Managed,
            "Iteration {$i}: project #{$project->id} did not transition to Managed (was {$status->value})"
        );

        $afterRaw = $project->getRawOriginal();

        // Every other column must remain identical to the snapshot. We allow
        // updated_at (and project_status itself) to differ.
        $ignored = ['project_status', 'updated_at'];

        foreach ($snapshot as $column => $value) {
            if (in_array($column, $ignored, true)) {
                continue;
            }

            expect($afterRaw[$column] ?? null)->toBe(
                $value,
                "Iteration {$i}: column [{$column}] mutated unexpectedly during promote"
            );
        }

        $project->delete();
    }
});
