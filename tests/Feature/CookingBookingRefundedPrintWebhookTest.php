<?php

namespace Tests\Feature;

use App\Jobs\PrintCookingBookingRefundedJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CookingBookingRefundedPrintWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'testing');
        putenv('COOKING_BOOKING_PRINT_KEY=test-key');
    }

    protected function tearDown(): void
    {
        putenv('COOKING_BOOKING_PRINT_KEY=');
        parent::tearDown();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'reference' => '01HXY8888',
            'class_title' => 'Pasta fresca',
            'slot_start' => '2026-09-27T19:30:00+02:00',
            'slot_end' => '2026-09-27T21:30:00+02:00',
            'pax' => 2,
            'customer_name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'phone' => '+39 000',
            'notes' => null,
            'total_cents' => 15000,
            'currency' => 'EUR',
            'payment_provider' => 'stripe',
        ], $override);
    }

    public function test_rejects_missing_key(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-refunded', $this->payload())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_rejects_wrong_key(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-refunded?key=WRONG', $this->payload())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_accepts_key_via_query_string_and_queues_job(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-refunded?key=test-key', $this->payload())
            ->assertStatus(202)
            ->assertJson(['ok' => true, 'queued' => true]);

        Queue::assertPushed(PrintCookingBookingRefundedJob::class, function ($job) {
            return $job->data['reference'] === '01HXY8888'
                && $job->data['total_cents'] === 15000
                && $job->data['payment_provider'] === 'stripe';
        });
    }

    public function test_accepts_key_via_header(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-refunded', $this->payload(), [
            'X-Cooking-Booking-Key' => 'test-key',
        ])->assertStatus(202);

        Queue::assertPushed(PrintCookingBookingRefundedJob::class);
    }

    public function test_validates_required_fields(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-refunded?key=test-key', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference', 'pax', 'total_cents']);

        Queue::assertNothingPushed();
    }
}
