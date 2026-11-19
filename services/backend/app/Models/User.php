<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Address\Address;
use App\Models\Bonus\BonusTransaction;
use App\Models\Chat\Chat;
use App\Models\Order\Order;
use App\Support\UserName;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'patronymic',
        'email',
        'password',
        'phone',
        'avatar',
        'email_order_notifications',
        'email_promotions',
        'sms_order_notifications',
        'push_notifications',
        'new_product_notifications',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'email_order_notifications' => 'boolean',
            'email_promotions' => 'boolean',
            'sms_order_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'new_product_notifications' => 'boolean',
        ];
    }

    /**
     * A single synchronization point for legacy writes, admin edits and profile API.
     * A legacy name change invalidates structured parts; structured edits rebuild name.
     */
    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            if ($user->wasChanged('email')) {
                \App\Models\Newsletter\NewsletterSubscriber::where('email', $user->getRawOriginal('email'))->update(['is_active' => false]);
            }
            if ($user->wasRecentlyCreated || $user->wasChanged(['email', 'email_promotions'])) {
                \App\Models\Newsletter\NewsletterSubscriber::updateOrCreate(
                    ['email' => strtolower(trim($user->email))], ['is_active' => (bool) $user->email_promotions],
                );
            }
        });
        static::saving(function (self $user): void {
            if ($user->exists && $user->isDirty('email')
                && strtolower(trim((string) $user->getRawOriginal('email'))) !== strtolower(trim($user->email))) {
                $user->auth_email_version = (int) $user->getRawOriginal('auth_email_version') + 1;
                $user->email_verified_at = null;
            }
            foreach (['first_name', 'last_name', 'patronymic'] as $field) {
                if ($user->isDirty($field)) {
                    $user->{$field} = UserName::normalize($user->{$field});
                }
            }

            if ($user->isDirty(['first_name', 'last_name', 'patronymic'])
                && $user->first_name !== null && $user->last_name !== null) {
                $user->name = UserName::format($user->first_name, $user->last_name, $user->patronymic);

                return;
            }

            if ($user->isDirty('name') && $user->exists) {
                // Old clients (and admin full-name edits) cannot be trusted to supply name parts.
                $user->first_name = null;
                $user->last_name = null;
                $user->patronymic = null;
            }
        });
    }

    /**
     * Determine whether the user can access the Filament panel (admin_sv).
     * Required for Filament 4 in production — without this, login to admin is denied.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'admin_sv') {
            return false;
        }

        return $this->hasAnyRole(['super_admin', 'admin', 'manager', 'editor', 'viewer']);
    }

    /**
     * Get the addresses for the user.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Get the orders for the user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the bonus transactions for the user.
     */
    public function bonusTransactions(): HasMany
    {
        return $this->hasMany(BonusTransaction::class);
    }

    /**
     * Get the chats for the user.
     */
    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    /**
     * Get the default address for the user.
     */
    public function defaultAddress(): ?Address
    {
        return $this->addresses()->where('is_default', true)->first();
    }
}
