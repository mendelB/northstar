<?php

use Northstar\Models\Client;
use Northstar\Models\User;

class OAuthTest extends TestCase
{
    /**
     * Test that the password grant rejects invalid credentials.
     */
    public function testPasswordGrantWithInvalidCredentials()
    {
        $user = User::create(['email' => 'login-test@dosomething.org', 'password' => 'secret']);
        $client = Client::create(['app_id' => 'phpunit']);

        $this->post('v2/auth/token', [
            'grant_type' => 'password',
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
            'username' => $user->email,
            'password' => 'letmein',
        ]);

        $this->assertResponseStatus(401);
    }

    /**
     * Test that the password grant provides a JWT for valid credentials.
     */
    public function testPasswordGrant()
    {
        $user = User::create(['email' => 'login-test@dosomething.org', 'password' => 'secret']);
        $client = Client::create(['app_id' => 'phpunit']);

        $this->post('v2/auth/token', [
            'grant_type' => 'password',
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
            'username' => $user->email,
            'password' => 'secret',
        ]);

        $this->assertResponseStatus(200);
        $this->seeJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'refresh_token',
        ]);
    }

    /**
     * Test that the client credentials grant rejects invalid credentials.
     */
    public function testClientCredentialsGrantWithFakeClient()
    {
        Client::create(['app_id' => 'phpunit']);

        $this->post('v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'totally_legit_client',
            'client_secret' => 'banana',
        ]);

        $this->assertResponseStatus(401);
    }

    /**
     * Test that the client credentials grant will not return "trusted" clients
     * if the client_secret is not provided.
     */
    public function testClientCredentialsGrantWithMissingSecret()
    {
        Client::create(['app_id' => 'phpunit']);

        $this->post('v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'phpunit',
        ]);

        $this->assertResponseStatus(401);
    }

    /**
     * Test that the client credentials grant rejects invalid credentials.
     */
    public function testClientCredentialsGrantWithInvalidCredentials()
    {
        Client::create(['app_id' => 'phpunit']);

        $this->post('v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => 'phpunit',
            'client_secret' => 'banana',
        ]);

        $this->assertResponseStatus(401);
    }

    /**
     * Test that the client credentials grant provides a JWT for valid credentials.
     */
    public function testClientCredentials()
    {
        $client = Client::create(['app_id' => 'phpunit']);

        $this->post('v2/auth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
        ]);

        $this->assertResponseStatus(200);
        $this->seeJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
        ]);
    }

    /**
     * Test that the refresh token grant provides a new JWT in exchange for
     * an unused refresh token, and then invalidates that refresh token.
     */
    public function testRefreshTokenGrant()
    {
        $user = User::create(['email' => 'login-test@dosomething.org', 'password' => 'secret']);
        $client = Client::create(['app_id' => 'phpunit']);

        $this->post('v2/auth/token', [
            'grant_type' => 'password',
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
            'username' => $user->email,
            'password' => 'secret',
        ]);

        // Get the provided refresh token.
        $refreshToken = $this->decodeResponseJson()['refresh_token'];

        // Get a new access token using the refresh token.
        $this->post('v2/auth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
            'refresh_token' => $refreshToken,
        ]);

        $this->assertResponseStatus(200);
        $this->seeJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'refresh_token',
        ]);

        // And now, verify that that refresh token has been consumed.
        $this->post('v2/auth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret,
            'refresh_token' => $refreshToken,
        ]);

        $this->assertResponseStatus(400);
    }
}
