<?php

namespace App\Filament\Resources\Mail\Tables;

use App\Models\Mail\MailEventTemplate;
use App\Services\Mail\MailTemplateContent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class MailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Шаблон')->searchable()->sortable()->weight('bold'),
            TextColumn::make('mailEvent.name')->label('Событие')->searchable(),
            TextColumn::make('subject')->label('Тема')->limit(65),
            IconColumn::make('is_default')->label('Основной')->boolean(),
            IconColumn::make('is_active')->label('Активен')->boolean(),
            TextColumn::make('updated_at')->label('Обновлён')->dateTime()->sortable(),
        ])->recordActions([
            Action::make('preview')
                ->label('Предпросмотр')
                ->icon('heroicon-o-eye')
                ->modalHeading('Предпросмотр электронного письма')
                ->modalSubmitAction(false)
                ->modalContent(function (MailEventTemplate $record): HtmlString {
                    // Only sample values are used. Access tokens must never be generated for previews.
                    $examples = [
                        'contact_name' => 'Покупатель',
                        'order_number' => '100001',
                        'order_total' => '45 000 ₽',
                        'order_subtotal' => '43 000 ₽',
                        'order_delivery_cost' => '2 000 ₽',
                        'order_assembly_cost' => '0 ₽',
                        'order_status' => 'Принят',
                        'payment_method' => 'Картой',
                        'delivery_type' => 'Доставка',
                        'order_date' => '28.10.2026',
                        'user_name' => 'Покупатель',
                        'user_email' => 'buyer@example.test',
                        'login_url' => 'https://example.test/login',
                        'old_status' => 'Новый',
                        'new_status' => 'Принят',
                    ];
                    $rendered = $record->replaceVariables($examples);
                    $note = $record->mailEvent?->code === 'order.created.guest'
                        ? '<p><em>Ссылка для открытия заказа добавится автоматически при реальной отправке. В предпросмотре она скрыта.</em></p>'
                        : '';

                    return new HtmlString(
                        '<div style="max-width:650px">'
                        .'<p><strong>Тема:</strong> '.e($rendered['subject']).'</p>'
                        .'<div style="border:1px solid #d1d5db;padding:18px;border-radius:8px">'
                        .MailTemplateContent::html($rendered['body']).'</div>'.$note.'</div>'
                    );
                }),
            EditAction::make(),
            DeleteAction::make(),
        ])->defaultSort('updated_at', 'desc');
    }
}
