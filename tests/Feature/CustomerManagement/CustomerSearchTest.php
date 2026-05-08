<?php

declare(strict_types=1);

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Feature: customer-management, Property 2: Customer search returns matching results
// Validates: Requirements 2.3
// For any customer with a given name or code, searching by a substring of that
// name or code in the CustomerResource table should include that customer in the results.

beforeEach(function () {
    // Create permissions and admin user
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

it('returns customers matching search by name substring', function () {
    for ($i = 0; $i < 50; $i++) {
        $target = Customer::factory()->create();
        // Create some noise customers
        Customer::factory()->count(fake()->numberBetween(1, 3))->create();

        // Take a random substring of the name (at least 3 chars)
        $name = $target->name;
        if (strlen($name) >= 3) {
            $start = fake()->numberBetween(0, strlen($name) - 3);
            $searchTerm = substr($name, $start, 3);
        } else {
            $searchTerm = $name;
        }

        $livewire = Livewire::test(ListCustomers::class)
            ->searchTable($searchTerm);

        // Property: the target customer should appear in search results
        $livewire->assertCanSeeTableRecords([$target]);

        // Clean up for next iteration
        Customer::query()->delete();
    }
});

it('returns customers matching search by code', function () {
    for ($i = 0; $i < 50; $i++) {
        $target = Customer::factory()->create();
        Customer::factory()->count(fake()->numberBetween(1, 3))->create();

        $code = $target->code;
        $searchTerm = substr($code, 0, min(3, strlen($code)));

        $livewire = Livewire::test(ListCustomers::class)
            ->searchTable($searchTerm);

        // Property: the target customer should appear in search results
        $livewire->assertCanSeeTableRecords([$target]);

        Customer::query()->delete();
    }
});
