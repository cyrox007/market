<?php

namespace Tests\Feature\Admin;

use App\Services\Product\ProductDescriptionHtml;
use Tests\TestCase;

class ProductDescriptionEditorTest extends TestCase
{
    public function test_plain_text_keeps_line_breaks_and_escapes_markup_characters(): void
    {
        $html = app(ProductDescriptionHtml::class)->forEditor("Первая строка\nВторая & третья");
        $this->assertStringContainsString('<br>', $html);
        $this->assertStringContainsString('Вторая &amp; третья', $html);
    }

    public function test_existing_paragraphs_lists_and_links_are_preserved(): void
    {
        $html = app(ProductDescriptionHtml::class)->forEditor('<p>Описание<br>Продолжение</p><ul><li>ЛДСП</li></ul><a href="https://example.com">Подробнее</a>');
        foreach (['<p>', '<br', '<ul>', '<li>', 'https://example.com'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
    }

    public function test_active_html_is_removed_before_storage(): void
    {
        $html = app(ProductDescriptionHtml::class)->sanitize('<p onclick="alert(1)">Текст</p><script>alert(1)</script><a href="javascript:alert(1)">Ссылка</a>');
        foreach (['onclick', '<script', 'javascript:'] as $part) {
            $this->assertStringNotContainsString($part, $html);
        }
        $this->assertStringContainsString('Текст', $html);
    }

    public function test_null_description_is_empty(): void
    {
        $this->assertSame('<p></p>', app(ProductDescriptionHtml::class)->forEditor(null));
    }

    public function test_product_form_opens_and_saves_plain_text_with_visual_editor(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $user = \App\Models\User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin_sv'));
        $product = \App\Models\Product\Product::factory()->create(['description' => "Первая строка\nВторая строка"]);
        \Livewire\Livewire::test(\App\Filament\Resources\Products\Pages\EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertStatus(200)->call('save', false)->assertHasNoFormErrors();
        $this->assertStringContainsString('Первая строка', $product->fresh()->description);
        $this->assertStringContainsString('<br', $product->fresh()->description);
        $this->assertStringContainsString('Вторая строка', $product->fresh()->description);
    }

    public function test_existing_table_image_and_list_survive_form_save(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web');
        $user = \App\Models\User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin_sv'));
        $product = \App\Models\Product\Product::factory()->create(['description' => '<h2>Размеры</h2><table><tbody><tr><td>Ширина</td><td>130 см</td></tr></tbody></table><p><img src="https://example.com/photo.jpg" alt="Антресоль"></p><ol><li>Первый пункт</li></ol>']);
        \Livewire\Livewire::test(\App\Filament\Resources\Products\Pages\EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertStatus(200)->assertSee('aria-label="Жирный"', false)
            ->call('save', false)->assertHasNoFormErrors();
        $html = $product->fresh()->description;
        foreach (['<h2>', '<table', '<td', '130 см', '<img', 'https://example.com/photo.jpg', 'Антресоль', '<ol', 'Первый пункт'] as $part) {
            $this->assertStringContainsString($part, $html);
        }
    }

    public function test_editor_disables_new_file_uploads(): void
    {
        $editor = \App\Filament\Forms\ProductDescriptionEditor::make('description')->fileAttachments(false);
        $this->assertFalse($editor->hasFileAttachments());
    }
}
