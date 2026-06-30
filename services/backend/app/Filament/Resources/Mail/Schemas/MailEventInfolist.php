<?php

namespace App\Filament\Resources\Mail\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MailEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Код события')
                            ->copyable()
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('name')
                            ->label('Название')
                            ->size('lg')
                            ->weight('bold'),
                        TextEntry::make('description')
                            ->label('Описание')
                            ->columnSpanFull()
                            ->placeholder('Описание отсутствует'),
                        TextEntry::make('is_active')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Активно' : 'Неактивно')
                            ->color(fn($state) => $state ? 'success' : 'danger'),
                        TextEntry::make('templates_count')
                            ->label('Количество шаблонов')
                            ->state(fn($record) => $record->templates()->count())
                            ->suffix(' шт.'),
                        TextEntry::make('logs_count')
                            ->label('Отправлено писем')
                            ->state(fn($record) => $record->logs()->count())
                            ->suffix(' шт.'),
                        TextEntry::make('created_at')
                            ->label('Создано')
                            ->dateTime('d.m.Y H:i')
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('updated_at')
                            ->label('Обновлено')
                            ->dateTime('d.m.Y H:i')
                            ->icon('heroicon-o-clock'),
                    ])
                    ->columns(3),

                Section::make('Документация и примеры использования')
                    ->schema([
                        TextEntry::make('documentation')
                            ->label('')
                            ->state(function ($record) {
                                return self::getDocumentation($record);
                            })
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    private static function getDocumentation($record): string
    {
        $code = $record->code;
        $name = $record->name;
        
        // Получаем примеры переменных из шаблонов
        $templates = $record->templates;
        $variables = [];
        foreach ($templates as $template) {
            if ($template->variables) {
                // variables уже массив из-за cast в модели
                $vars = is_array($template->variables) 
                    ? $template->variables 
                    : (is_string($template->variables) ? json_decode($template->variables, true) : []);
                if (is_array($vars)) {
                    $variables = array_merge($variables, $vars);
                }
            }
        }
        $variables = array_unique($variables);
        $variablesList = !empty($variables) 
            ? '<ul class="list-disc list-inside space-y-1"><li>' . implode('</li><li>', $variables) . '</li></ul>'
            : '<p class="text-gray-500">Переменные не указаны в шаблонах</p>';

        return <<<HTML
<div class="space-y-6">
    <div>
        <h3 class="text-lg font-semibold mb-2">📖 Описание</h3>
        <p class="text-gray-700 mb-4">{$name}</p>
        <p class="text-sm text-gray-600">Код события: <code class="bg-gray-100 px-2 py-1 rounded">{$code}</code></p>
    </div>

    <div>
        <h3 class="text-lg font-semibold mb-2">💻 Использование в коде</h3>
        <div class="bg-gray-50 p-4 rounded-lg mb-4">
            <p class="text-sm font-medium mb-2">Рекомендуемый способ - через Dependency Injection:</p>
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs overflow-x-auto"><code>use App\Services\Mail\MailEventService;

class YourController
{
    public function __construct(
        protected MailEventService \$mailService
    ) {}

    public function someMethod()
    {
        // Простая отправка
        \$this->mailService->send('{$code}', 'user@example.com', [
            'variable1' => 'значение1',
            'variable2' => 'значение2',
        ]);

        // С Mailable классом
        \$mailable = new YourMailableClass(\$data);
        \$this->mailService->sendMailable('{$code}', 'user@example.com', \$mailable, [
            'variable1' => 'значение1',
        ]);
    }
}</code></pre>
        </div>
        
        <div class="bg-yellow-50 p-4 rounded-lg mb-4">
            <p class="text-sm font-medium mb-2 text-yellow-900">⚠️ Альтернативный способ (не рекомендуется):</p>
            <p class="text-xs text-yellow-800 mb-2">Использование service locator через helper app() допустимо только в исключительных случаях:</p>
            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-xs overflow-x-auto"><code>use App\Services\Mail\MailEventService;

\$mailService = app(MailEventService::class);
\$mailService->send('{$code}', 'user@example.com', [
    'variable1' => 'значение1',
]);</code></pre>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold mb-2">📝 Доступные переменные</h3>
        <div class="bg-blue-50 p-4 rounded-lg">
            {$variablesList}
            <p class="text-xs text-gray-600 mt-3">
                💡 Переменные используются в шаблонах в формате <code>{{переменная}}</code>
            </p>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold mb-2">📧 Пример шаблона письма</h3>
        <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-sm font-medium mb-2">Тема письма:</p>
            <p class="text-sm text-gray-700 mb-4 bg-white p-2 rounded border">
                Уведомление о событии: {{variable1}}
            </p>
            <p class="text-sm font-medium mb-2">Текст письма:</p>
            <pre class="bg-white p-3 rounded text-xs border overflow-x-auto"><code>Здравствуйте!

Это уведомление о событии: {{variable1}}

Дополнительная информация: {{variable2}}

С уважением,
Команда сайта</code></pre>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold mb-2">🔧 Добавление нового события</h3>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
            <li>Создайте событие в админке (раздел "Почтовые события")</li>
            <li>Укажите уникальный код события (например, <code class="bg-gray-100 px-1 rounded">product.back_in_stock</code>)</li>
            <li>Добавьте название и описание</li>
            <li>Создайте шаблон письма с темой и текстом</li>
            <li>Укажите доступные переменные в формате JSON</li>
            <li>Используйте в коде через <code class="bg-gray-100 px-1 rounded">MailEventService</code></li>
        </ol>
    </div>

    <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg">
        <p class="text-sm font-medium text-yellow-900 mb-1">⚠️ Важно</p>
        <ul class="text-xs text-yellow-800 space-y-1 list-disc list-inside">
            <li>Код события должен быть уникальным</li>
            <li>Переменные в шаблонах должны совпадать с переменными, передаваемыми в коде</li>
            <li>Для отправки писем событие должно быть активным</li>
            <li>Если у события несколько шаблонов, используется шаблон по умолчанию</li>
        </ul>
    </div>
</div>
HTML;
    }
}
