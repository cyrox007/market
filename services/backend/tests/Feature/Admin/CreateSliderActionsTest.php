<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Sliders\Pages\CreateSlider;
use App\Filament\Resources\Sliders\Pages\EditSlider;
use App\Filament\Resources\Sliders\SliderResource;
use App\Models\Page\Slider;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateSliderActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('super_admin', 'web');
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getPanel('admin_sv'));
    }

    public function test_save_redirects_to_slider_list(): void
    {
        Livewire::test(CreateSlider::class)
            ->fillForm($this->sliderData('Обычное сохранение'))
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(SliderResource::getUrl('index'));

        $this->assertDatabaseHas('sliders', ['title' => 'Обычное сохранение']);
    }

    public function test_save_and_stay_redirects_to_created_slider_edit_page(): void
    {
        $component = Livewire::test(CreateSlider::class)
            ->fillForm($this->sliderData('Сохранение с продолжением'))
            ->call('saveAndStay')
            ->assertHasNoFormErrors();

        $slider = Slider::query()->where('title', 'Сохранение с продолжением')->firstOrFail();

        $component->assertRedirect(SliderResource::getUrl('edit', ['record' => $slider]));
    }

    public function test_edit_save_redirects_to_slider_list(): void
    {
        $slider = Slider::factory()->create();

        Livewire::test(EditSlider::class, ['record' => $slider->getRouteKey()])
            ->fillForm(['title' => 'Изменено с выходом'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(SliderResource::getUrl('index'));

        $this->assertSame('Изменено с выходом', $slider->fresh()->title);
    }

    public function test_edit_save_and_stay_does_not_redirect(): void
    {
        $slider = Slider::factory()->create();

        Livewire::test(EditSlider::class, ['record' => $slider->getRouteKey()])
            ->fillForm(['title' => 'Изменено без выхода'])
            ->call('saveAndStay')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();

        $this->assertSame('Изменено без выхода', $slider->fresh()->title);
    }

    /**
     * @return array<string, mixed>
     */
    private function sliderData(string $title): array
    {
        return [
            'placement' => Slider::PLACEMENT_TOP,
            'slot' => Slider::SLOT_MAIN,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'link_type' => 'manual',
            'is_active' => true,
            'priority' => 0,
        ];
    }
}
