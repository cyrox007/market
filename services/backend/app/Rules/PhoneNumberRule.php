<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Проверяет российский номер телефона. Ожидает уже нормализованное значение
 * (контроллер прогоняет ввод через PhoneNumber::normalize до валидации),
 * поэтому уникальность и проверка формата работают по одному каноничному виду.
 */
class PhoneNumberRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! PhoneNumber::isValidRu(is_string($value) ? $value : null)) {
            $fail('Некорректный номер телефона. Ожидается российский мобильный или городской номер.');
        }
    }
}
