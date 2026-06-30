# SSR Миграция для React + Vite

## Вариант 1: Vite SSR (Рекомендуется)

### Шаги миграции:

1. **Создать server entry point**
   - `src/entry-server.tsx` - точка входа для SSR

2. **Обновить client entry point**
   - `src/entry-client.tsx` - гидратация вместо рендера

3. **Создать SSR server**
   - `server.js` - Express/Koa сервер для SSR

4. **Обновить Vite config**
   - Настроить SSR режим

5. **Адаптировать код**
   - Убрать `window` в render
   - Перенести данные в SSR контекст
   - Обернуть браузерные API

## Вариант 2: Remix (Альтернатива)

Полная миграция на Remix фреймворк - больше изменений, но лучше поддержка.

## Вариант 3: Pre-rendering (Быстрое решение)

Использовать pre-rendering сервисы (Prerender.io, Puppeteer) без изменения кода.