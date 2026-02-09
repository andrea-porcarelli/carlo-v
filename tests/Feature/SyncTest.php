<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Printer;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\SyncWatermark;
use App\Models\User;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sync.role' => 'local',
            'sync.api_token' => 'test-token-123',
            'sync.peer_url' => 'http://web.test',
            'sync.batch_size' => 100,
        ]);
    }

    public function test_sync_api_rejects_missing_token(): void
    {
        $response = $this->getJson('/api/sync/status');
        $response->assertStatus(401);
    }

    public function test_sync_api_rejects_invalid_token(): void
    {
        $response = $this->getJson('/api/sync/status', [
            'X-Sync-Token' => 'wrong-token',
        ]);
        $response->assertStatus(401);
    }

    public function test_sync_api_accepts_valid_token(): void
    {
        $response = $this->getJson('/api/sync/status', [
            'X-Sync-Token' => 'test-token-123',
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'role', 'master_tables', 'watermarks']);
    }

    public function test_sync_status_returns_correct_role(): void
    {
        config(['sync.role' => 'web']);

        $response = $this->getJson('/api/sync/status', [
            'X-Sync-Token' => 'test-token-123',
        ]);
        $response->assertStatus(200);
        $response->assertJson(['role' => 'web']);
    }

    public function test_export_rejects_non_master_table(): void
    {
        // Role is local, so 'users' is not mastered here
        $response = $this->getJson('/api/sync/export/users', [
            'X-Sync-Token' => 'test-token-123',
        ]);
        $response->assertStatus(403);
    }

    public function test_export_returns_data_for_master_table(): void
    {
        // Role is local, printers is mastered
        Printer::create(['label' => 'Cucina', 'ip' => '192.168.1.100', 'is_active' => true]);
        Printer::create(['label' => 'Bar', 'ip' => '192.168.1.101', 'is_active' => true]);

        $response = $this->getJson('/api/sync/export/printers', [
            'X-Sync-Token' => 'test-token-123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'table',
            'data',
            'deleted_ids',
            'meta' => ['page', 'per_page', 'total', 'total_pages'],
        ]);
        $response->assertJsonCount(2, 'data');
    }

    public function test_export_filters_by_since_parameter(): void
    {
        $old = Printer::create(['label' => 'Old', 'ip' => '192.168.1.1', 'is_active' => true]);
        $old->timestamps = false;
        $old->update(['updated_at' => '2024-01-01 00:00:00']);

        Printer::create(['label' => 'New', 'ip' => '192.168.1.2', 'is_active' => true]);

        $response = $this->getJson('/api/sync/export/printers?since=2025-01-01T00:00:00Z', [
            'X-Sync-Token' => 'test-token-123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_watermark_get_and_set(): void
    {
        $this->assertNull(SyncWatermark::getWatermark('users'));

        $now = now();
        SyncWatermark::setWatermark('users', $now);

        $watermark = SyncWatermark::getWatermark('users');
        $this->assertNotNull($watermark);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $watermark->format('Y-m-d H:i:s'));
    }

    public function test_watermark_reset(): void
    {
        SyncWatermark::setWatermark('users', now());
        $this->assertNotNull(SyncWatermark::getWatermark('users'));

        SyncWatermark::resetWatermark('users');
        $this->assertNull(SyncWatermark::getWatermark('users'));
    }

    public function test_import_creates_records_with_preserved_ids(): void
    {
        config(['sync.role' => 'local']);

        Http::fake([
            'http://web.test/api/sync/export/allergens*' => Http::response([
                'table' => 'allergens',
                'data' => [
                    ['id' => 42, 'label' => 'Glutine', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                    ['id' => 43, 'label' => 'Lattosio', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'],
                ],
                'deleted_ids' => [],
                'meta' => ['page' => 1, 'per_page' => 100, 'total' => 2, 'total_pages' => 1],
            ]),
        ]);

        $service = new SyncService('test');
        $result = $service->syncTable('allergens');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(2, $result['created']);

        // Verify IDs are preserved
        $this->assertNotNull(Allergen::find(42));
        $this->assertNotNull(Allergen::find(43));
        $this->assertEquals('Glutine', Allergen::find(42)->label);
    }

    public function test_import_updates_existing_records(): void
    {
        Allergen::create(['id' => 42, 'label' => 'Old Label']);

        Http::fake([
            'http://web.test/api/sync/export/allergens*' => Http::response([
                'table' => 'allergens',
                'data' => [
                    ['id' => 42, 'label' => 'Updated Label', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-06-01 00:00:00'],
                ],
                'deleted_ids' => [],
                'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1, 'total_pages' => 1],
            ]),
        ]);

        $service = new SyncService('test');
        $result = $service->syncTable('allergens');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals('Updated Label', Allergen::find(42)->label);
    }

    public function test_soft_delete_propagation(): void
    {
        RestaurantTable::create([
            'id' => 10,
            'table_number' => 1,
            'capacity' => 4,
            'status' => 'free',
            'is_active' => true,
        ]);

        Http::fake([
            'http://web.test/api/sync/export/restaurant_tables*' => Http::response([
                'table' => 'restaurant_tables',
                'data' => [],
                'deleted_ids' => [10],
                'meta' => ['page' => 1, 'per_page' => 100, 'total' => 0, 'total_pages' => 1],
            ]),
        ]);

        // For this test, role is local and restaurant_tables is mastered by local
        // We need to test the soft delete mechanism, so let's use web role pulling from local
        config(['sync.role' => 'web']);
        config(['sync.peer_url' => 'http://web.test']);

        $service = new SyncService('test');
        $result = $service->syncTable('restaurant_tables');

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['deleted']);
        $this->assertNull(RestaurantTable::find(10));
        $this->assertNotNull(RestaurantTable::withTrashed()->find(10));
    }

    public function test_sync_log_created_on_sync(): void
    {
        Http::fake([
            'http://web.test/api/sync/export/allergens*' => Http::response([
                'table' => 'allergens',
                'data' => [],
                'deleted_ids' => [],
                'meta' => ['page' => 1, 'per_page' => 100, 'total' => 0, 'total_pages' => 1],
            ]),
        ]);

        $service = new SyncService('test');
        $service->syncTable('allergens');

        $log = SyncLog::forTable('allergens')->latestRun()->first();
        $this->assertNotNull($log);
        $this->assertEquals('success', $log->status);
        $this->assertEquals('test', $log->triggered_by);
    }

    public function test_sync_log_records_failure(): void
    {
        Http::fake([
            'http://web.test/api/sync/export/allergens*' => Http::response('Server Error', 500),
        ]);

        $service = new SyncService('test');
        $result = $service->syncTable('allergens');

        $this->assertEquals('failed', $result['status']);

        $log = SyncLog::forTable('allergens')->latestRun()->first();
        $this->assertNotNull($log);
        $this->assertEquals('failed', $log->status);
        $this->assertNotNull($log->error_message);
    }

    public function test_settings_cache_invalidated_after_sync(): void
    {
        // Pre-populate a cached setting
        Setting::create(['key' => 'cover_charge', 'value' => '2.00', 'type' => 'decimal']);
        Setting::get('cover_charge'); // This caches the value

        Http::fake([
            'http://web.test/api/sync/export/settings*' => Http::response([
                'table' => 'settings',
                'data' => [
                    ['id' => 1, 'key' => 'cover_charge', 'value' => '3.50', 'type' => 'decimal', 'description' => null, 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-06-01 00:00:00'],
                ],
                'deleted_ids' => [],
                'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1, 'total_pages' => 1],
            ]),
        ]);

        $service = new SyncService('test');
        $result = $service->syncTable('settings');

        $this->assertEquals('success', $result['status']);
        // After sync, cache should be invalidated, so we get the new value
        $this->assertEquals(3.50, Setting::get('cover_charge'));
    }

    public function test_check_peer_connectivity(): void
    {
        Http::fake([
            'http://web.test/api/sync/status' => Http::response([
                'status' => 'ok',
                'role' => 'web',
                'master_tables' => ['users', 'allergens'],
                'watermarks' => [],
                'timestamp' => now()->toIso8601String(),
            ]),
        ]);

        $service = new SyncService('test');
        $result = $service->checkPeer();

        $this->assertTrue($result['connected']);
        $this->assertEquals('web', $result['peer']['role']);
    }
}
