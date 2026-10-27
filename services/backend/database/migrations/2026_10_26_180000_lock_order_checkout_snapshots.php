<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->json('checkout_snapshot')->nullable());
        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                $address = $order->address_id ? DB::table('user_addresses')->find($order->address_id) : null;
                $data = $address ? (array) $address : null;
                if ($data && filled($order->address_snapshot)) { $data['full_address'] = $order->address_snapshot; }
                DB::table('orders')->where('id', $order->id)->update(['checkout_snapshot' => json_encode(['address' => $data], JSON_UNESCAPED_UNICODE)]);
            }
        });
    }
    public function down(): void { Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('checkout_snapshot')); }
};
