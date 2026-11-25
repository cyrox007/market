<?php

namespace App\Filament\Pages;

use App\Services\Integration\OneCOrdersAccessTokens;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use UnitEnum;

class OneCOrdersAccessPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Доступ 1С к заказам';

    protected static ?string $title = 'Доступ 1С к заказам';

    protected static string|UnitEnum|null $navigationGroup = 'Интеграции';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'onec-orders-access';

    protected string $view = 'filament.pages.onec-orders-access';

    public string $name = '';

    /** Expiration as a UTC calendar day, inclusive; blank = no expiration. */
    public string $expiresOn = '';

    /** Optional comma-separated exact IPv4/IPv6 addresses. */
    public string $allowedIps = '';

    public string $scope = 'orders:read';

    /** Visible only until this page is refreshed or explicitly dismissed. */
    #[Locked]
    public ?string $issuedToken = null;

    #[Locked]
    public ?int $issuedTokenId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('super_admin');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->issuedToken = null;
        $this->issuedTokenId = null;
    }

    public function issueToken(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'scope' => ['required', 'in:orders:read,orders:status:write'],
            'expiresOn' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'allowedIps' => ['nullable', 'string', 'max:1000'],
        ]);

        $ips = trim($this->allowedIps) === ''
            ? [] : preg_split('/[\s,;]+/', trim($this->allowedIps), -1, PREG_SPLIT_NO_EMPTY);

        $created = app(OneCOrdersAccessTokens::class)->issue(
            $this->name,
            $this->expiresOn !== '' ? $this->expiresOn : null,
            $ips,
            (int) auth()->id(),
            request()->ip(),
            [$this->scope]
        );
        $this->issuedToken = $created['token'];
        $this->issuedTokenId = $created['id'];
        $this->reset('name', 'expiresOn', 'allowedIps', 'scope');

        Notification::make()->title('Токен выпущен')
            ->body('Скопируйте новый секрет сейчас: после закрытия страницы его нельзя получить повторно.')
            ->success()->send();
    }

    public function dismissIssuedToken(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->issuedToken = null;
        $this->issuedTokenId = null;
    }

    public function revokeToken(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $revoked = app(OneCOrdersAccessTokens::class)->revoke($id, (int) auth()->id(), request()->ip());
        if ($this->issuedTokenId === $id) {
            $this->issuedToken = null;
            $this->issuedTokenId = null;
        }

        Notification::make()
            ->title($revoked ? 'Доступ 1С отозван' : 'Токен уже отозван или не найден')
            ->color($revoked ? 'success' : 'warning')->send();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);

        return [
            'tokens' => DB::table('onec_orders_access_tokens')
                ->select(['id', 'name', 'token_hint', 'abilities', 'allowed_ips',
                    'expires_at', 'revoked_at', 'created_at', 'last_used_at', 'last_used_ip', 'created_by'])
                ->orderByDesc('id')->limit(100)->get(),
            'events' => DB::table('onec_orders_access_token_events')
                ->select(['token_id', 'actor_id', 'event', 'ip', 'created_at'])
                ->orderByDesc('id')->limit(30)->get(),
        ];
    }
}
