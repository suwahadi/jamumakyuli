<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MidtransRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_and_finish_routes_are_registered(): void
    {
        $this->assertTrue(app('router')->has('payments.midtrans.notification'));
        $this->assertTrue(app('router')->has('payments.midtrans.finish'));
    }
}
