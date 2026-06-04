<?php

declare(strict_types=1);

use App\Enums\ProjectStatus;
use App\Filament\Resources\ManagedServices\ManagedServiceResource;
use App\Filament\Resources\ManagedServices\Pages\ListManagedServices;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
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

// Validates: Requirements 3.2, 3.3, 3.5, 3.6
//
// Static configuration of ManagedServiceResource:
//  - navigation group/sort/label, slug, model labels
//  - status badge label/color via ProjectStatus enum
//  - ListManagedServices::getHeaderActions() returns no CreateAction
//  - SelectFilter::make('customer_id') exists in the toolbar

it('exposes the expected slug and model labels', function () {
    expect(ManagedServiceResource::getSlug())->toBe('managed-services');
    expect(ManagedServiceResource::getModelLabel())->toBe('Managed Service');
    expect(ManagedServiceResource::getPluralModelLabel())->toBe('Managed Services');
});

it('registers under the Managed Services navigation group with the documented sort/label', function () {
    expect(ManagedServiceResource::getNavigationGroup())->toBe('Managed Services');
    expect(ManagedServiceResource::getNavigationSort())->toBe(2);
    expect(ManagedServiceResource::getNavigationLabel())->toBe('Services');
});

it('maps ProjectStatus::Managed to the Managed Services badge label and primary color', function () {
    expect(ProjectStatus::Managed->getLabel())->toBe('Managed Services');
    expect(ProjectStatus::Managed->getColor())->toBe('primary');
});

it('has no CreateAction in the ListManagedServices header actions', function () {
    $this->actingAs($this->superAdmin);

    // ListManagedServices::getHeaderActions() is `protected`. Boot the page
    // through Livewire so Filament bindings are in place, then use reflection
    // to invoke the method on the live instance.
    $component = Livewire::test(ListManagedServices::class);
    $instance = $component->instance();

    $method = new ReflectionMethod($instance, 'getHeaderActions');
    $method->setAccessible(true);

    /** @var array<int, mixed> $actions */
    $actions = $method->invoke($instance);

    expect($actions)->toBeArray();
    expect($actions)->toBe([]);

    foreach ($actions as $action) {
        expect($action)->not->toBeInstanceOf(CreateAction::class);
    }
});

it('exposes the customer_id SelectFilter on the ManagedService table toolbar', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(ListManagedServices::class)
        ->assertTableFilterExists('customer_id');
});

// Validates: Requirements 4.6, 4.8, 5.4, 5.6
//
// Confirmation modal & notification dispatch for the Promote/Demote actions.
//   - Both actions MUST require confirmation (modal) before executing.
//   - On successful execution, both actions MUST dispatch a Filament
//     Notification with the documented success title.

it('requires a confirmation modal before promoting a project to Managed', function () {
    $action = \App\Filament\Actions\PromoteToManagedAction::make();

    expect($action->isConfirmationRequired())->toBeTrue();
});

it('requires a confirmation modal before demoting a project from Managed', function () {
    $action = \App\Filament\Actions\DemoteFromManagedAction::make();

    expect($action->isConfirmationRequired())->toBeTrue();
});

it('dispatches a success notification after a successful promote', function () {
    $this->actingAs($this->superAdmin);

    $project = \App\Models\Project::factory()->create([
        'project_status' => \App\Enums\ProjectStatus::Running->value,
    ]);

    \App\Filament\Actions\PromoteToManagedAction::make()
        ->record($project)
        ->call();

    \Filament\Notifications\Notification::assertNotified('Project promoted to Managed Services');
});

it('dispatches a success notification after a successful demote', function () {
    $this->actingAs($this->superAdmin);

    $project = \App\Models\Project::factory()->create([
        'project_status' => \App\Enums\ProjectStatus::Managed->value,
    ]);

    \App\Filament\Actions\DemoteFromManagedAction::make()
        ->record($project)
        ->call();

    \Filament\Notifications\Notification::assertNotified('Service demoted to Running');
});

// Validates: Requirements 7.7, 7.11
//
// MsOverview header actions and view configuration:
//   - getHeaderActions() MUST contain an action named "refresh".
//   - The blade view MUST NOT contain `wire:poll` per requirement 7.11
//     (no automatic polling).

it('registers a refresh action on the MsOverview header', function () {
    $this->actingAs($this->superAdmin);

    $component = Livewire::test(\App\Filament\Pages\MsOverview::class);
    $instance = $component->instance();

    $method = new ReflectionMethod($instance, 'getHeaderActions');
    $method->setAccessible(true);

    /** @var array<int, \Filament\Actions\Action> $actions */
    $actions = $method->invoke($instance);

    expect($actions)->toBeArray();
    expect($actions)->not->toBeEmpty();

    $names = array_map(fn ($action) => $action->getName(), $actions);

    expect($names)->toContain('refresh');
});

it('does not embed any wire:poll directive in the MsOverview blade view', function () {
    $viewPath = resource_path('views/filament/pages/ms-overview.blade.php');

    expect(file_exists($viewPath))->toBeTrue(
        "MsOverview blade view is missing at {$viewPath}"
    );

    $contents = (string) file_get_contents($viewPath);

    expect($contents)->not->toContain(
        'wire:poll',
        'MsOverview blade view must not use wire:poll (Requirement 7.11)'
    );
});
