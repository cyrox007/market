<?php

namespace App\Support;

/**
 * Приведение телефона к каноничному виду и проверка формата.
 * Канон — E.164 РФ: +7XXXXXXXXXX (мобильный и городской). Беларусь (+375) — на будущее.
 * Один источник нормализации для профиля пользователя и телефона заказа (1С).
 */
class PhoneNumber
{
    /**
     * Возвращает канон +7XXXXXXXXXX, null для пустого ввода,
     * либо исходную строку, если распознать не удалось (её отсеет валидация).
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed);

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7' . substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return '+7' . $digits;
        }

        // Не РФ-формат — отдаём как есть, решение за валидацией.
        return $trimmed;
    }

    /**
     * Валидный российский номер в каноничном виде: +7 и 10 цифр
     * (покрывает и мобильные +79XX…, и городские).
     */
    public static function isValidRu(?string $value): bool
    {
        return is_string($value) && preg_match('/^\+7\d{10}$/', $value) === 1;
    }
}
