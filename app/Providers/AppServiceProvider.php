<?php

namespace Northstar\Providers;

use Illuminate\Auth\NorthstarJwtGuard;
use Northstar\Models\User;
use DoSomething\Gateway\Blink;
use DoSomething\Gateway\Gladiator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        User::creating(function (User $user) {
            // Set source automatically if not provided.
            $user->source = $user->source ?: client_id();
        });

        User::created(function (User $user) {
            // Send payload to Blink for Customer.io profile.
            if (config('features.blink')) {
                app(Blink::class)->userCreate($user->toBlinkPayload());
            }

            // Send metrics to StatHat.
            app('stathat')->ezCount('user created');
            app('stathat')->ezCount('user created - '.$user->source);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // @TODO: This should be registered in Gateway's service provider!
        $this->app->singleton(Blink::class, function () {
            return new Blink(config('services.blink'));
        });

        $this->app->singleton(Gladiator::class, function () {
            return new Gladiator(config('services.gladiator'));
        });
    }
}
