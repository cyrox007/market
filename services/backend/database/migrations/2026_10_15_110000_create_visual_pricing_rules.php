<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pricing_product_groups' => 'product_ids', 'pricing_customer_groups' => 'user_ids'] as $name => $members) {
            Schema::create($name, function (Blueprint $table) use ($members) {
                $table->id();
                $table->string('name');
                $table->json($members);
                $table->timestamps();
            });
        }
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(false)->index();
            $table->string('target_type')->default('all');
            foreach (['product_ids', 'category_ids', 'room_ids', 'product_group_ids', 'excluded_product_ids', 'user_ids', 'customer_group_ids', 'location_ids'] as $field) {
                $table->json($field)->nullable();
            }
            $table->string('audience_type')->default('all');
            $table->json('conditions')->nullable();
            $table->string('adjustment_type');
            $table->decimal('amount', 14, 2);
            $table->integer('priority')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->json('pricing_snapshot')->nullable());
        Schema::create('pricing_rule_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pricing_rule_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rule_changes');
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('pricing_snapshot'));
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('pricing_customer_groups');
        Schema::dropIfExists('pricing_product_groups');
    }
};
