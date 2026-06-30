# Запуск React приложения через PM2

## Установка PM2 (если еще не установлен)

```bash
npm install -g pm2
```

## Запуск в режиме разработки (development)

```bash
# Запустить приложение
pm2 start ecosystem.config.js --only react-frontend-dev

# Или просто
pm2 start ecosystem.config.js
```

## Запуск в режиме production

Сначала соберите приложение:

```bash
cd apps/frontend
npm run build
cd ../..
```

Затем запустите:

```bash
pm2 start ecosystem.config.js --only react-frontend-prod
```

## Полезные команды PM2

```bash
# Посмотреть список запущенных процессов
pm2 list

# Посмотреть логи
pm2 logs react-frontend-dev
pm2 logs react-frontend-prod

# Остановить приложение
pm2 stop react-frontend-dev
pm2 stop react-frontend-prod

# Перезапустить приложение
pm2 restart react-frontend-dev

# Удалить приложение из PM2
pm2 delete react-frontend-dev

# Остановить все процессы
pm2 stop all

# Сохранить текущую конфигурацию для автозапуска
pm2 save

# Настроить автозапуск при перезагрузке системы
pm2 startup
```

## Проверка работы

После запуска приложение будет доступно по адресу:
- Development: http://localhost:3000
- Production: http://localhost:3000 (после сборки)

Логи сохраняются в папке `./logs/`




