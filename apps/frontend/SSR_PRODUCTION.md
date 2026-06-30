# Запуск SSR на продакшене

## Шаги для запуска SSR на продакшене:

### 1. Убедитесь, что сборка прошла успешно:
```bash
npm run build:ssr
```

### 2. Создайте директорию для логов (если её нет):
```bash
mkdir -p logs
```

### 3. Запустите SSR сервер через PM2:
```bash
pm2 start ecosystem.config.cjs --only react-ssr-prod
```

### 4. Проверьте статус:
```bash
pm2 status
```

### 5. Просмотр логов:
```bash
pm2 logs react-ssr-prod
```

### 6. Остановка:
```bash
pm2 stop react-ssr-prod
```

### 7. Перезапуск после изменений:
```bash
npm run build:ssr
pm2 restart react-ssr-prod
```

### 8. Удаление из PM2:
```bash
pm2 delete react-ssr-prod
```

## Настройка порта

По умолчанию сервер запускается на порту 3000. Чтобы изменить порт, отредактируйте `ecosystem.config.cjs` или установите переменную окружения:

```bash
PORT=8080 pm2 start ecosystem.config.cjs --only react-ssr-prod
```

## Настройка BASE_PATH

Если приложение работает не в корне домена, установите BASE_PATH:

```bash
BASE_PATH=/subfolder pm2 start ecosystem.config.cjs --only react-ssr-prod
```

Или отредактируйте `ecosystem.config.cjs` и измените значение `BASE_PATH` в секции `env`.

## Настройка VITE_API_BASE_URL

Переменная окружения `VITE_API_BASE_URL` определяет URL для Laravel API backend.

### Самый простой способ - через .env файл:

Создайте файл `.env` в директории `apps/frontend/`:

```bash
# Для продакшена
VITE_API_BASE_URL=https://api.yourdomain.com/api/v1

# Или для локальной разработки
# VITE_API_BASE_URL=http://localhost:8000/api/v1
```

**Этот файл будет автоматически загружен:**
- ✅ Vite загрузит его при сборке (для клиентской части)
- ✅ SSR сервер загрузит его при запуске (через dotenv)

### Альтернативные способы:

#### 1. Через переменную окружения при запуске PM2:
```bash
VITE_API_BASE_URL=https://api.example.com/api/v1 pm2 start ecosystem.config.cjs --only react-ssr-prod
```

#### 2. Через редактирование ecosystem.config.cjs:
Отредактируйте файл `ecosystem.config.cjs` и измените значение в секции `env`:
```javascript
env: {
  // ...
  VITE_API_BASE_URL: 'https://api.example.com/api/v1',
}
```

#### 3. Через системные переменные окружения:
```bash
export VITE_API_BASE_URL=https://api.example.com/api/v1
pm2 start ecosystem.config.cjs --only react-ssr-prod
```

### Дефолтное значение:
Если переменная не задана, используется `http://localhost:8000/api/v1`

### Важно:
- После изменения `.env` файла **пересоберите проект**: `npm run build:ssr`
- После изменения в `ecosystem.config.cjs` **перезапустите PM2**: `pm2 restart react-ssr-prod`
- Не коммитьте `.env` файл в git (он должен быть в `.gitignore`)

## Важно

- Убедитесь, что `tsx` установлен (он должен быть в `node_modules`)
- Убедитесь, что все зависимости установлены: `npm install`
- После изменений в коде обязательно пересоберите: `npm run build:ssr`
