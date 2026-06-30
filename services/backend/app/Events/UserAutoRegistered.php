<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Событие автоматической регистрации пользователя
 * Вызывается при автоматической регистрации пользователя при оформлении заказа
 */
class UserAutoRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public string $password
    ) {
    }

    /**
     * Получить пользователя
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * Получить пароль
     */
    public function getPassword(): string
    {
        return $this->password;
    }
}
