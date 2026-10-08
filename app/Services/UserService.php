<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class UserService
{
    // Si hay archivo: guarda localmente; si no: descarga de unavatar.io, null si falla
    public static function storeOrFetchAvatar(User $user, ?UploadedFile $avatar): ?string
    {
        if ($avatar) {
            $filename = $user->id.'.'.$avatar->clientExtension();
            $avatar->storeAs('avatars', $filename, 'external');

            return "/imagenes/avatars/{$filename}";
        } else {
            $filepath = '/tablerohu/imagenes/avatars/'.$user->id.'.jpg';

            try {
                $response = Http::timeout(20)
                    ->sink($filepath)
                    ->get("https://unavatar.io/{$user->email}?fallback=false");

                if ($response->successful()) {
                    return "/imagenes/avatars/{$user->id}.jpg";
                } else {
                    File::delete($filepath);

                    return null;
                }
            } catch (\Exception $e) {
                return null;
            }
        }
    }
}
