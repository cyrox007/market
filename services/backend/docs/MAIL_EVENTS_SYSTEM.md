# Система почтовых событий

## Обзор

Система почтовых событий позволяет управлять отправкой писем через админку Filament, аналогично системе в Битрикс. Каждое почтовое событие имеет код, название, описание и может иметь несколько шаблонов писем.

## Архитектура

### Модели

- **MailEvent** - почтовое событие (например, `order.created`, `user.registered`)
- **MailEventTemplate** - шаблон письма для события (может быть несколько шаблонов на событие)
- **MailEventLog** - лог отправки письма (для отслеживания и отладки)

### Сервисы

- **MailEventService** - основной сервис для отправки писем на основе событий

### События и слушатели

- `OrderCreated` → `SendOrderCreatedMail` - отправка письма при создании заказа
- `UserAutoRegistered` → `SendUserRegisteredMail` - отправка письма при автоматической регистрации
- `OrderStatusChanged` → `SendOrderStatusChangedMail` - отправка письма при изменении статуса
- `OrderCancelled` → `SendOrderCancelledMail` - отправка письма при отмене заказа

## Использование

### Отправка письма через сервис

**Рекомендуемый способ - через Dependency Injection:**

```php
use App\Services\Mail\MailEventService;

class YourController
{
    public function __construct(
        protected MailEventService $mailService
    ) {}

    public function someMethod()
    {
        // Простая отправка
        $this->mailService->send('order.created', 'user@example.com', [
            'order_number' => 'ORD123',
            'order_total' => '5000 ₽',
            'contact_name' => 'Иван Иванов',
        ]);

        // Отправка с Mailable классом
        $mailable = new OrderCreatedMail($order);
        $this->mailService->sendMailable('order.created', 'user@example.com', $mailable, [
            'order_number' => $order->number,
        ]);
    }
}
```

**Пример в слушателе события:**

```php
use App\Services\Mail\MailEventService;

class SendOrderCreatedMail
{
    public function __construct(
        protected MailEventService $mailEventService
    ) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->getOrder();
        
        $this->mailEventService->send('order.created', $order->contact_email, [
            'order_number' => $order->number,
            'order_total' => number_format($order->total, 2, '.', ' ') . ' ₽',
        ]);
    }
}
```

> ⚠️ **Важно:** Использование `app(MailEventService::class)` (service locator) допустимо только в исключительных случаях, когда dependency injection невозможен. В большинстве случаев используйте конструктор для внедрения зависимостей.

### Переменные в шаблонах

В шаблонах писем можно использовать переменные в формате `{{переменная}}`. При отправке письма переменные автоматически заменяются на значения.

Пример шаблона:
```
Тема: Ваш заказ №{{order_number}} создан

Здравствуйте, {{contact_name}}!

Ваш заказ №{{order_number}} на сумму {{order_total}} успешно создан.
```

### Добавление нового события

1. Создайте событие в админке Filament (раздел "Почтовые события")
   - Укажите уникальный код (например, `product.back_in_stock`)
   - Добавьте название и описание
   - Активируйте событие

2. Создайте шаблон для события
   - В разделе редактирования события перейдите на вкладку "Шаблоны писем"
   - Создайте новый шаблон с темой и текстом письма
   - Укажите доступные переменные в формате JSON

3. Используйте в коде через Dependency Injection:
```php
use App\Services\Mail\MailEventService;

class YourController
{
    public function __construct(
        protected MailEventService $mailService
    ) {}

    public function notifyProductBackInStock(Product $product, User $user)
    {
        $this->mailService->send('product.back_in_stock', $user->email, [
            'product_name' => $product->name,
            'product_url' => url("/products/{$product->id}"),
        ]);
    }
}
```

## Автоматическая регистрация пользователей

При оформлении заказа без авторизации система автоматически:

1. Проверяет, существует ли пользователь с указанным email
2. Если пользователя нет - создает нового со случайным паролем
3. Автоматически авторизует пользователя
4. Привязывает заказ к созданному пользователю
5. Отправляет письмо с данными аккаунта (email и пароль)

### Логика в OrderController

Автоматическая регистрация происходит в методе `OrderController::store()`:

```php
// Если пользователь не авторизован
if (!$user) {
    $existingUser = User::where('email', $validated['contact_email'])->first();
    
    if (!$existingUser) {
        // Создаем нового пользователя
        $password = Str::random(12);
        $user = User::create([...]);
        Auth::login($user);
        
        // Отправляем событие регистрации
        event(new UserAutoRegistered($user, $password));
    } else {
        // Авторизуем существующего пользователя
        Auth::login($existingUser);
    }
}
```

## Управление через Filament

### Почтовые события

В разделе "Настройки" → "Почтовые события" можно:

- Просматривать список всех событий
- Создавать новые события
- Редактировать существующие события
- Активировать/деактивировать события

### Шаблоны писем

Для каждого события можно создать несколько шаблонов:

- Название шаблона
- Тема письма (с поддержкой переменных)
- Текст письма (с поддержкой переменных)
- Список доступных переменных (JSON)
- Флаг "Шаблон по умолчанию"
- Флаг "Активен"

### Логи отправки

Все отправленные письма логируются в таблице `mail_event_logs`:

- Событие и шаблон
- Email получателя
- Тема и текст письма
- Статус отправки (pending, sent, failed)
- Сообщение об ошибке (если отправка не удалась)
- Время отправки
- Использованные переменные

## Права доступа

Система использует Spatie Laravel Permission для управления правами:

- `viewAny mail_events` - просмотр списка событий
- `view mail_events` - просмотр события
- `create mail_events` - создание событий
- `update mail_events` - редактирование событий
- `delete mail_events` - удаление событий

Роли:
- **Admin** - полный доступ ко всем операциям
- **Manager** - управление шаблонами, просмотр логов

## Предустановленные события

При выполнении сидера `MailEventsSeeder` создаются следующие события:

1. **order.created** - Создание заказа
   - Переменные: `order_number`, `order_total`, `contact_name`, `payment_method`, и др.

2. **user.registered** - Регистрация пользователя
   - Переменные: `user_name`, `user_email`, `password`, `login_url`

3. **order.status_changed** - Изменение статуса заказа
   - Переменные: `order_number`, `old_status`, `new_status`, `contact_name`

4. **order.cancelled** - Отмена заказа
   - Переменные: `order_number`, `order_total`, `contact_name`, `order_date`

## Примеры использования

### Отправка письма при создании заказа

```php
// В слушателе SendOrderCreatedMail
class SendOrderCreatedMail
{
    public function __construct(
        protected MailEventService $mailEventService
    ) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->getOrder();
        
        $variables = [
            'order_number' => $order->number,
            'order_total' => number_format($order->total, 2, '.', ' ') . ' ₽',
            'contact_name' => $order->contact_name,
            // ...
        ];

        $this->mailEventService->sendMailable(
            'order.created',
            $order->contact_email,
            new OrderCreatedMail($order),
            $variables
        );
    }
}
```

### Добавление нового события для уведомления о наличии товара

1. В админке создайте событие с кодом `product.back_in_stock`
2. Создайте шаблон:
   ```
   Тема: Товар {{product_name}} снова в наличии
   
   Здравствуйте!
   
   Товар {{product_name}} снова доступен для заказа.
   Перейти к товару: {{product_url}}
   ```
3. В коде через Dependency Injection:
```php
use App\Services\Mail\MailEventService;

class ProductController
{
    public function __construct(
        protected MailEventService $mailService
    ) {}

    public function notifyBackInStock(Product $product, User $user)
    {
        $this->mailService->send('product.back_in_stock', $user->email, [
            'product_name' => $product->name,
            'product_url' => url("/products/{$product->id}"),
        ]);
    }
}
```

## Тестирование

### Unit тесты

- `MailEventServiceTest` - тестирование отправки писем
- Проверка замены переменных
- Проверка обработки ошибок

### Feature тесты

- `OrderControllerAutoRegistrationTest` - тестирование автоматической регистрации
- Проверка создания пользователя при заказе
- Проверка авторизации существующего пользователя
- Проверка отсутствия создания пользователя для авторизованных

## Отладка

### Просмотр логов отправки

Все отправленные письма сохраняются в таблице `mail_event_logs`. В админке можно просмотреть:

- Какие письма были отправлены
- Какие шаблоны использовались
- Какие переменные были подставлены
- Статус отправки (успешно/ошибка)
- Сообщения об ошибках

### Тестирование отправки

Для тестирования можно использовать драйвер `log` в конфигурации почты (`config/mail.php`):

```php
'default' => env('MAIL_MAILER', 'log'),
```

В этом случае письма будут записываться в лог-файл вместо реальной отправки.

## Расширение системы

### Добавление нового типа письма

1. Создайте Mailable класс (если нужен):
```php
namespace App\Mail;

class ProductBackInStockMail extends Mailable
{
    public function __construct(public Product $product) {}
    
    // ...
}
```

2. Создайте слушатель события:
```php
namespace App\Listeners\Mail;

class SendProductBackInStockMail
{
    public function handle(ProductBackInStock $event): void
    {
        $mailService = app(MailEventService::class);
        // ...
    }
}
```

3. Зарегистрируйте слушатель в `EventServiceProvider`

4. Создайте событие и шаблон в админке

## API для разработчиков

### MailEventService

#### send(string $eventCode, string $recipientEmail, array $variables = [], ?MailEventTemplate $template = null): bool

Отправляет письмо на основе события.

**Параметры:**
- `$eventCode` - код события (например, 'order.created')
- `$recipientEmail` - email получателя
- `$variables` - массив переменных для подстановки
- `$template` - конкретный шаблон (опционально, если не указан, используется default)

**Возвращает:** `true` если отправка успешна, `false` в противном случае

#### sendMailable(string $eventCode, string $recipientEmail, $mailable, array $variables = []): bool

Отправляет письмо используя Mailable класс.

**Параметры:**
- `$eventCode` - код события
- `$recipientEmail` - email получателя
- `$mailable` - экземпляр Mailable класса
- `$variables` - массив переменных для подстановки

**Возвращает:** `true` если отправка успешна, `false` в противном случае

#### getAvailableVariables(string $eventCode): array

Получает список доступных переменных для события.

**Параметры:**
- `$eventCode` - код события

**Возвращает:** массив названий переменных

## Миграции

Система использует следующие таблицы:

- `mail_events` - события
- `mail_event_templates` - шаблоны писем
- `mail_event_logs` - логи отправки

Миграции находятся в `database/migrations/2026_01_22_100000_*.php`

## Сидеры

Сидер `MailEventsSeeder` создает начальные события и шаблоны. Запускается автоматически при выполнении `DatabaseSeeder`.
