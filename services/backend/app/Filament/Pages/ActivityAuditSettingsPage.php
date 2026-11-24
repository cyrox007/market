<?php

namespace App\Filament\Pages;

use App\Services\Audit\ActivityAuditSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

final class ActivityAuditSettingsPage extends Page
{
    protected static ?string $navigationLabel = 'Настройки аудита';
    protected static ?string $title = 'Настройки аудита';
    protected static string|\UnitEnum|null $navigationGroup = 'Аудит и безопасность';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected string $view = 'filament.pages.activity-audit-settings';
    public bool $behavior = false;
    public array $retentionDays = [];

    public static function canAccess(): bool
    {
        return (bool) config('activity_audit.enabled') && (bool) auth()->user()?->hasRole('super_admin');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = app(ActivityAuditSettings::class)->get();
        $this->behavior = $settings['behavior'];
        $this->retentionDays = $settings['retention_days'];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        app(ActivityAuditSettings::class)->save(auth()->user(), ['behavior' => $this->behavior, 'retention_days' => $this->retentionDays]);
        Notification::make()->title('Настройки аудита сохранены')->success()->send();
    }
}
