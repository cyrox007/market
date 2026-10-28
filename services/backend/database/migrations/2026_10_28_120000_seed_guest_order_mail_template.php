<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $event = DB::table('mail_events')->where('code', 'order.created.guest')->first();
        if (! $event) {
            $id = DB::table('mail_events')->insertGetId([
                'code' => 'order.created.guest',
                'name' => 'Заказ без регистрации',
                'description' => 'Подтверждение заказа с защищённой ссылкой для неавторизованного покупателя',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $id = $event->id;
        }

        // Preserve templates already customized by administrators.
        if (DB::table('mail_event_templates')->where('mail_event_id', $id)->exists()) {
            return;
        }

        DB::table('mail_event_templates')->insert([
            'mail_event_id' => $id,
            'name' => 'Подтверждение гостевого заказа',
            'subject' => 'Ваш заказ №{{order_number}} создан',
            'body' => <<<'HTML'
<p>Здравствуйте, {{contact_name}}!</p>
<p>Ваш заказ <strong>№{{order_number}}</strong> успешно создан в магазине «Светофор мебели».</p>
<p>Сумма заказа: <strong>{{order_total}}</strong><br>Статус: {{order_status}}<br>Оплата: {{payment_method}}<br>Получение: {{delivery_type}}<br>Дата: {{order_date}}</p>
<p>Ссылка для открытия заказа с другого устройства добавляется ниже автоматически.</p>
<p>Благодарим за покупку!</p>
HTML,
            'variables' => json_encode([
                'contact_name', 'order_number', 'order_total', 'order_subtotal',
                'order_delivery_cost', 'order_assembly_cost', 'order_status',
                'payment_method', 'delivery_type', 'order_date',
            ], JSON_UNESCAPED_UNICODE),
            'is_active' => true,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Never delete customer-edited templates or events when rolling back.
    }
};
