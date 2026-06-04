<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ManagedServicePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_managed_service');
    }

    public function view(AuthUser $authUser, Project $project): bool
    {
        return $authUser->can('view_managed_service')
            && $project->project_status === ProjectStatus::Managed;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('manage_managed_service');
    }

    public function update(AuthUser $authUser, Project $project): bool
    {
        return $authUser->can('manage_managed_service')
            && $project->project_status === ProjectStatus::Managed;
    }

    public function delete(AuthUser $authUser, Project $project): bool
    {
        return false;
    }
}
