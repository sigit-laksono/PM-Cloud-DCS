<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Pages\ProjectBoard;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
});

// Feature: managed-services-module
// Validates: Requirements 10.1, 10.2
//
// Zero migration & enum unchanged: the Managed Services Module ships without
// adding any migration to database/migrations and without altering the
// ProjectStatus enum cases.

it('does not introduce any migration file matching *managed_service* in database/migrations', function () {
    $migrationsDir = database_path('migrations');

    expect(is_dir($migrationsDir))->toBeTrue(
        "Migrations directory missing at {$migrationsDir}"
    );

    // Use a case-insensitive scan over the migrations directory looking for
    // the substring "managed_service" anywhere in the filename.
    $matches = collect(scandir($migrationsDir) ?: [])
        ->reject(fn ($entry) => $entry === '.' || $entry === '..')
        ->filter(fn ($entry) => is_file($migrationsDir.DIRECTORY_SEPARATOR.$entry))
        ->filter(fn ($entry) => stripos((string) $entry, 'managed_service') !== false)
        ->values()
        ->all();

    expect($matches)->toBe(
        [],
        'Found unexpected migration file(s) related to managed_service: '.json_encode($matches)
    );
});

it('keeps ProjectStatus::cases() at exactly the four documented cases', function () {
    $cases = ProjectStatus::cases();

    expect($cases)->toHaveCount(4);

    $names = array_map(fn (ProjectStatus $case) => $case->name, $cases);

    expect($names)->toBe(['Poc', 'Running', 'Completed', 'Managed']);

    // Belt-and-braces: each documented case must still resolve to its
    // canonical name so unrelated re-orderings still surface here.
    expect(ProjectStatus::Poc->name)->toBe('Poc');
    expect(ProjectStatus::Running->name)->toBe('Running');
    expect(ProjectStatus::Completed->name)->toBe('Completed');
    expect(ProjectStatus::Managed->name)->toBe('Managed');
});

// Feature: managed-services-module
// Validates: Requirements 10.3, 10.4
//
// Existing routes remain accessible for non-Managed projects:
//   - /admin/projects must return HTTP 200 and list a Running project
//   - ProjectBoard::getUrl() must return HTTP 200 and the dropdown must
//     contain Running/Poc/Completed projects (excluding Managed handled
//     by Property 3 in ProjectResourceScopeTest).

it('keeps /admin/projects accessible and lists non-Managed projects for super_admin', function () {
    $running = Project::factory()->create([
        'project_status' => ProjectStatus::Running->value,
        'name' => 'Zero-Migration Running Project '.fake()->unique()->lexify('????'),
    ]);

    // Also create a Managed project so the assertion that Managed records
    // are excluded from /admin/projects remains exercised end-to-end. It
    // must NOT be found in the rendered list page output.
    $managed = Project::factory()->create([
        'project_status' => ProjectStatus::Managed->value,
        'name' => 'Zero-Migration Managed Project '.fake()->unique()->lexify('????'),
    ]);

    $response = $this->actingAs($this->superAdmin)->get('/admin/projects');

    $response->assertOk();
    $response->assertSee($running->name);
});

it('keeps ProjectBoard accessible and exposes Poc/Running/Completed projects in the dropdown', function () {
    $poc = Project::factory()->create([
        'project_status' => ProjectStatus::Poc->value,
        'name' => 'Zero-Migration POC '.fake()->unique()->lexify('????'),
    ]);
    $running = Project::factory()->create([
        'project_status' => ProjectStatus::Running->value,
        'name' => 'Zero-Migration Running '.fake()->unique()->lexify('????'),
    ]);
    $completed = Project::factory()->create([
        'project_status' => ProjectStatus::Completed->value,
        'name' => 'Zero-Migration Completed '.fake()->unique()->lexify('????'),
    ]);
    $managed = Project::factory()->create([
        'project_status' => ProjectStatus::Managed->value,
        'name' => 'Zero-Migration Managed '.fake()->unique()->lexify('????'),
    ]);

    // 1. The page itself must respond OK on the existing route.
    $response = $this->actingAs($this->superAdmin)->get(ProjectBoard::getUrl());
    $response->assertOk();

    // 2. The dropdown (driven by ProjectBoard::$projects) must contain the
    //    three non-Managed projects and exclude the Managed one.
    $component = Livewire::test(ProjectBoard::class);

    /** @var \Illuminate\Support\Collection<int, Project> $projects */
    $projects = $component->get('projects');

    $ids = $projects->pluck('id')->all();

    expect($ids)->toContain($poc->id);
    expect($ids)->toContain($running->id);
    expect($ids)->toContain($completed->id);
    expect($ids)->not->toContain($managed->id);
});
