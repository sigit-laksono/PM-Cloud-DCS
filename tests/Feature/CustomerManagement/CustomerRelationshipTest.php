<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Feature: customer-management, Property 4: One-to-many relationship integrity
// Validates: Requirements 3.1
// For any customer with N associated projects, calling customer->projects()
// should return exactly those N projects, and each project's customer_id
// should equal the customer's id.

it('maintains one-to-many relationship integrity across random project counts', function () {
    for ($i = 0; $i < 100; $i++) {
        $customer = Customer::factory()->create();
        $projectCount = fake()->numberBetween(0, 5);

        $projects = Project::factory()
            ->count($projectCount)
            ->create(['customer_id' => $customer->id]);

        // Also create some unrelated projects to ensure they are not included
        Project::factory()->count(fake()->numberBetween(0, 3))->create();

        $relatedProjects = $customer->projects()->get();

        // Property: the count of related projects must equal exactly N
        expect($relatedProjects)->toHaveCount($projectCount);

        // Property: every related project's customer_id must equal the customer's id
        foreach ($relatedProjects as $project) {
            expect($project->customer_id)->toBe($customer->id);
        }

        // Property: the set of related project IDs matches exactly the created set
        $expectedIds = $projects->pluck('id')->sort()->values()->all();
        $actualIds = $relatedProjects->pluck('id')->sort()->values()->all();
        expect($actualIds)->toBe($expectedIds);
    }
});
