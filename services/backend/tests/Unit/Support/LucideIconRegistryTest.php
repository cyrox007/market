<?php

namespace Tests\Unit\Support;

use App\Filament\Support\LucideIconSelect;
use App\Support\LucideIconRegistry;
use Filament\Forms\Components\Select;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LucideIconRegistryTest extends TestCase
{
    #[DataProvider('legacyIconProvider')]
    public function test_legacy_remixicon_values_are_normalized_to_lucide(string $legacy, string $expected): void
    {
        $this->assertSame($expected, LucideIconRegistry::normalizeLegacy($legacy));
    }

    public static function legacyIconProvider(): array
    {
        return [
            'line suffix' => ['ri-shopping-cart-line', 'shopping-cart'],
            'fill suffix' => ['ri-bank-card-fill', 'credit-card'],
            'legacy without suffix' => ['ri-truck', 'truck'],
            'mapped room icon' => ['ri-hotel-bed-line', 'bed-double'],
        ];
    }

    public function test_canonical_lucide_value_is_left_untouched(): void
    {
        $this->assertSame('shopping-cart', LucideIconRegistry::normalizeLegacy('shopping-cart'));
    }

    public function test_unknown_legacy_value_uses_safe_fallback(): void
    {
        $this->assertSame(LucideIconRegistry::FALLBACK, LucideIconRegistry::normalizeLegacy('ri-does-not-exist-line'));
    }

    public function test_empty_values_remain_empty(): void
    {
        $this->assertNull(LucideIconRegistry::normalizeLegacy(null));
        $this->assertNull(LucideIconRegistry::normalizeLegacy(''));
    }

    public function test_registry_only_exposes_valid_canonical_values(): void
    {
        foreach (LucideIconRegistry::values() as $icon) {
            $this->assertTrue(LucideIconRegistry::isValid($icon), $icon);
            $this->assertFalse(str_starts_with($icon, 'ri-'), $icon);
        }
    }

    public function test_filament_helper_builds_native_select(): void
    {
        $this->assertInstanceOf(Select::class, LucideIconSelect::make());
    }
}
