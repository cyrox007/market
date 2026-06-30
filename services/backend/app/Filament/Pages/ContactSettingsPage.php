<?php

namespace App\Filament\Pages;

use App\Models\Settings\ContactSettings;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class ContactSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        $settings = ContactSettings::getInstance();

        $phones = $settings->phones ?? [];
        $emails = $settings->emails ?? [];
        $socialNetworks = $settings->social_networks ?? [];
        $workingHours = $settings->working_hours ?? [];

        if (!is_array($phones)) {
            $phones = [];
        }
        if (!is_array($emails)) {
            $emails = [];
        }
        if (!is_array($socialNetworks)) {
            $socialNetworks = [];
        }
        if (!is_array($workingHours)) {
            $workingHours = [];
        }

        $phones = array_values($phones);
        $emails = array_values($emails);
        $socialNetworks = array_values($socialNetworks);
        $workingHours = array_values($workingHours);

        $this->data = [
            'company_name' => $settings->company_name ?? null,
            'address' => $settings->address ?? null,
            'phones' => $phones,
            'emails' => $emails,
            'social_networks' => $socialNetworks,
            'map_embed' => $settings->map_embed ?? null,
            'working_hours' => $workingHours,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/admin_sv/contact_settings_page.основнаяинформация'))
                    ->schema([
                        TextInput::make('company_name')
                            ->label(__('filament/admin_sv/contact_settings_page.названиекомпании'))
                            ->maxLength(255),
                        Textarea::make('address')
                            ->label(__('filament/admin_sv/contact_settings_page.адрес'))
                            ->rows(3),
                    ])->columns(1),
                Section::make(__('filament/admin_sv/contact_settings_page.контакты'))
                    ->schema([
                        Repeater::make('phones')
                            ->label(__('filament/admin_sv/contact_settings_page.телефоны'))
                            ->schema([
                                TextInput::make('phone')
                                    ->label(__('filament/admin_sv/contact_settings_page.телефон'))
                                    ->maxLength(255),
                            ])
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->itemLabel(fn(array $state): ?string => $state['phone'] ?? null),
                        Repeater::make('emails')
                            ->label(__('filament/admin_sv/contact_settings_page.emailадреса'))
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('filament/admin_sv/contact_settings_page.email'))
                                    ->email()
                                    ->maxLength(255),
                            ])
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->itemLabel(fn(array $state): ?string => $state['email'] ?? null),
                    ]),
                Section::make(__('filament/admin_sv/contact_settings_page.социальныесети'))
                    ->schema([
                        Repeater::make('social_networks')
                            ->label(__('filament/admin_sv/contact_settings_page.социальныесети'))
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament/admin_sv/contact_settings_page.название'))
                                    ->maxLength(255),
                                TextInput::make('url')
                                    ->label(__('filament/admin_sv/contact_settings_page.u_r_l'))
                                    ->url()
                                    ->maxLength(255),
                            ])
                            ->reorderable(false)
                            ->defaultItems(0)
                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? null),
                    ]),
                Section::make(__('filament/admin_sv/contact_settings_page.картаирабочиечасы'))
                    ->schema([
                        Textarea::make('map_embed')
                            ->label(__('filament/admin_sv/contact_settings_page.кодвстраиваниякарты'))
                            ->rows(5)
                            ->helperText('HTML код для встраивания карты (iframe)'),
                        Repeater::make('working_hours')
                            ->label(__('filament/admin_sv/contact_settings_page.рабочиечасы'))
                            ->reorderable(false)
                            ->schema([
                                TextInput::make('day')
                                    ->label(__('filament/admin_sv/contact_settings_page.деньнедели'))
                                    ->maxLength(255),
                                TextInput::make('hours')
                                    ->label(__('filament/admin_sv/contact_settings_page.часыработы'))
                                    ->maxLength(255)
                                    ->placeholder('9:00 - 18:00'),
                            ])
                            ->defaultItems(0)
                            ->itemLabel(fn(array $state): ?string => ($state['day'] ?? '') . ' - ' . ($state['hours'] ?? '')),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();
            $data = $this->normalizeRepeaterData($data);

            DB::transaction(function () use ($data) {
                $settings = ContactSettings::getInstance();
                $settings->update($data);
            });

            \Filament\Notifications\Notification::make()
                ->title(__('filament/admin_sv/contact_settings_page.настройкисохранены'))
                ->success()
                ->send();

            $this->mount();
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title(__('filament/admin_sv/contact_settings_page.ошибкасохранения'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function normalizeRepeaterData(array $data): array
    {
        $repeaterFields = ['phones', 'emails', 'social_networks', 'working_hours'];

        foreach ($repeaterFields as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                $data[$field] = [];
                continue;
            }

            $data[$field] = array_values(array_filter($data[$field], function ($item) {
                return is_array($item) && !empty(array_filter($item, fn($value) => $value !== null && $value !== ''));
            }));
        }

        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament/admin_sv/contact_settings_page.save'))
                ->submit('save'),
        ];
    }

    public function getCachedFormActions(): array
    {
        return $this->getFormActions();
    }

    public function getView(): string
    {
        return 'filament.pages.contact-settings-page';
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/contact_settings_page.title');
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/contact_settings_page.navigation_label');
    }


}
