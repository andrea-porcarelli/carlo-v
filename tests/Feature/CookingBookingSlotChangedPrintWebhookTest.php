<?php

namespace Tests\Feature;

use App\Jobs\PrintCookingBookingSlotChangedJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CookingBookingSlotChangedPrintWebhookTest extends TestCase
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
            'reference' => '01HXY9999',
            'class_title' => 'Pasta fresca',
            'old_date' => '2026-09-20',
            'old_start' => '19:00',
            'old_end' => '21:00',
            'new_slot_start' => '2026-09-27T19:30:00+02:00',
            'new_slot_end' => '2026-09-27T21:30:00+02:00',
            'pax' => 2,
            'customer_name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'phone' => '+39 000',
            'notes' => 'Allergia noci',
        ], $override);
    }

    public function test_rejects_missing_key(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-slot-changed', $this->payload())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_rejects_wrong_key(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-slot-changed?key=WRONG', $this->payload())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_accepts_key_via_query_string_and_queues_job(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-slot-changed?key=test-key', $this->payload())
            ->assertStatus(202)
            ->assertJson(['ok' => true, 'queued' => true]);

        Queue::assertPushed(PrintCookingBookingSlotChangedJob::class, function ($job) {
            return $job->data['reference'] === '01HXY9999'
                && $job->data['old_date'] === '2026-09-20'
                && $job->data['pax'] === 2;
        });
    }

    public function test_accepts_key_via_header(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-slot-changed', $this->payload(), [
            'X-Cooking-Booking-Key' => 'test-key',
        ])->assertStatus(202);

        Queue::assertPushed(PrintCookingBookingSlotChangedJob::class);
    }

    public function test_validates_required_fields(): void
    {
        Queue::fake();

        $this->postJson('/webhook/cooking-booking-slot-changed?key=test-key', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference', 'pax']);

        Queue::assertNothingPushed();
    }
}
