<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Feature: customer-management, Property 5: NullOnDelete cascade behavior
// Validates: Requirements 3.3
// For any customer that is deleted, all projects previously associated with
// that customer should have their customer_id set to null and should still
// exist in the database.

it('sets customer_id to null on all associated projects when customer is deleted', function () {
    for ($i = 0; $i < 100; $i++) {
        $customer = Customer::factory()->create();
        $projectCount = fake()->numberBetween(1, 5);

        $projects = Project::factory()
            ->count($projectCount)
            ->create(['customer_id' => $customer->id]);

        // Also create unrelated projects to ensure they are unaffected
        $unrelatedProjects = Project::factory()
            ->count(fake()->numberBetween(0, 3))
            ->create();

        $projectIds = $projects->pluck('id')->all();

        // Delete the customer
        $customer->delete();

        // Property: all previously associated projects must still exist
        foreach ($projectIds as $projectId) {
            $this->assertDatabaseHas('projects', ['id' => $projectId]);
        }

        // Property: all previously associated projects must have customer_id = null
        $updatedProjects = Project::whereIn('id', $projectIds)->get();
        expect($updatedProjects)->toHaveCount($projectCount);

        foreach ($updatedProjects as $project) {
            expect($project->customer_id)->toBeNull();
        }

        // Property: unrelated projects must remain unchanged
        foreach ($unrelatedProjects as $unrelated) {
            $fresh = $unrelated->fresh();
            expect($fresh)->not->toBeNull();
            expect($fresh->customer_id)->toBe($unrelated->customer_id);
        }
    }
});
