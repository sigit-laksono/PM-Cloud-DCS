<?php

declare(strict_types=1);

use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Feature: customer-management, Property 3: Customer active filter correctness
// Validates: Requirements 2.4
// For any set of customers with mixed is_active states, filtering by active status
// should return only customers whose is_active field matches the filter value.

beforeEach(function () {
    $permissions = ['view_customer', 'view_any_customer', 'create_customer', 'update_customer', 'delete_customer'];
    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    $this->user = User::factory()->create();
    $this->user->assignRole($role);
    $this->actingAs($this->user);
});

it('filters customers correctly by active status', function () {
    for ($i = 0; $i < 50; $i++) {
        $activeCount = fake()->numberBetween(1, 4);
        $inactiveCount = fake()->numberBetween(1, 4);

        $activeCustomers = Customer::factory()->count($activeCount)->create(['is_active' => true]);
        $inactiveCustomers = Customer::factory()->count($inactiveCount)->create(['is_active' => false]);

        // Filter for active only
        $livewire = Livewire::test(ListCustomers::class)
            ->filterTable('is_active', true);

        // Property: all active customers should be visible
        $livewire->assertCanSeeTableRecords($activeCustomers);
        // Property: no inactive customers should be visible
        $livewire->assertCanNotSeeTableRecords($inactiveCustomers);

        // Filter for inactive only
        $livewire = Livewire::test(ListCustomers::class)
            ->filterTable('is_active', false);

        // Property: all inactive customers should be visible
        $livewire->assertCanSeeTableRecords($inactiveCustomers);
        // Property: no active customers should be visible
        $livewire->assertCanNotSeeTableRecords($activeCustomers);

        Customer::query()->delete();
    }
});
