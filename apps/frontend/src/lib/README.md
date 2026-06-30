# API Client Documentation

## Обзор

API клиент предоставляет единый интерфейс для работы с Laravel API (v1) с поддержкой кэширования и SSR.

## Базовый URL

API клиент использует базовый URL из переменной окружения `VITE_API_BASE_URL` или дефолтный `http://localhost:8000/api/v1`.

## Структура

- `api.ts` - Базовый HTTP клиент с методами для всех эндпоинтов
- `api-server.ts` - Серверный fetcher с поддержкой кэширования и ISR

## Использование

### Базовый API клиент

```typescript
import { api } from "@/lib/api";

// Получить список слайдеров
const sliders = await api.sliders.list();

// Получить конкретный слайдер
const slider = await api.sliders.get("home-hero");
```

### Серверный fetcher с кэшированием

```typescript
import { getSliders, getSlider } from "@/lib/api-server";

// Загрузить слайдеры с кэшированием (revalidate: 120 секунд)
const sliders = await getSliders({ revalidate: 120 });

// Загрузить конкретный слайдер
const slider = await getSlider("home-hero", { revalidate: 60 });
```

## Кэширование и ISR

### Политика кэширования

- **По умолчанию**: `revalidate = 120` секунд (2 минуты)
- **Механизм**: In-memory кэш с автоматической инвалидацией по времени
- **Fallback**: При ошибке запроса возвращается устаревший кэш (если доступен)

### Настройка revalidate

```typescript
// Кэш на 5 минут
const data = await getSliders({ revalidate: 300 });

// Кэш на 1 минуту
const data = await getSliders({ revalidate: 60 });
```

### Инвалидация кэша

```typescript
import { invalidateCache } from "@/lib/api-server";

// Очистить весь кэш
invalidateCache("all");
```

## SSR (Server-Side Rendering)

Для React + Vite приложения:

1. **Клиентская загрузка**: Данные загружаются при монтировании компонента
2. **Кэширование**: Используется in-memory кэш для уменьшения запросов
3. **Расширение**: Структура готова для интеграции с SSR фреймворками (Next.js, Remix и т.д.)

### Пример использования в компоненте

```typescript
import { useEffect, useState } from "react";
import { getSliders, Slider } from "@/lib/api-server";

export default function MyComponent() {
  const [slider, setSlider] = useState<Slider | null>(null);

  useEffect(() => {
    getSliders({ revalidate: 120 })
      .then((sliders) => {
        setSlider(sliders[0] || null);
      })
      .catch(console.error);
  }, []);

  // ...
}
```

### Передача данных через пропсы (для SSR)

```typescript
// В родительском компоненте
const slider = await getSliders({ revalidate: 120 });

// Передача в дочерний компонент
<Hero initialSlider={slider[0]} />;
```

## Авторизация

API клиент поддерживает Sanctum cookie-based авторизацию через `credentials: 'include'` в fetch запросах.

Для публичных эндпоинтов авторизация не требуется.

## Обработка ошибок

Все ошибки API выбрасываются как `Error` с сообщением формата:

```
API Error: {status} {errorText}
```

Рекомендуется обрабатывать ошибки в компонентах:

```typescript
try {
  const data = await api.sliders.list();
} catch (error) {
  console.error("Failed to load sliders:", error);
  // Показать fallback UI
}
```

## Типы

Все типы экспортируются из `api.ts`:

```typescript
import { Slider } from "@/lib/api";

const slider: Slider = {
  id: 1,
  title: "Заголовок",
  description: "Описание",
  // ...
};
```
