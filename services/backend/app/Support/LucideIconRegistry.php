<?php

namespace App\Support;

final class LucideIconRegistry
{
    public const FALLBACK = 'circle-help';

    /**
     * Canonical Lucide identifiers stored in DB and returned by API.
     *
     * @var array<string, string>
     */
    private const ICONS = [
        'archive' => 'Archive',
        'armchair' => 'Armchair',
        'arrow-left-right' => 'Arrow left/right',
        'arrow-right' => 'Arrow right',
        'arrow-up-from-line' => 'Arrow up',
        'award' => 'Award',
        'baby' => 'Baby',
        'bed-double' => 'Bed',
        'bell' => 'Bell',
        'book-open' => 'Book',
        'briefcase' => 'Briefcase',
        'building-2' => 'Building',
        'bus' => 'Bus',
        'calculator' => 'Calculator',
        'calendar' => 'Calendar',
        'car' => 'Car',
        'chart-line' => 'Chart',
        'check' => 'Check',
        'chevron-down' => 'Chevron down',
        'chevron-left' => 'Chevron left',
        'chevron-right' => 'Chevron right',
        'circle-check' => 'Circle check',
        'circle-dollar-sign' => 'Money',
        'circle-help' => 'Help',
        'circle-x' => 'Circle close',
        'clipboard-list' => 'Checklist',
        'clock' => 'Clock',
        'cloud-upload' => 'Upload',
        'copy' => 'Copy',
        'credit-card' => 'Credit card',
        'door-open' => 'Door',
        'download' => 'Download',
        'eye' => 'Eye',
        'file-chart-column' => 'File chart',
        'file-plus' => 'File add',
        'file-text' => 'File text',
        'gift' => 'Gift',
        'graduation-cap' => 'Graduation',
        'hammer' => 'Hammer',
        'headset' => 'Headset',
        'heart' => 'Heart',
        'house' => 'Home',
        'image' => 'Image',
        'info' => 'Info',
        'layers' => 'Layers',
        'leaf' => 'Leaf',
        'lightbulb' => 'Lightbulb',
        'list-checks' => 'List checks',
        'list-filter' => 'Filter',
        'loader-circle' => 'Loader',
        'lock' => 'Lock',
        'log-out' => 'Logout',
        'mail' => 'Mail',
        'mail-check' => 'Mail check',
        'map-pin' => 'Map pin',
        'megaphone' => 'Megaphone',
        'menu' => 'Menu',
        'message-circle' => 'Message',
        'message-square' => 'Message square',
        'minus' => 'Minus',
        'package' => 'Package',
        'palette' => 'Palette',
        'paperclip' => 'Attachment',
        'pencil' => 'Edit',
        'phone' => 'Phone',
        'plug' => 'Plug',
        'plus' => 'Plus',
        'save' => 'Save',
        'search' => 'Search',
        'send' => 'Send',
        'settings' => 'Settings',
        'share-2' => 'Share',
        'shield-check' => 'Shield',
        'ship' => 'Ship',
        'shopping-bag' => 'Shopping bag',
        'shopping-cart' => 'Shopping cart',
        'smile' => 'Smile',
        'snowflake' => 'Snowflake',
        'sofa' => 'Sofa',
        'star' => 'Star',
        'store' => 'Store',
        'sun' => 'Sun',
        'table' => 'Table',
        'tag' => 'Tag',
        'thermometer-sun' => 'Temperature',
        'train-front' => 'Train',
        'trash-2' => 'Trash',
        'triangle-alert' => 'Warning',
        'trophy' => 'Trophy',
        'truck' => 'Truck',
        'tv' => 'TV',
        'undo-2' => 'Undo',
        'user' => 'User',
        'user-plus' => 'User add',
        'users' => 'Users',
        'utensils' => 'Restaurant',
        'wrench' => 'Tools',
        'x' => 'Close',
        'zap' => 'Flash',
        'zoom-in' => 'Zoom in',
    ];

    /**
     * Legacy Remixicon base name (without -line/-fill) => canonical Lucide identifier.
     *
     * @var array<string, string>
     */
    private const LEGACY = [
        'ri-add' => 'plus',
        'ri-advertisement' => 'megaphone',
        'ri-archive' => 'archive',
        'ri-armchair' => 'armchair',
        'ri-arrow-down-s' => 'chevron-down',
        'ri-arrow-go-back' => 'undo-2',
        'ri-arrow-left-s' => 'chevron-left',
        'ri-arrow-right' => 'arrow-right',
        'ri-arrow-right-s' => 'chevron-right',
        'ri-attachment-2' => 'paperclip',
        'ri-award' => 'award',
        'ri-bank-card' => 'credit-card',
        'ri-bear-smile' => 'baby',
        'ri-book' => 'book-open',
        'ri-box-3' => 'package',
        'ri-briefcase' => 'briefcase',
        'ri-building' => 'building-2',
        'ri-bus' => 'bus',
        'ri-calculator' => 'calculator',
        'ri-calendar' => 'calendar',
        'ri-car' => 'car',
        'ri-chat-3' => 'message-circle',
        'ri-check' => 'check',
        'ri-checkbox-circle' => 'circle-check',
        'ri-close' => 'x',
        'ri-close-circle' => 'circle-x',
        'ri-customer-service' => 'headset',
        'ri-customer-service-2' => 'headset',
        'ri-delete-bin' => 'trash-2',
        'ri-door' => 'door-open',
        'ri-download' => 'download',
        'ri-edit' => 'pencil',
        'ri-error-warning' => 'triangle-alert',
        'ri-exchange' => 'arrow-left-right',
        'ri-eye' => 'eye',
        'ri-file-add' => 'file-plus',
        'ri-file-chart' => 'file-chart-column',
        'ri-file-copy' => 'copy',
        'ri-file-list-3' => 'clipboard-list',
        'ri-file-text' => 'file-text',
        'ri-filter-3' => 'list-filter',
        'ri-flashlight' => 'zap',
        'ri-gift' => 'gift',
        'ri-graduation-cap' => 'graduation-cap',
        'ri-hammer' => 'hammer',
        'ri-heart' => 'heart',
        'ri-home' => 'house',
        'ri-hotel-bed' => 'bed-double',
        'ri-image' => 'image',
        'ri-information' => 'info',
        'ri-install' => 'plug',
        'ri-leaf' => 'leaf',
        'ri-lightbulb' => 'lightbulb',
        'ri-line-chart' => 'chart-line',
        'ri-list-check' => 'list-checks',
        'ri-loader-4' => 'loader-circle',
        'ri-lock-password' => 'lock',
        'ri-logout-box' => 'log-out',
        'ri-mail' => 'mail',
        'ri-mail-send' => 'mail-check',
        'ri-map-pin' => 'map-pin',
        'ri-map-pin-2' => 'map-pin',
        'ri-map-pin-3' => 'map-pin',
        'ri-menu' => 'menu',
        'ri-message-3' => 'message-square',
        'ri-money-dollar-circle' => 'circle-dollar-sign',
        'ri-notification' => 'bell',
        'ri-palette' => 'palette',
        'ri-phone' => 'phone',
        'ri-price-tag-3' => 'tag',
        'ri-question' => 'circle-help',
        'ri-restaurant' => 'utensils',
        'ri-save' => 'save',
        'ri-scales-3' => 'arrow-left-right',
        'ri-search' => 'search',
        'ri-send-plane' => 'send',
        'ri-settings' => 'settings',
        'ri-settings-3' => 'settings',
        'ri-share' => 'share-2',
        'ri-shield-check' => 'shield-check',
        'ri-ship' => 'ship',
        'ri-shopping-bag' => 'shopping-bag',
        'ri-shopping-cart' => 'shopping-cart',
        'ri-snowy' => 'snowflake',
        'ri-sofa' => 'sofa',
        'ri-stackshare' => 'layers',
        'ri-stairs' => 'arrow-up-from-line',
        'ri-star' => 'star',
        'ri-store' => 'store',
        'ri-subtract' => 'minus',
        'ri-subway' => 'train-front',
        'ri-sun' => 'sun',
        'ri-table' => 'table',
        'ri-team' => 'users',
        'ri-temp-hot' => 'thermometer-sun',
        'ri-time' => 'clock',
        'ri-tools' => 'wrench',
        'ri-trophy' => 'trophy',
        'ri-truck' => 'truck',
        'ri-tv' => 'tv',
        'ri-upload-cloud' => 'cloud-upload',
        'ri-user' => 'user',
        'ri-user-add' => 'user-plus',
        'ri-user-smile' => 'smile',
        'ri-zoom-in' => 'zoom-in',
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::ICONS;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::ICONS);
    }

    public static function isValid(?string $value): bool
    {
        return $value === null || $value === '' || array_key_exists($value, self::ICONS);
    }

    public static function normalizeLegacy(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (array_key_exists($value, self::ICONS)) {
            return $value;
        }

        $base = preg_replace('/-(line|fill)$/', '', $value) ?: $value;

        return self::LEGACY[$base] ?? self::FALLBACK;
    }
}
