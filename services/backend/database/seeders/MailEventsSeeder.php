<?php

namespace Database\Seeders;

use App\Models\Mail\MailEvent;
use App\Models\Mail\MailEventTemplate;
use Illuminate\Database\Seeder;

class MailEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Событие создания заказа
        $orderCreated = MailEvent::firstOrCreate(
            ['code' => 'order.created'],
            [
                'name' => 'Создание заказа',
                'description' => 'Отправляется при создании нового заказа',
                'is_active' => true,
            ]
        );

        MailEventTemplate::firstOrCreate(
            [
                'mail_event_id' => $orderCreated->id,
                'is_default' => true,
            ],
            [
                'name' => 'Шаблон по умолчанию',
                'subject' => 'Ваш заказ №{{order_number}} создан',
                'body' => "Здравствуйте, {{contact_name}}!\n\nВаш заказ №{{order_number}} успешно создан.\n\nДетали заказа:\n- Номер заказа: {{order_number}}\n- Сумма заказа: {{order_total}}\n- Статус: {{order_status}}\n- Способ оплаты: {{payment_method}}\n- Тип доставки: {{delivery_type}}\n\nДата создания: {{order_date}}\n\nСпасибо за ваш заказ!",
                'variables' => [
                    'order_number',
                    'order_id',
                    'order_total',
                    'order_subtotal',
                    'order_delivery_cost',
                    'order_assembly_cost',
                    'order_status',
                    'contact_name',
                    'contact_phone',
                    'contact_email',
                    'delivery_type',
                    'payment_method',
                    'order_date',
                ],
                'is_active' => true,
                'is_default' => true,
            ]
        );

        // Событие регистрации пользователя
        $userRegistered = MailEvent::firstOrCreate(
            ['code' => 'user.registered'],
            [
                'name' => 'Регистрация пользователя',
                'description' => 'Отправляется при автоматической регистрации пользователя',
                'is_active' => true,
            ]
        );

        MailEventTemplate::firstOrCreate(
            [
                'mail_event_id' => $userRegistered->id,
                'is_default' => true,
            ],
            [
                'name' => 'Шаблон по умолчанию',
                'subject' => 'Добро пожаловать! Ваш аккаунт создан',
                'body' => "Здравствуйте, {{user_name}}!\n\nДля вас автоматически создан аккаунт на нашем сайте.\n\nДанные для входа:\n- Email: {{user_email}}\n- Пароль: {{password}}\n\nВы можете войти в личный кабинет по ссылке: {{login_url}}\n\nРекомендуем изменить пароль после первого входа.\n\nС уважением,\nКоманда сайта",
                'variables' => [
                    'user_name',
                    'user_email',
                    'user_phone',
                    'password',
                    'login_url',
                ],
                'is_active' => true,
                'is_default' => true,
            ]
        );

        // Событие изменения статуса заказа
        $orderStatusChanged = MailEvent::firstOrCreate(
            ['code' => 'order.status_changed'],
            [
                'name' => 'Изменение статуса заказа',
                'description' => 'Отправляется при изменении статуса заказа',
                'is_active' => true,
            ]
        );

        MailEventTemplate::firstOrCreate(
            [
                'mail_event_id' => $orderStatusChanged->id,
                'is_default' => true,
            ],
            [
                'name' => 'Шаблон по умолчанию',
                'subject' => 'Статус вашего заказа №{{order_number}} изменен',
                'body' => "Здравствуйте, {{contact_name}}!\n\nСтатус вашего заказа №{{order_number}} изменен.\n\n- Предыдущий статус: {{old_status}}\n- Новый статус: {{new_status}}\n\nВы можете отслеживать статус заказа в личном кабинете.\n\nС уважением,\nКоманда сайта",
                'variables' => [
                    'order_number',
                    'order_id',
                    'old_status',
                    'new_status',
                    'order_total',
                    'contact_name',
                ],
                'is_active' => true,
                'is_default' => true,
            ]
        );

        // Событие отмены заказа
        $orderCancelled = MailEvent::firstOrCreate(
            ['code' => 'order.cancelled'],
            [
                'name' => 'Отмена заказа',
                'description' => 'Отправляется при отмене заказа',
                'is_active' => true,
            ]
        );

        MailEventTemplate::firstOrCreate(
            [
                'mail_event_id' => $orderCancelled->id,
                'is_default' => true,
            ],
            [
                'name' => 'Шаблон по умолчанию',
                'subject' => 'Ваш заказ №{{order_number}} отменен',
                'body' => "Здравствуйте, {{contact_name}}!\n\nВаш заказ №{{order_number}} был отменен.\n\nДата создания заказа: {{order_date}}\nСумма заказа: {{order_total}}\n\nЕсли у вас возникли вопросы, пожалуйста, свяжитесь с нами.\n\nС уважением,\nКоманда сайта",
                'variables' => [
                    'order_number',
                    'order_id',
                    'order_total',
                    'contact_name',
                    'order_date',
                ],
                'is_active' => true,
                'is_default' => true,
            ]
        );
    }
}
