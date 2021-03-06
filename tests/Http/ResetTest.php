<?php

use Northstar\Models\User;

class ResetTest extends TestCase
{
    /**
     * Test that anonymous and non-admin keys/users cannot create
     * password reset links.
     * POST /resets
     *
     * @test
     */
    public function testResetNotAccessibleByNonAdmin()
    {
        $this->post('v2/resets');
        $this->assertResponseStatus(401);

        $this->asNormalUser()->post('v2/resets');
        $this->assertResponseStatus(401);

        $this->asStaffUser()->post('v2/resets');
        $this->assertResponseStatus(401);
    }

    /**
     * Test creating a new password reset link.
     * POST /resets
     *
     * @test
     */
    public function testCreatePasswordResetLink()
    {
        $user = factory(User::class)->create();

        $this->asAdminUser()->post('v2/resets', ['id' => $user->id]);
        $this->assertResponseStatus(200);
        $this->seeJsonStructure(['url']);

        $this->seeInDatabase('password_resets', ['email' => $user->email]);
    }
}
