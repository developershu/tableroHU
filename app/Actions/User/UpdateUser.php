<?php

namespace App\Actions\User;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateUser
{
    public function update($user, array $data): bool
    {
        $newData = [
            'name' => $data['name'],
            'job_title' => $data['job_title'],
            'phone' => $data['phone'],
            'email' => $data['email'],
        ];

        $user->syncRoles($data['roles']);

        if ($user->avatar === null || $data['avatar']) {
            $newData['avatar'] = UserService::storeOrFetchAvatar($user, $data['avatar']);
        }

        if (! empty($data['password'])) {
            $newData['password'] = Hash::make($data['password']);
        }

        return DB::transaction(function () use ($user, $newData, $data) {
            $updated = $user->update($newData);

            $user->syncAreas(self::resolveAreaIds($user, $data['area_ids']));

            return $updated;
        });
    }

    /**
     * Calcula las áreas finales del usuario editado.
     *
     * - Superadmin: las áreas enviadas reemplazan a todas.
     * - Resto (admin de área): solo puede agregar/quitar áreas que él mismo tiene.
     *   Las áreas del usuario editado que el admin NO administra se conservan siempre,
     *   para que no se pierdan por no aparecer en su selector.
     *
     * @param  array<int|string>  $submitted
     * @return int[]
     */
    public static function resolveAreaIds(User $user, array $submitted): array
    {
        $submitted = array_map('intval', $submitted);

        /** @var User|null $authUser */
        $authUser = auth()->user();

        if (! $authUser || $authUser->isSuperAdmin()) {
            return $submitted;
        }

        $manageable = $authUser->areaIds();
        $foreign = array_diff($user->areaIds(), $manageable);
        $ownSelected = array_intersect($submitted, $manageable);

        return array_values(array_unique([...$ownSelected, ...$foreign]));
    }
}
