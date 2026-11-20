<?php

namespace App\Support;

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Human-readable Filament presentation of the existing Spatie permission keys.
 * Does not create, rename or grant any permissions.
 */
final class AdminAccessCatalog
{
    private const DOMAINS = [
        'Каталог и товары' => [
            'products' => 'Товары и торговые предложения',
            'categories' => 'Категории',
            'attributes' => 'Характеристики и цвета',
            'product_collections' => 'Коллекции товаров',
            'product_feature_blocks' => 'Блоки преимуществ товаров',
            'product_delivery_blocks' => 'Блоки доставки товаров',
            'catalog_sync' => 'Синхронизация каталога',
        ],
        'Заказы и продажи' => [
            'orders' => 'Заказы',
            'payment_methods' => 'Способы оплаты',
            'reviews' => 'Отзывы покупателей',
            'one_click_leads' => 'Заявки в один клик',
        ],
        'Магазины и доставка' => [
            'stores' => 'Магазины',
            'shipping_locations' => 'Регионы доставки',
            'carriers' => 'Перевозчики',
            'delivery_handling_types' => 'Услуги по доставке',
        ],
        'Контент и коммуникации' => [
            'articles' => 'Статьи',
            'sliders' => 'Слайдеры',
            'interior_ideas' => 'Идеи интерьеров',
            'newsletter_subscribers' => 'Подписчики рассылки',
            'mail_events' => 'Почтовые события',
        ],
        'Пользователи и доступ' => [
            'users' => 'Пользователи',
            'roles' => 'Роли',
            'permissions' => 'Разрешения',
        ],
        'Системные операции' => [
            'service_tools' => 'Сервисные инструменты',
            'dangerous' => 'Опасные операции',
        ],
    ];

    private const ACTIONS = [
        'viewAny' => ['Просматривать список', 'Доступ к списку записей раздела.'],
        'view' => ['Просматривать карточку', 'Открытие отдельной записи.'],
        'create' => ['Создавать', 'Добавление новых записей.'],
        'update' => ['Редактировать', 'Изменение существующих записей.'],
        'delete' => ['Удалять', 'Удаление записей раздела.'],
        'deleteAny' => ['Массово удалять', 'Удаление сразу нескольких записей.'],
        'restore' => ['Восстанавливать', 'Восстановление ранее удалённых записей.'],
        'restoreAny' => ['Массово восстанавливать', 'Восстановление нескольких записей.'],
        'forceDelete' => ['Удалять безвозвратно', 'Безвозвратное удаление записей.'],
        'forceDeleteAny' => ['Массово удалять безвозвратно', 'Безвозвратное удаление нескольких записей.'],
        'replicate' => ['Копировать', 'Создание записи на основе существующей.'],
        'reorder' => ['Изменять порядок', 'Изменение порядка записей.'],
    ];

    private const ROLE_TITLES = [
        'super_admin' => 'Суперадминистратор',
        'admin' => 'Администратор',
        'manager' => 'Менеджер',
        'editor' => 'Редактор контента',
        'viewer' => 'Наблюдатель',
    ];

    private const ROLE_DESCRIPTIONS = [
        'super_admin' => 'Обычно полный административный доступ.',
        'admin' => 'Обычно управление магазином без настройки ролей.',
        'manager' => 'Обычно работа с каталогом, товарами и заказами.',
        'editor' => 'Обычно работа с контентом и материалами сайта.',
        'viewer' => 'Обычно просмотр без изменения данных.',
    ];

    public static function roleTitle(string $name): string
    {
        return self::ROLE_TITLES[$name] ?? Str::headline($name);
    }

    public static function roleOption(string $name): string
    {
        return self::roleTitle($name).' ('.$name.')';
    }

    public static function roleDescription(string $name): string
    {
        return (self::ROLE_DESCRIPTIONS[$name] ?? 'Пользовательская роль.')
            .' Фактический доступ определяется выданными разрешениями.';
    }

    /** @return array<int, string> */
    public static function roleOptions(): array
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->get()
            ->mapWithKeys(fn (Role $role): array => [$role->id => self::roleOption($role->name)])
            ->all();
    }

    /** @return array<int, string> */
    public static function roleDescriptions(): array
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->get()
            ->mapWithKeys(fn (Role $role): array => [$role->id => self::roleDescription($role->name)])
            ->all();
    }

    /**
     * @return array{domain: string, resource_key: string, resource_title: string, action: string, description: string}
     */
    public static function explain(string $permissionName): array
    {
        if ($permissionName === 'delete_all_catalog') {
            return [
                'domain' => 'Системные операции',
                'resource_key' => 'dangerous',
                'resource_title' => 'Опасные операции',
                'action' => 'Полностью очистить каталог',
                'description' => 'Критическое разрешение: удаление всех товаров и категорий.',
            ];
        }

        $parts = explode(' ', $permissionName, 2);
        $actionKey = $parts[0];
        $resourceKey = $parts[1] ?? 'other';
        $domain = 'Другие разрешения';
        $resourceTitle = Str::headline($resourceKey);

        foreach (self::DOMAINS as $name => $resources) {
            if (array_key_exists($resourceKey, $resources)) {
                $domain = $name;
                $resourceTitle = $resources[$resourceKey];
                break;
            }
        }

        [$action, $description] = self::ACTIONS[$actionKey]
            ?? [Str::headline($actionKey), 'Специальное разрешение. Проверьте его назначение перед выдачей.'];

        if (! isset($parts[1])) {
            $resourceKey = 'other';
            $resourceTitle = 'Прочие системные права';
            $domain = 'Другие разрешения';
            $action = $permissionName;
        }

        return compact('domain', 'resourceKey', 'resourceTitle', 'action', 'description') + [
            'resource_key' => $resourceKey,
            'resource_title' => $resourceTitle,
        ];
    }

    public static function actionLabel(string $permissionName): string
    {
        return self::explain($permissionName)['action'];
    }

    public static function resourceLabel(string $permissionName): string
    {
        return self::explain($permissionName)['resource_title'];
    }

    public static function domainLabel(string $permissionName): string
    {
        return self::explain($permissionName)['domain'];
    }

    /** @return array<int, Section> */
    public static function permissionSections(): array
    {
        $groups = [];
        foreach (Permission::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']) as $permission) {
            $meta = self::explain($permission->name);
            $domain = $meta['domain'];
            $resource = $meta['resource_key'];
            $groups[$domain][$resource]['title'] = $meta['resource_title'];
            $groups[$domain][$resource]['options'][(string) $permission->id] = $meta['action'];
            $groups[$domain][$resource]['descriptions'][(string) $permission->id] =
                $meta['description'].' Технический ключ: '.$permission->name;
        }

        $sections = [];
        foreach ($groups as $domain => $resources) {
            $fields = [];
            foreach ($resources as $resource => $group) {
                $fields[] = Section::make($group['title'])
                    ->compact()
                    ->schema([
                        CheckboxList::make('permission_groups.'.$resource)
                            ->label('Действия')
                            ->options($group['options'])
                            ->descriptions($group['descriptions'])
                            ->columns(2)
                            ->bulkToggleable(),
                    ]);
            }

            $sections[] = Section::make($domain)
                ->description('Выдавайте только те действия, которые необходимы сотрудникам.')
                ->collapsible()
                ->collapsed()
                ->columns(2)
                ->schema($fields)
                ->columnSpanFull();
        }

        return $sections;
    }

    /** @return array<string, array<int, string>> */
    public static function selectedGroups(Role $role): array
    {
        $groups = [];
        foreach ($role->permissions as $permission) {
            $meta = self::explain($permission->name);
            $groups[$meta['resource_key']][] = (string) $permission->id;
        }

        return $groups;
    }

    /** @return array<int, int> */
    public static function selectedIds(array $groups): array
    {
        $ids = [];
        foreach ($groups as $values) {
            if (! is_array($values)) {
                continue;
            }
            foreach ($values as $id) {
                if (is_numeric($id) && ctype_digit((string) $id) && (int) $id > 0) {
                    $ids[(int) $id] = (int) $id;
                }
            }
        }

        return array_values($ids);
    }
}
