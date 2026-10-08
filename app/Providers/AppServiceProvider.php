<?php

namespace App\Providers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        JsonResource::withoutWrapping();

        foreach (['info', 'success', 'warning', 'error'] as $type) {
            RedirectResponse::macro(
                $type,
                function ($title, $message = null) use ($type) {
                    return $this->with('flash', ['type' => $type, 'title' => $title, 'message' => $message]);
                }
            );
        }
    }
}
