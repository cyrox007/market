<?php

/**
 * Скрипт для проверки и исправления конфигурации l5-swagger
 * Запустите: php fix-swagger-config.php
 */

$configPath = __DIR__ . '/config/l5-swagger.php';

if (!file_exists($configPath)) {
    echo "Ошибка: файл конфигурации не найден: $configPath\n";
    exit(1);
}

$config = require $configPath;

if (!isset($config['proxy'])) {
    echo "Ошибка: ключ 'proxy' отсутствует в конфигурации!\n";
    exit(1);
}

echo "✓ Ключ 'proxy' найден в конфигурации\n";
echo "  Значение: " . var_export($config['proxy'], true) . "\n";
echo "\n";
echo "Теперь выполните:\n";
echo "  php artisan config:clear\n";
echo "  php artisan cache:clear\n";
