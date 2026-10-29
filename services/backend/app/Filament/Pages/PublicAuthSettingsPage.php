<?php

namespace App\Filament\Pages;

use App\Models\Settings\PublicAuthSettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PublicAuthSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;
    protected static ?string $navigationLabel = 'Вход и регистрация на сайте';
    protected static ?string $title = 'Вход и регистрация на сайте';
    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 20;
    protected static ?string $slug = 'public-auth-settings';

    public bool $registrationEnabled = true;
    public bool $loginEnabled = true;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = PublicAuthSettings::current();
        $this->registrationEnabled = $settings->registration_enabled;
        $this->loginEnabled = $settings->login_enabled;
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['registrationEnabled' => 'required|boolean', 'loginEnabled' => 'required|boolean']);
        PublicAuthSettings::updateOrCreate(['id' => 1], [
            'registration_enabled' => $this->registrationEnabled,
            'login_enabled' => $this->loginEnabled,
        ]);
        Notification::make()->title('Настройки сохранены')->success()->send();
    }

    public function getView(): string
    {
        return 'filament.pages.public-auth-settings';
    }
}
