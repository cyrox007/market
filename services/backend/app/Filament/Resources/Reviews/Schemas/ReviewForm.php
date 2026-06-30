<?php

namespace App\Filament\Resources\Reviews\Schemas;

use App\Models\Product\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Информация о пользователе')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament/admin_sv/review_resource.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('filament/admin_sv/review_resource.email'))
                            ->email()
                            ->maxLength(255)
                            ->helperText('Email необязателен, но может быть полезен для связи с автором отзыва'),
                    ])
                    ->columns(2),

                Section::make('Информация о товаре')
                    ->schema([
                        Select::make('product_id')
                            ->label(__('filament/admin_sv/review_resource.product_id'))
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Выберите товар, к которому относится отзыв'),
                    ]),

                Section::make('Отзыв')
                    ->schema([
                        Select::make('rating')
                            ->label(__('filament/admin_sv/review_resource.rating'))
                            ->options(['1' => __('filament/admin_sv/review_resource.rating.1звезда'), '2' => __('filament/admin_sv/review_resource.rating.2звезды'), '3' => __('filament/admin_sv/review_resource.rating.3звезды'), '4' => __('filament/admin_sv/review_resource.rating.4звезды'), '5' => __('filament/admin_sv/review_resource.rating.5звезд')])
                            ->required()
                            ->default(5)
                            ->native(false),

                        Textarea::make('comment')
                            ->label(__('filament/admin_sv/review_resource.comment'))
                            ->required()
                            ->minLength(10)
                            ->maxLength(5000)
                            ->rows(5)
                            ->helperText('Минимум 10 символов, максимум 5000 символов')
                            ->columnSpanFull(),

                        Toggle::make('is_approved')
                            ->label(__('filament/admin_sv/review_resource.is_approved'))
                            ->default(false)
                            ->helperText('Одобренные отзывы отображаются на сайте. Новые отзывы требуют модерации'),
                    ]),
            ]);
    }
}
