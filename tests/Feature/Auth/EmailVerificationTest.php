<?php

namespace Tests\Feature\Auth;

use Tests\Support\UsesSqliteTestDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use UsesSqliteTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpSqliteTestDatabase();
    }

    public function test_route_verifikasi_email_tidak_tersedia(): void
    {
        $this->get('/email/verify')->assertNotFound();
        $this->get('/email/verify/1/hash')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
    }
}
