<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table): void {
            // Preserve existing order emails. New accounts require marketing opt-in.
            $table->boolean('email_order_notifications')->default(true);
            $table->boolean('email_promotions')->default(false)->change();
        });
        Schema::table('mail_events', fn (Blueprint $table) => $table->string('category', 32)->default('transactional'));
        Schema::create('mail_promotion_limits', function (Blueprint $table): void {
            $table->char('recipient_hash', 64)->primary();
            $table->timestamp('last_sent_at')->nullable();
        });
        \Illuminate\Support\Facades\DB::table('users')->orderBy('id')->chunkById(200, function ($users): void {
            foreach ($users as $user) {
                \Illuminate\Support\Facades\DB::table('newsletter_subscribers')->updateOrInsert(
                    ['email' => strtolower(trim($user->email))],
                    ['is_active' => (bool) $user->email_promotions, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        });
    }
    public function down(): void {
        Schema::dropIfExists('mail_promotion_limits');
        Schema::table('mail_events', fn (Blueprint $table) => $table->dropColumn('category'));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('email_order_notifications');
            $table->boolean('email_promotions')->default(true)->change();
        });
    }
};
