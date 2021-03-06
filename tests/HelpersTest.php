<?php

use Carbon\Carbon;
use Northstar\Models\Client;

class HelpersTest extends TestCase
{
    /** @test */
    public function testFormatDate()
    {
        // It should format strings that PHP can parse as DateTimes.
        $this->assertEquals(format_date('10/25/1990'), 'Oct 25, 1990');
        $this->assertEquals(format_date('1990-10-25'), 'Oct 25, 1990');

        // It should also format Carbon objects.
        $carbonDate = Carbon::create(1990, 10, 25);
        $this->assertEquals(format_date($carbonDate), 'Oct 25, 1990');

        // It should return null if null is passed.
        $this->assertEquals(format_date(null), null);
    }

    /** @test */
    public function testIso8601()
    {
        $this->assertEquals('2017-12-15T22:00:00+00:00', iso8601('December 15 2017 10:00pm'));
        $this->assertEquals('2017-12-15T22:00:00+00:00', iso8601(new Carbon('December 15 2017 10:00pm')));
        $this->assertEquals(null, iso8601(null), 'handles null values safely');
    }

    /** @test */
    public function testRouteHasAttachedMiddleware()
    {
        $this->get('/login');

        // It should be able to check if in a middleware group.
        $this->assertTrue(has_middleware('web'));
        $this->assertFalse(has_middleware('api'));

        // ...or just if it has any middleware at all!
        $this->assertTrue(has_middleware());
    }

    /** @test */
    public function testGetClientId()
    {
        // Web requests should report as 'northstar'.
        $this->get('/register');
        $this->assertEquals(client_id(), 'northstar');

        // Make a request using legacy API header.
        $client = Client::create(['client_id' => 'legacy_client', 'scope' => 'user']);
        $this->withLegacyApiKey($client)->json('POST', '/v1/auth/register', [
            'email' => $this->faker->safeEmail,
            'password' => $this->faker->password,
        ]);
        $this->assertEquals(client_id(), 'legacy_client');

        // And make a request using OAuth.
        $this->asNormalUser()->json('POST', '/v1/auth/register', [
            'email' => $this->faker->safeEmail,
            'password' => $this->faker->password,
        ]);
        $this->assertEquals(client_id(), 'phpunit');
    }
}
