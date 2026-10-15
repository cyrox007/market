<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'laravel' || ! in_array(config('database.connections.mysql.host'), ['mysql-8.4', 'localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Only local laravel permitted');
}
config(['cache.default' => 'array', 'services.catalog_import.enabled' => false]);
Illuminate\Support\Facades\Http::fake();
Illuminate\Support\Facades\DB::beginTransaction();
try {
    $sku = App\Models\Product\Product::factory()->create(['price' => 1000]);
    $user = App\Models\User::factory()->create();
    $rule = App\Models\Pricing\PricingRule::create(['name' => 'Temporary SQL verification', 'target_type' => 'products', 'product_ids' => [$sku->id], 'audience_type' => 'users', 'user_ids' => [$user->id], 'is_active' => true, 'adjustment_type' => 'discount_percent', 'amount' => 12.5, 'priority' => 10000]);
    $engine = app(App\Services\Pricing\PricingEngine::class);
    $personal = $engine->explain($sku, null, $user, true);
    $guest = $engine->explain($sku, null, null, true);
    if ($personal['price'] !== 875.0 || $guest['price'] !== 1000.0 || $personal['rule_id'] !== $rule->id) {
        throw new RuntimeException('MySQL personalized pricing mismatch');
    }
    echo json_encode(['status' => 'mysql_pricing_passed', 'personal_price' => $personal['price'], 'guest_price' => $guest['price'], 'fixtures' => 'rolled_back']);
} finally {
    Illuminate\Support\Facades\DB::rollBack();
}
