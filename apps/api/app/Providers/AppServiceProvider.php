<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
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
        Model::preventLazyLoading(! app()->isProduction());
        URL::forceRootUrl(config('app.url'));

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return rtrim(config('app.frontend_url'), '/')
                .'/reset-password?token='.urlencode($token)
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}
