<?php

use Carbon\Carbon;
use DoSomething\Gateway\Blink;
use Northstar\Models\User;

class BackfillCustomerIoProfilesTest extends TestCase
{
    /** @test */
    public function it_should_backfill_users()
    {
        factory(User::class, 3)->create(['updated_at' => new Carbon('1/15/2016')]);
        factory(User::class, 2)->create(['updated_at' => new Carbon('3/14/2017')]);

        // Reset our Blink mock & set expectation that it'll be called twice - once
        // for each of the users updated between 1/1/2017 and now.
        $this->mock(Blink::class)->shouldReceive('userCreate')->twice();

        // Run the Customer.io backfill command.
        $this->artisan('northstar:cio', ['start' => '1/1/2017']);
    }
}
