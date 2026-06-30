# Настройка переменных окружения

## VITE_API_BASE_URL

Переменная `VITE_API_BASE_URL` определяет базовый URL для Laravel API.

**Дефолтное значение:** `http://localhost:8000/api/v1`

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

### Альтернативные способы для продакшена (SSR):

**Вариант A: Через ecosystem.config.cjs**

Отредактируйте `apps/frontend/ecosystem.config.cjs`:

```javascript
env: {
  NODE_ENV: 'production',
  PORT: 3000,
  BASE_PATH: '/',
  VITE_API_BASE_URL: 'https://api.yourdomain.com/api/v1', // Ваш URL
},
```

**Вариант B: Через переменную окружения при запуске**

```bash
VITE_API_BASE_URL=https://api.yourdomain.com/api/v1 pm2 start ecosystem.config.cjs --only react-ssr-prod
```

**Вариант C: Через системную переменную окружения**

```bash
export VITE_API_BASE_URL=https://api.yourdomain.com/api/v1
pm2 start ecosystem.config.cjs --only react-ssr-prod
```

### Важно:

1. **Для клиентской сборки:** Переменная заменяется Vite во время сборки (`npm run build`). Убедитесь, что она задана до сборки.

2. **Для SSR:** Переменная читается из `process.env.VITE_API_BASE_URL` во время выполнения сервера.

3. **После изменения:** Если вы изменили переменную в `ecosystem.config.cjs`, перезапустите PM2:
   ```bash
   pm2 restart react-ssr-prod
   ```

4. **Если изменили переменную для клиентской сборки:** Пересоберите проект:
   ```bash
   npm run build:ssr
   ```

### Примеры:

**Локальная разработка:**
```bash
# apps/frontend/.env
VITE_API_BASE_URL=http://localhost:8000/api/v1
```

**Продакшен (рекомендуемый способ):**
```bash
# apps/frontend/.env
VITE_API_BASE_URL=https://api.yourdomain.com/api/v1
```

После создания/изменения `.env` файла:
```bash
npm run build:ssr
pm2 restart react-ssr-prod
```

**Альтернатива - через ecosystem.config.cjs:**
```javascript
// apps/frontend/ecosystem.config.cjs
env: {
  VITE_API_BASE_URL: 'https://api.yourdomain.com/api/v1',
}
```

**Альтернатива - через переменную окружения:**
```bash
VITE_API_BASE_URL=https://api.yourdomain.com/api/v1 pm2 start ecosystem.config.cjs --only react-ssr-prod
```
