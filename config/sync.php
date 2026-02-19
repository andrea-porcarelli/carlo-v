<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Instance Role
    |--------------------------------------------------------------------------
    |
    | Determines what data this instance is master of.
    | 'web' = backoffice (piatti, categorie, allergeni, ecc.)
    | 'local' = POS/frontoffice (tavoli, ordini, ecc.)
    |
    */
    'role' => env('SYNC_INSTANCE_ROLE', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Peer Connection
    |--------------------------------------------------------------------------
    */
    'peer_url' => env('SYNC_PEER_URL', 'http://internal.carlov.it'),
    'api_token' => env('SYNC_API_TOKEN', ''),
    'timeout' => (int) env('SYNC_TIMEOUT', 120),
    'batch_size' => (int) env('SYNC_BATCH_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Master Tables per Role
    |--------------------------------------------------------------------------
    |
    | Tables that each role is authoritative for.
    |
    */
    'master_tables' => [
        'web' => [
            'users',
            'allergens',
            'materials',
            'categories',
            'dishes',
            'dish_allergens',
            'dish_materials',
            'material_stocks',
            'suppliers',
            'supplier_invoices',
            'supplier_invoice_products',
            'mapping_products',
            'media',
        ],
        'local' => [
            'printers',
            'restaurant_tables',
            'table_orders',
            'order_items',
            'table_order_logs',
            'print_logs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Order (respects FK dependencies)
    |--------------------------------------------------------------------------
    */
    'sync_order' => [
        'web' => [
            'users',
            'allergens',
            'materials',
            'categories',
            'dishes',
            'dish_allergens',
            'dish_materials',
            'material_stocks',
            'settings',
            'suppliers',
            'supplier_invoices',
            'supplier_invoice_products',
            'mapping_products',
            'media',
        ],
        'local' => [
            'printers',
            'restaurant_tables',
            'table_orders',
            'order_items',
            'table_order_logs',
            'print_logs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Map (table name => Eloquent model class)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'users' => \App\Models\User::class,
        'allergens' => \App\Models\Allergen::class,
        'materials' => \App\Models\Material::class,
        'categories' => \App\Models\Category::class,
        'dishes' => \App\Models\Dish::class,
        'material_stocks' => \App\Models\MaterialStock::class,
        'settings' => \App\Models\Setting::class,
        'suppliers' => \App\Models\Supplier::class,
        'supplier_invoices' => \App\Models\SupplierInvoice::class,
        'supplier_invoice_products' => \App\Models\SupplierInvoiceProduct::class,
        'mapping_products' => \App\Models\MappingProduct::class,
        'media' => \App\Models\Media::class,
        'printers' => \App\Models\Printer::class,
        'restaurant_tables' => \App\Models\RestaurantTable::class,
        'table_orders' => \App\Models\TableOrder::class,
        'order_items' => \App\Models\OrderItem::class,
        'table_order_logs' => \App\Models\TableOrderLog::class,
        'print_logs' => \App\Models\PrintLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pivot Tables (no Eloquent model, direct DB operations)
    |--------------------------------------------------------------------------
    */
    'pivot_tables' => [
        'dish_allergens',
        'dish_materials',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables with Soft Deletes
    |--------------------------------------------------------------------------
    */
    'soft_delete_tables' => [
        'restaurant_tables',
        'table_orders',
        'order_items',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unique Key for Settings (upsert by 'key' instead of 'id')
    |--------------------------------------------------------------------------
    */
    'upsert_keys' => [
        'settings' => 'key',
    ],

];
