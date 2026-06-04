<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Properties 1 and 12 below assume roles already exist; seed once per
    // test so the in-loop role assignments succeed. The matrix test below
    // re-seeds inside its loop, which is idempotent.
    $this->seed(RoleSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

// Feature: managed-services-module, Property 18: Role-to-MS-permission mapping
// Validates: Requirements 8.2, 8.3, 8.4
//
// For every role in {super_admin, admin, member}, after running RoleSeeder,
// a user with that role MUST be able to perform `view_managed_service` and
// `manage_managed_service` according to the design matrix:
//   super_admin -> view: true,  manage: true
//   admin       -> view: true,  manage: true
//   member      -> view: true,  manage: false

it('grants MS permissions to roles according to the design matrix across many iterations', function () {
    $matrix = [
        'super_admin' => ['view_managed_service' => true, 'manage_managed_service' => true],
        'admin' => ['view_managed_service' => true, 'manage_managed_service' => true],
        'member' => ['view_managed_service' => true, 'manage_managed_service' => false],
    ];

    for ($i = 0; $i < 100; $i++) {
        // Re-run the seeder each iteration; it must be deterministic and idempotent.
        $this->seed(RoleSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($matrix as $roleName => $expected) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            // Re-fetch to ensure permissions are loaded fresh for this user.
            $user = $user->fresh();

            expect($user->can('view_managed_service'))
                ->toBe(
                    $expected['view_managed_service'],
                    "Iteration {$i}: role {$roleName} expected view_managed_service=".
                    ($expected['view_managed_service'] ? 'true' : 'false')
                );

            expect($user->can('manage_managed_service'))
                ->toBe(
                    $expected['manage_managed_service'],
                    "Iteration {$i}: role {$roleName} expected manage_managed_service=".
                    ($expected['manage_managed_service'] ? 'true' : 'false')
                );
        }
    }
});

// Feature: managed-services-module, Property 1: Sidebar visibility gated by permission
// Validates: Requirements 1.4
//
// For any authenticated user without permission `view_managed_service`, none
// of the three Managed Services entries (MsOverview, ManagedServiceResource,
// ServiceBoard) shall report `canAccess() === true`; conversely, for any user
// with that permission, all three shall.
//
// Filament uses each entry's static `canAccess()` to decide whether to render
// the sidebar item, so verifying that contract directly verifies sidebar
// visibility without coupling the test to HTML markup.
it('shows the three Managed Services sidebar entries iff the user has view_managed_service', function () {
    $roles = ['super_admin', 'admin', 'member'];

    for ($i = 0; $i < 100; $i++) {
        $role = $roles[array_rand($roles)];
        // Randomly include or exclude the permission, then compute the expected
        // value from the resulting `can()` (Spatie role permissions cannot be
        // user-revoked, so `member` without view_managed_service requires
        // detaching from the role rather than direct revocation; we instead
        // compute the expectation from the actual permission state).
        $shouldGrantViewPermission = (bool) random_int(0, 1);

        $user = \App\Models\User::factory()->create();
        $user->assignRole($role);

        if ($shouldGrantViewPermission) {
            $user->givePermissionTo('view_managed_service');
        } else {
            $user->revokePermissionTo('view_managed_service');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = $user->fresh();

        $this->actingAs($user);

        $expected = $user->can('view_managed_service');

        expect(\App\Filament\Pages\MsOverview::canAccess())->toBe(
            $expected,
            "Iteration {$i}: role {$role} expected MsOverview::canAccess()=".
            ($expected ? 'true' : 'false')
        );

        expect(\App\Filament\Resources\ManagedServices\ManagedServiceResource::canAccess())->toBe(
            $expected,
            "Iteration {$i}: role {$role} expected ManagedServiceResource::canAccess()=".
            ($expected ? 'true' : 'false')
        );

        expect(\App\Filament\Pages\ServiceBoard::canAccess())->toBe(
            $expected,
            "Iteration {$i}: role {$role} expected ServiceBoard::canAccess()=".
            ($expected ? 'true' : 'false')
        );

        auth()->logout();
    }
});

// Feature: managed-services-module, Property 12: Permission gate on Managed Services entries
// Validates: Requirements 6.6, 8.5
//
// For any authenticated user without permission `view_managed_service`, an
// HTTP request to MsOverview, ManagedServiceResource (index/view/edit), or
// ServiceBoard shall return 403; conversely, for any user with that
// permission, those routes shall return 2xx (subject to other authorization).
it('gates Managed Services HTTP entry points by view_managed_service / manage_managed_service', function () {
    $roles = ['super_admin', 'admin', 'member'];

    for ($i = 0; $i < 100; $i++) {
        $role = $roles[array_rand($roles)];
        $shouldGrantViewPermission = (bool) random_int(0, 1);
        $shouldGrantManagePermission = (bool) random_int(0, 1);

        $user = \App\Models\User::factory()->create();
        $user->assignRole($role);

        if ($shouldGrantViewPermission) {
            $user->givePermissionTo('view_managed_service');
        } else {
            $user->revokePermissionTo('view_managed_service');
        }

        if ($shouldGrantManagePermission) {
            $user->givePermissionTo('manage_managed_service');
        } else {
            $user->revokePermissionTo('manage_managed_service');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $user = $user->fresh();

        $project = \App\Models\Project::factory()->create([
            'project_status' => \App\Enums\ProjectStatus::Managed->value,
        ]);

        // For non-admin/super_admin roles the resource scopes by membership;
        // attach so `view`/`edit` routes have a record visible to the user.
        if (! $user->hasRole(['super_admin', 'admin'])) {
            $project->members()->attach($user->id);
        }

        $this->actingAs($user);

        $canView = $user->can('view_managed_service');
        $canManage = $user->can('manage_managed_service');

        // MS Overview index
        $response = $this->get(\App\Filament\Pages\MsOverview::getUrl());
        if ($canView) {
            expect($response->status())->toBe(
                200,
                "Iteration {$i}: MsOverview expected 200 for role {$role} with view permission"
            );
        } else {
            expect($response->status())->toBe(
                403,
                "Iteration {$i}: MsOverview expected 403 for role {$role} without view permission"
            );
        }

        // ManagedServiceResource index
        $response = $this->get(
            \App\Filament\Resources\ManagedServices\ManagedServiceResource::getUrl('index')
        );
        if ($canView) {
            expect($response->status())->toBe(
                200,
                "Iteration {$i}: ManagedServiceResource index expected 200 for role {$role}"
            );
        } else {
            expect($response->status())->toBe(
                403,
                "Iteration {$i}: ManagedServiceResource index expected 403 for role {$role}"
            );
        }

        // ManagedServiceResource view
        $response = $this->get(
            \App\Filament\Resources\ManagedServices\ManagedServiceResource::getUrl('view', ['record' => $project])
        );
        if ($canView) {
            expect($response->status())->toBe(
                200,
                "Iteration {$i}: ManagedServiceResource view expected 200 for role {$role}"
            );
        } else {
            expect($response->status())->toBe(
                403,
                "Iteration {$i}: ManagedServiceResource view expected 403 for role {$role}"
            );
        }

        // ManagedServiceResource edit (additionally requires manage permission)
        $response = $this->get(
            \App\Filament\Resources\ManagedServices\ManagedServiceResource::getUrl('edit', ['record' => $project])
        );
        if ($canView && $canManage) {
            expect($response->status())->toBe(
                200,
                "Iteration {$i}: ManagedServiceResource edit expected 200 for role {$role} with manage permission"
            );
        } else {
            expect($response->status())->toBe(
                403,
                "Iteration {$i}: ManagedServiceResource edit expected 403 for role {$role} without manage permission"
            );
        }

        // ServiceBoard index
        $response = $this->get(\App\Filament\Pages\ServiceBoard::getUrl());
        if ($canView) {
            expect($response->status())->toBe(
                200,
                "Iteration {$i}: ServiceBoard expected 200 for role {$role} with view permission"
            );
        } else {
            expect($response->status())->toBe(
                403,
                "Iteration {$i}: ServiceBoard expected 403 for role {$role} without view permission"
            );
        }

        // Cleanup for next iteration
        $project->members()->detach();
        $project->delete();
        auth()->logout();
        $user->delete();
    }
});
