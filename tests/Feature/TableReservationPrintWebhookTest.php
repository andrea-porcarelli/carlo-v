<?php

namespace Tests\Feature;

use App\Jobs\PrintTableReservationJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TableReservationPrintWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.env', 'testing');
        putenv('TABLE_RESERVATION_PRINT_KEY=test-key');
        putenv('COOKING_BOOKING_PRINT_KEY=');
    }

    protected function tearDown(): void
    {
        putenv('TABLE_RESERVATION_PRINT_KEY=');
        putenv('COOKING_BOOKING_PRINT_KEY=');
        parent::tearDown();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'reference' => '01HXY0001',
            'reservation_date' => '2026-08-15',
            'slot_time' => '20:30',
            'adults' => 4,
            'children' => 2,
            'total_pax' => 6,
            'special_requests' => 'Tavolo vicino alla finestra',
            'customer_name' => 'Mario Rossi',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario@example.com',
            'phone' => '+39 000',
            'country_code' => 'IT',
        ], $override);
    }

    public function test_rejects_missing_key(): void
    {
        Queue::fake();

        $this->postJson('/webhook/table-reservation', $this->payload())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_rejects_wrong_key(): void
    {
        Queue::fake();

        $this->postJson('/webhook/table-reservation?key=WRONG', $this->payload())
            ->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_accepts_key_via_query_string_and_queues_job(): void
    {
        Queue::fake();

        $this->postJson('/webhook/table-reservation?key=test-key', $this->payload())
            ->assertStatus(202)
            ->assertJson(['ok' => true, 'queued' => true]);

        Queue::assertPushed(PrintTableReservationJob::class, function ($job) {
            return $job->data['reference'] === '01HXY0001'
                && $job->data['slot_time'] === '20:30'
                && $job->data['adults'] === 4
                && $job->data['children'] === 2;
        });
    }

    public function test_accepts_key_via_header(): void
    {
        Queue::fake();

        $this->postJson('/webhook/table-reservation', $this->payload(), [
            'X-Table-Reservation-Key' => 'test-key',
        ])->assertStatus(202);

        Queue::assertPushed(PrintTableReservationJob::class);
    }

    public function test_falls_back_to_cooking_booking_key_when_dedicated_missing(): void
    {
        Queue::fake();
        putenv('TABLE_RESERVATION_PRINT_KEY=');
        putenv('COOKING_BOOKING_PRINT_KEY=shared-key');

        $this->postJson('/webhook/table-reservation?key=shared-key', $this->payload())
            ->assertStatus(202);

        Queue::assertPushed(PrintTableReservationJob::class);
    }

    public function test_validates_required_fields(): void
    {
        Queue::fake();

        $this->postJson('/webhook/table-reservation?key=test-key', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference', 'reservation_date', 'slot_time', 'adults']);

        Queue::assertNothingPushed();
    }

    public function test_children_defaults_to_null_when_absent(): void
    {
        Queue::fake();

        $payload = $this->payload();
        unset($payload['children']);

        $this->postJson('/webhook/table-reservation?key=test-key', $payload)
            ->assertStatus(202);

        Queue::assertPushed(PrintTableReservationJob::class);
    }
}
