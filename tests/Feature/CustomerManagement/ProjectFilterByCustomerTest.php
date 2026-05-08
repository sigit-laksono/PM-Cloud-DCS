<?php

declare(strict_types=1);

use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// Feature: customer-management, Property 6: Project filter by customer correctness
// Validates: Requirements 4.3
// For any customer filter applied on the ProjectResource table, all returned projects
// should have a customer_id matching the selected customer.

beforeEach(function () {
    $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->assignRole($role);
    $this->actingAs($this->user);
});

it('filters projects correctly by customer', function () {
    for ($i = 0; $i < 50; $i++) {
        $customerCount = fake()->numberBetween(2, 4);
        $customers = Customer::factory()->count($customerCount)->create();

        // Create projects assigned to different customers
        $projectsByCustomer = [];
        foreach ($customers as $customer) {
            $count = fake()->numberBetween(1, 3);
            $projectsByCustomer[$customer->id] = Project::factory()
                ->count($count)
                ->create(['customer_id' => $customer->id]);
        }

        // Also create some projects without a customer
        $unassignedProjects = Project::factory()
            ->count(fake()->numberBetween(1, 2))
            ->create(['customer_id' => null]);

        // Pick a random customer to filter by
        $targetCustomer = $customers->random();
        $expectedProjects = $projectsByCustomer[$targetCustomer->id];
        $otherProjects = collect($projectsByCustomer)
            ->filter(fn ($projects, $customerId) => $customerId !== $targetCustomer->id)
            ->flatten();

        $livewire = Livewire::test(ListProjects::class)
            ->filterTable('customer_id', $targetCustomer->id);

        // Property: all projects belonging to the target customer should be visible
        $livewire->assertCanSeeTableRecords($expectedProjects);

        // Property: projects belonging to other customers should NOT be visible
        $livewire->assertCanNotSeeTableRecords($otherProjects);

        // Property: unassigned projects should NOT be visible
        $livewire->assertCanNotSeeTableRecords($unassignedProjects);

        // Cleanup for next iteration
        Project::query()->delete();
        Customer::query()->delete();
    }
});
