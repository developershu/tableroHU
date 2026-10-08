<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view users');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create user');
    }

    /**
     * Solo puede editar usuarios que compartan al menos un área (o el superadmin).
     */
    public function update(User $user, User $model): bool
    {
        if (! $user->hasPermissionTo('edit user')) {
            return false;
        }

        // El superadmin puede editar a cualquiera
        if ($user->isSuperAdmin()) {
            return true;
        }

        // El admin de área solo puede editar usuarios con los que comparte al menos un área
        return $user->sharesAreaWith($model);
    }

    /**
     * Solo puede archivar usuarios que compartan al menos un área (o el superadmin).
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->hasPermissionTo('archive user')) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->sharesAreaWith($model);
    }

    /**
     * Solo puede restaurar usuarios que compartan al menos un área (o el superadmin).
     */
    public function restore(User $user, User $model): bool
    {
        if (! $user->hasPermissionTo('restore user')) {
            return false;
        }

        if ($model->wasArchivedBySuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->sharesAreaWith($model);
    }

    /**
     * Solo puede borrar permanentemente usuarios que compartan al menos un área (o el superadmin).
     * Nunca se puede borrar el propio usuario logueado.
     */
    public function forceDelete(User $user, User $model): bool
    {
        if (! $user->hasPermissionTo('force delete user')) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($model->wasArchivedBySuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->sharesAreaWith($model);
    }
}
