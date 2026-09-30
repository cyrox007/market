<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function normalizationCases(): array
    {
        return [
            'уже канон'          => ['+79991234567', '+79991234567'],
            'ведущая 8'          => ['89991234567', '+79991234567'],
            'ведущая 7 без плюса' => ['79991234567', '+79991234567'],
            'человеческий формат' => ['8 (999) 123-45-67', '+79991234567'],
            '10 цифр без кода'   => ['9991234567', '+79991234567'],
            'городской'          => ['+7 (495) 123-45-67', '+74951234567'],
            'пустая строка'      => ['', null],
            'null'               => [null, null],
        ];
    }

    /**
     * @dataProvider normalizationCases
     */
    public function test_normalize(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public function test_normalize_leaves_unrecognized_input_untouched(): void
    {
        // Не РФ-формат отдаётся как есть — решение за валидацией.
        $this->assertSame('12345', PhoneNumber::normalize('12345'));
    }

    public function test_is_valid_ru(): void
    {
        $this->assertTrue(PhoneNumber::isValidRu('+79991234567'));  // мобильный
        $this->assertTrue(PhoneNumber::isValidRu('+74951234567'));  // городской
        $this->assertFalse(PhoneNumber::isValidRu('89991234567'));  // не канон
        $this->assertFalse(PhoneNumber::isValidRu('+7999123456'));  // мало цифр
        $this->assertFalse(PhoneNumber::isValidRu('+375291234567')); // РБ — пока не РФ
        $this->assertFalse(PhoneNumber::isValidRu(null));
    }
}
