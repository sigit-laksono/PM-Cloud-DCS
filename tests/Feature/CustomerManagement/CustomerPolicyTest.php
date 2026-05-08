<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Feature: customer-management, Property 7: Policy authorization based on permissions
// Validates: Requirements 5.2
// For any user and any customer action (view, viewAny, create, update, delete),
// the CustomerPolicy should return true if and only if the user has the
// corresponding permission assigned.

beforeEach(function () {
    // Create all customer permissions
    $actions = ['view', 'view_any', 'create', 'update', 'delete'];
    foreach ($actions as $action) {
        Permission::firstOrCreate(['name' => $action . '_customer', 'guard_name' => 'web']);
    }
});

it('authorizes actions if and only if user has the corresponding permission', function () {
    $permissionActionMap = [
        'view_customer' => 'view',
        'view_any_customer' => 'viewAny',
        'create_customer' => 'create',
        'update_customer' => 'update',
        'delete_customer' => 'delete',
    ];

    for ($i = 0; $i < 100; $i++) {
        // Randomly select a subset of permissions to grant
        $allPermissions = array_keys($permissionActionMap);
        $grantedPermissions = fake()->randomElements(
            $allPermissions,
            fake()->numberBetween(0, count($allPermissions))
        );

        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'test_role_' . $i, 'guard_name' => 'web']);
        $role->syncPermissions($grantedPermissions);
        $user->assignRole($role);

        $customer = Customer::factory()->create();

        foreach ($permissionActionMap as $permission => $policyMethod) {
            $hasPermission = in_array($permission, $grantedPermissions);

            if (in_array($policyMethod, ['view', 'update', 'delete'])) {
                $result = $user->can($policyMethod, $customer);
            } elseif ($policyMethod === 'viewAny') {
                $result = $user->can('viewAny', Customer::class);
            } else {
                $result = $user->can('create', Customer::class);
            }

            // Property: authorization result must match permission assignment
            expect($result)->toBe($hasPermission, "Failed for permission '{$permission}' (granted: " . ($hasPermission ? 'yes' : 'no') . ")");
        }
    }
});
