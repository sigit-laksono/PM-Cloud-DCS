<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Actions\DemoteFromManagedAction;
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

// Feature: managed-services-module, Property 8: DemoteFromManagedAction visibility rule
// Validates: Requirements 5.2, 5.3
//
// For any combination of (Project with status Managed, authenticated user),
// DemoteFromManagedAction shall be visible iff the user has role super_admin.
//
// Note: per design, the action also requires `manage_managed_service`. The
// RoleSeeder grants that permission to super_admin and admin only, so among
// the three real roles, only super_admin satisfies BOTH gates simultaneously.
// The visibility property therefore reduces to "role === super_admin".
it('shows the demote action iff the user has role super_admin', function () {
    $roles = ['super_admin', 'admin', 'member'];

    for ($i = 0; $i < 100; $i++) {
        $role = $roles[array_rand($roles)];

        $user = User::factory()->create();
        $user->assignRole($role);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = $user->fresh();

        $project = Project::factory()->create([
            'project_status' => ProjectStatus::Managed->value,
        ]);

        $this->actingAs($user);

        $action = DemoteFromManagedAction::make()->record($project);

        $expectedVisible = $role === 'super_admin';

        expect($action->isVisible())->toBe(
            $expectedVisible,
            "Iteration {$i}: role={$role}, ".
            'expected visible='.($expectedVisible ? 'true' : 'false')
        );

        $project->delete();
        $user->delete();
    }
});

// Feature: managed-services-module, Property 9: DemoteFromManagedAction state transition
// Validates: Requirements 5.5
//
// For any Project with project_status = Managed whose Demote action is
// invoked (and confirmed), after the action completes:
//   - project_status MUST equal ProjectStatus::Running, AND
//   - no other persisted attribute (except updated_at) shall be modified.
it('transitions project_status from Managed back to Running and preserves all other fields', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $superAdmin = $superAdmin->fresh();
    $this->actingAs($superAdmin);

    for ($i = 0; $i < 100; $i++) {
        $project = Project::factory()->create([
            'project_status' => ProjectStatus::Managed->value,
        ]);

        // Snapshot all persisted attributes BEFORE the action runs.
        $snapshot = $project->getRawOriginal();

        DemoteFromManagedAction::make()
            ->record($project)
            ->call();

        $project->refresh();

        expect($project->project_status)->toBe(
            ProjectStatus::Running,
            "Iteration {$i}: project #{$project->id} did not transition to Running"
        );

        $afterRaw = $project->getRawOriginal();

        // Every column except project_status and updated_at must be identical.
        $ignored = ['project_status', 'updated_at'];

        foreach ($snapshot as $column => $value) {
            if (in_array($column, $ignored, true)) {
                continue;
            }

            expect($afterRaw[$column] ?? null)->toBe(
                $value,
                "Iteration {$i}: column [{$column}] mutated unexpectedly during demote"
            );
        }

        $project->delete();
    }
});
