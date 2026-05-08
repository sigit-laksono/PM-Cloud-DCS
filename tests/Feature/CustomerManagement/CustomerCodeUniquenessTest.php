<?php

declare(strict_types=1);

use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Feature: customer-management, Property 1: Customer code uniqueness and validation
// Validates: Requirements 1.2, 2.5
// For any two customers in the system, their code values must be distinct,
// and for any customer code, it must not exceed 10 characters and must be
// stored as uppercase.

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

it('stores code as uppercase and enforces uniqueness via form', function () {
    for ($i = 0; $i < 100; $i++) {
        $length = fake()->numberBetween(1, 10);
        $rawCode = fake()->unique()->lexify(str_repeat('?', $length));

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => fake()->company(),
                'code' => $rawCode,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $customer = Customer::where('code', strtoupper($rawCode))->first();

        // Property: code is stored as uppercase
        expect($customer)->not->toBeNull();
        expect($customer->code)->toBe(strtoupper($rawCode));

        // Property: code length does not exceed 10 characters
        expect(strlen($customer->code))->toBeLessThanOrEqual(10);
    }
});

it('rejects duplicate codes via form validation', function () {
    for ($i = 0; $i < 50; $i++) {
        $code = strtoupper(fake()->unique()->lexify(str_repeat('?', fake()->numberBetween(1, 10))));
        Customer::factory()->create(['code' => $code]);

        // Attempt to create another customer with the same code (different case)
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => fake()->company(),
                'code' => strtolower($code),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }
});

it('rejects codes exceeding 10 characters via form validation', function () {
    for ($i = 0; $i < 50; $i++) {
        $longCode = fake()->lexify(str_repeat('?', fake()->numberBetween(11, 20)));

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => fake()->company(),
                'code' => $longCode,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }
});
