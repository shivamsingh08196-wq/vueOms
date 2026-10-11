<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::where('email', 'admin@vueoms.com')->first();
    $this->staff = User::where('email', 'staff@vueoms.com')->first();
});

it('returns the complete reports payload', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/reports')
        ->assertStatus(200)
        ->assertJsonStructure([
            'summary' => [
                'total_revenue',
                'total_purchases',
                'total_clients',
                'total_items',
                'total_items_sold',
                'avg_order_value',
            ],
            'purchases_by_status',
            'top_products_by_quantity',
            'top_products_by_price',
            'top_clients_by_orders',
            'top_clients_by_price',
            'per_client_report',
            'per_product_report',
            'filter_options' => ['years', 'months', 'statuses', 'clients', 'items'],
        ]);
});

it('filters the report by purchase status', function () {
    $response = $this->actingAs($this->admin)
        ->getJson('/api/reports?status=completed')
        ->assertStatus(200);

    foreach ($response->json('purchases_by_status') as $row) {
        expect($row['status'])->toBe('completed');
    }
});

it('filters the report by year and month', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/reports?year=2026&month=10')
        ->assertStatus(200);
});

it('views the detailed report per client or per product', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/reports?client_id=all&item_id=all')
        ->assertStatus(200)
        ->assertJsonPath('per_client_report.0.client_id', fn ($id) => is_numeric($id))
        ->assertJsonPath('per_product_report.0.item_id', fn ($id) => is_numeric($id));
});

it('exports every report type in every supported format', function () {
    $types = [
        'overview',
        'top_products_quantity',
        'top_products_price',
        'top_clients_orders',
        'top_clients_price',
        'per_client',
        'per_product',
    ];
    $formats = ['csv', 'xls', 'xlsx', 'pdf'];

    foreach ($types as $type) {
        foreach ($formats as $format) {
            $this->actingAs($this->admin)
                ->get("/api/reports/export?type={$type}&format={$format}")
                ->assertStatus(200);
        }
    }
});

it('forbids staff from viewing reports', function () {
    $this->actingAs($this->staff)->getJson('/api/reports')->assertStatus(403);
    $this->actingAs($this->staff)->get('/api/reports/export?type=overview&format=csv')->assertStatus(403);
});
