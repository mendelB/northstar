<?php

namespace Northstar\Console\Commands;

use Carbon\Carbon;
use DoSomething\Gateway\Blink;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Northstar\Models\User;

class BackfillCustomerIoProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'northstar:cio {start} {end=now}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send profiles updated between the given dates to Customer.io';

    /**
     * Execute the console command.
     *
     * @param Blink $blink
     * @return mixed
     */
    public function handle(Blink $blink)
    {
        $start = new Carbon($this->argument('start'));
        $end = new Carbon($this->argument('end'));

        // Iterate over users where the `mobile` field is not null.
        $query = User::where('updated_at', '>', $start)->where('updated_at', '<', $end);
        $query->chunkById(200, function (Collection $records) use ($start, $end, $blink) {
            $users = User::hydrate($records->toArray());

            // Send each of the loaded users to Blink's user queue.
            $users->each(function ($user) {
                gateway('blink')->userCreate($user->toBlinkPayload());
            });
        }, '_id');
    }
}
