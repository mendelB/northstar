<?php

namespace Northstar\Providers;

use Illuminate\Database\Query\Builder;
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

        User::updating(function (User $user) {
            // Write profile changes to the log, with redacted values for hidden fields.
            $changed = array_replace_keys($user->getDirty(), $user->getHidden(), '*****');
            logger('updated user', ['id' => $user->id, 'client_id' => client_id(), 'changed' => $changed]);
        });

        // Add 'chunkWithLimit' method to the query builder.
        // @see: https://github.com/laravel/internals/issues/103
        Builder::macro('chunkFromId', function ($count, $startId, callable $callback, $column) {
            /** @var Builder $this */

            // Literally copy-pasting `chunkById` so we can override this value... :'(
            $lastId = $startId;

            do {
                $clone = clone $this;

                // ... and switch this `>` to a `>=`. Oy.
                $results = $clone->where($column, '>=', $lastId)
                    ->orderBy($column, 'asc')
                    ->take($count)
                    ->get();

                $countResults = $results->count();

                if ($countResults == 0) {
                    break;
                }

                if (call_user_func($callback, $results) === false) {
                    return false;
                }

                $lastId = $results->last()[$column];
            } while ($countResults == $count);

            return true;
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
