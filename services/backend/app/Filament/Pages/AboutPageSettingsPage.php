<?php

namespace App\Filament\Pages;

use App\Models\Page\AboutPage;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use RalphJSmit\Filament\SEO\SEO;
use UnitEnum;

class AboutPageSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static string|UnitEnum|null $navigationGroup = 'Контент';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public function mount(): void
    {
        $aboutPage = AboutPage::getInstance();

        $this->data = [
            'hero_title' => $aboutPage->hero_title ?? null,
            'hero_description' => $aboutPage->hero_description ?? null,
            'story_title' => $aboutPage->story_title ?? null,
            'story_content' => $aboutPage->story_content ?? null,
            'statistics' => $aboutPage->statistics ?? [
                'years' => 0,
                'stores' => 0,
                'clients' => 0,
                'products' => 0,
            ],
            'is_active' => $aboutPage->is_active ?? true,
            'priority' => $aboutPage->priority ?? 0,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/admin_sv/about_page_settings_page.heroсекция'))
                    ->schema([
                        TextInput::make('hero_title')
                            ->label(__('filament/admin_sv/about_page_settings_page.заголовок_hero'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('hero_description')
                            ->label(__('filament/admin_sv/about_page_settings_page.описание_hero'))
                            ->rows(3)
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('hero_image')
                            ->collection('hero_image')
                            ->label(__('filament/admin_sv/about_page_settings_page.изображение_hero'))
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament/admin_sv/about_page_settings_page.историякомпании'))
                    ->schema([
                        TextInput::make('story_title')
                            ->label(__('filament/admin_sv/about_page_settings_page.заголовокистории'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        RichEditor::make('story_content')
                            ->label(__('filament/admin_sv/about_page_settings_page.содержаниеистории'))
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'link',
                                'bulletList',
                                'orderedList',
                            ])
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('story_images')
                            ->collection('story_images')
                            ->label(__('filament/admin_sv/about_page_settings_page.изображенияистории'))
                            ->image()
                            ->imageEditor()
                            ->conversion('thumb')
                            ->maxSize(10240)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->multiple()
                            ->columnSpanFull(),
                    ]),
                Section::make(__('filament/admin_sv/about_page_settings_page.статистика'))
                    ->schema([
                        TextInput::make('statistics.years')
                            ->label(__('filament/admin_sv/about_page_settings_page.летнарынке'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('statistics.stores')
                            ->label(__('filament/admin_sv/about_page_settings_page.магазинов'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('statistics.clients')
                            ->label(__('filament/admin_sv/about_page_settings_page.довольныхклиентов'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('statistics.products')
                            ->label(__('filament/admin_sv/about_page_settings_page.товароввкаталоге'))
                            ->numeric()
                            ->default(0),
                    ])->columns(2),
                Section::make(__('filament/admin_sv/about_page_settings_page.настройки'))
                    ->schema([
                        TextInput::make('priority')
                            ->label(__('filament/admin_sv/about_page_settings_page.приоритет'))
                            ->numeric()
                            ->default(0)
                            ->helperText('Чем меньше число, тем выше в списке'),
                        Toggle::make('is_active')
                            ->label(__('filament/admin_sv/about_page_settings_page.активна'))
                            ->default(true),
                    ])->columns(2),
                Section::make(__('filament/admin_sv/about_page_settings_page.s_e_oнастройки'))
                    ->description(__('filament/admin_sv/about_page_settings_page.управлениеметаданными'))
                    ->schema([
                        SEO::make()
                    ])
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            DB::transaction(function () use ($data) {
                $aboutPage = AboutPage::getInstance();

                // Сохраняем медиа файлы отдельно
                $heroImage = $data['hero_image'] ?? null;
                $storyImages = $data['story_images'] ?? null;

                unset($data['hero_image'], $data['story_images']);

                $aboutPage->update($data);
                // SpatieMediaLibraryFileUpload автоматически обрабатывает конверсии через форму
                // Если конверсии не создаются, проверьте настройку QUEUE_CONVERSIONS_BY_DEFAULT в .env
                // Для локальной разработки установите QUEUE_CONVERSIONS_BY_DEFAULT=false
            });

            \Filament\Notifications\Notification::make()
                ->title(__('filament/admin_sv/about_page_settings_page.настройкисохранены'))
                ->success()
                ->send();

            $this->mount();
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title(__('filament/admin_sv/about_page_settings_page.ошибкасохранения'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament/admin_sv/about_page_settings_page.save'))
                ->submit('save'),
        ];
    }

    public function getCachedFormActions(): array
    {
        return $this->getFormActions();
    }

    public function getView(): string
    {
        return 'filament.pages.about-page-settings-page';
    }
    public function getTitle(): string
    {
        return __('filament/admin_sv/about_page_settings_page.title');
    }
    public static function getNavigationLabel(): string
    {
        return __('filament/admin_sv/about_page_settings_page.navigation_label');
    }


}
