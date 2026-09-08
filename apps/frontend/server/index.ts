import { config as loadEnv } from 'dotenv';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import express, { type Request, type Response, type NextFunction } from 'express';
import cookieParser from 'cookie-parser';
import { createServer as createViteServer } from 'vite';
import type { SSRContext } from '../src/types/ssr';
import { withCache, withCacheSWR, createCacheKey, ssrCache } from './ssr-cache';

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

const __dirname = path.dirname(fileURLToPath(import.meta.url));
loadEnv({ path: path.resolve(__dirname, '../.env') });
const isProduction = process.env.NODE_ENV === 'production';
const port = process.env.PORT || 3000;
const base = process.env.BASE_PATH || '/';

async function createServer() {
  const app = express();

  app.use(express.json());

  // Сброс SSR-кэша блоков главной (вызывается с Laravel после правки подборок)
  app.post('/internal/cache/invalidate', (req: Request, res: Response) => {
    const secret = process.env.FRONTEND_SSR_CACHE_INVALIDATE_SECRET || '';
    if (secret !== '' && req.header('x-cache-secret') !== secret) {
      res.status(403).json({ message: 'Forbidden' });
      return;
    }
    const prefixes = Array.isArray(req.body?.prefixes) ? (req.body.prefixes as string[]) : [];
    const removed = prefixes.length > 0 ? ssrCache.deleteByPrefixes(prefixes) : 0;
    res.json({ ok: true, removed });
  });

  let vite: any = null;

  if (!isProduction) {
    // В режиме разработки используем Vite dev server
    vite = await createViteServer({
      root: path.resolve(__dirname, '..'),
      configFile: path.resolve(__dirname, '../vite.config.ts'),
      server: { middlewareMode: true },
      appType: 'custom',
    });
    app.use(vite.middlewares);
  } else {
    // В production отдаем статические файлы с браузерным кэшем
    const clientDistPath = path.resolve(__dirname, '../dist/client');
    // В assets/ лежат только хешированные файлы Vite — долгий кэш; index.html — без кэша
    app.use(
      express.static(clientDistPath, {
        index: false,
        setHeaders(res, filePath) {
          const normalized = filePath.replace(/\\/g, '/');
          if (normalized.includes('/assets/')) {
            res.setHeader('Cache-Control', 'public, max-age=31536000, immutable');
          } else if (normalized.endsWith('.html') || normalized.endsWith('.html/')) {
            res.setHeader('Cache-Control', 'private, no-cache, no-store, must-revalidate');
          } else {
            // Остальная статика (favicon, manifest и т.д.) — 1 час
            res.setHeader('Cache-Control', 'public, max-age=3600');
          }
        },
      }),
    );
  }

  // Парсинг cookies
  app.use(cookieParser());

  // Обработка всех маршрутов
  app.use('*', async (req: Request, res: Response, next: NextFunction) => {
    const url = req.originalUrl.replace(base, '/') || '/';

    const homeCollectionFilters = new Set(['new', 'featured', 'sale']);
    try {
      const parsed = new URL(url, 'http://ssr.local');
      if (
        (parsed.pathname === '/catalog' || parsed.pathname === '/catalog/') &&
        parsed.searchParams.has('filter')
      ) {
        const filter = parsed.searchParams.get('filter');
        if (filter && homeCollectionFilters.has(filter)) {
          const target = `${base.replace(/\/$/, '')}/collections/${filter}`;
          res.redirect(302, target);
          return;
        }
      }
    } catch {
      // ignore malformed URL
    }

    try {
      // Создаем SSR контекст с данными пользователя и счетчиков
      const ssrContext: SSRContext = {};

      try {
        // Загружаем API клиент для SSR
        let ssrApi: ReturnType<typeof import('../src/lib/api-ssr').createSSRApi>;
        if (!isProduction && vite) {
          try {
            const apiModule = await vite.ssrLoadModule('/src/lib/api-ssr.ts');
            ssrApi = apiModule.createSSRApi(req);
          } catch (apiError: any) {
            console.error('Error loading api-ssr:', apiError);
            throw apiError;
          }
        } else {
          // В production используем собранный модуль
          // Путь относительно текущего файла (server/index.ts)
          // api-ssr.ts собирается в dist/server/lib/api-ssr.js
          const apiModule = await import('../dist/server/lib/api-ssr.js');
          ssrApi = apiModule.createSSRApi(req);
        }

        // Загружаем данные пользователя и счетчиков на сервере
        // Используем кеш для ускорения повторных запросов

        const hasAuthSession = Boolean(
          req.cookies?.['svetofor-session'] || req.cookies?.['laravel_session'],
        );
        const userId = hasAuthSession ? 'authenticated' : 'anonymous';

        // Параллельно: auth (только с сессией), счётчики, регион — без лишней последовательной задержки
        const parallelTasks: Promise<unknown>[] = [
          withCacheSWR(
            createCacheKey('/regions/detect', { ip: req.ip }),
            () => ssrApi.regions.detect(),
            { ttl: 300 },
          ),
          withCache(
            createCacheKey('/cart/count', undefined, userId === 'authenticated' ? 1 : undefined),
            () => ssrApi.cart.count(),
            { ttl: 30 },
          ),
          withCache(
            createCacheKey(
              '/wishlist/count',
              undefined,
              userId === 'authenticated' ? 1 : undefined,
            ),
            () => ssrApi.wishlist.count(),
            { ttl: 30 },
          ),
          withCache(
            createCacheKey('/compare/count', undefined, userId === 'authenticated' ? 1 : undefined),
            () => ssrApi.compare.count(),
            { ttl: 30 },
          ),
          // Дерево категорий нужно шапке на каждом маршруте. Кэш общий для всех
          // посетителей, поэтому бэкенд получает один запрос в 5 минут.
          withCacheSWR(
            createCacheKey('/categories/tree', undefined, undefined),
            () => ssrApi.categories.tree(),
            { ttl: 300 },
          ),
        ];

        if (hasAuthSession) {
          parallelTasks.unshift(
            withCache(createCacheKey('/auth/me', undefined, 1), () => ssrApi.auth.me(), {
              ttl: 60,
            }),
          );
        }

        const parallelResults = await Promise.allSettled(parallelTasks);

        let resultOffset = 0;
        if (hasAuthSession) {
          const userResponse = parallelResults[resultOffset++];
          if (
            userResponse.status === 'fulfilled' &&
            (userResponse.value as { user?: unknown })?.user
          ) {
            ssrContext.user = (userResponse.value as { user: SSRContext['user'] }).user;
          }
        }

        const regionResponse = parallelResults[resultOffset++];
        const cartCountResponse = parallelResults[resultOffset++];
        const wishlistCountResponse = parallelResults[resultOffset++];
        const compareCountResponse = parallelResults[resultOffset++];
        const categoryTreeResponse = parallelResults[resultOffset++];

        if (categoryTreeResponse.status === 'fulfilled') {
          const tree = (categoryTreeResponse.value as { tree?: unknown[] })?.tree;
          if (tree?.length) ssrContext.categoryTree = tree;
        }

        if (
          regionResponse.status === 'fulfilled' &&
          (regionResponse.value as { region?: unknown })?.region
        ) {
          ssrContext.region = (regionResponse.value as { region: SSRContext['region'] }).region;
        }

        const counters = {
          cartCount:
            cartCountResponse.status === 'fulfilled'
              ? (cartCountResponse.value as { count?: number }).count || 0
              : 0,
          wishlistCount:
            wishlistCountResponse.status === 'fulfilled'
              ? (wishlistCountResponse.value as { count?: number }).count || 0
              : 0,
          compareCount:
            compareCountResponse.status === 'fulfilled'
              ? (compareCountResponse.value as { count?: number }).count || 0
              : 0,
        };

        if (counters.cartCount > 0 || counters.wishlistCount > 0 || counters.compareCount > 0) {
          ssrContext.counters = counters;
        }

        const regionId = ssrContext.region?.id;

        // Загружаем данные в зависимости от URL
        // Главная страница
        if (url === '/' || url === '') {
          try {
            // Главная: stale-while-revalidate — следующий пользователь сразу получает кэш, в фоне кэш обновляется
            // Ключи без region_id: бэкенд отдаёт один и тот же список товаров (регион только для корзины), кэш общий для всех
            const [
              categoriesResponse,
              featuredResponse,
              newResponse,
              saleResponse,
              slidersResponse,
              interiorIdeasResponse,
            ] = await Promise.allSettled([
              withCacheSWR(
                createCacheKey('/categories', undefined, undefined),
                () => ssrApi.categories.list(),
                { ttl: 300 }, // 5 минут для категорий
              ),
              withCacheSWR(
                createCacheKey('/products/featured', undefined, undefined),
                () => ssrApi.products.featured({ region_id: regionId }),
                { ttl: 120 }, // 2 минуты для товаров
              ),
              withCacheSWR(
                createCacheKey('/products/new', undefined, undefined),
                () => ssrApi.products.new({ region_id: regionId }),
                { ttl: 120 },
              ),
              withCacheSWR(
                createCacheKey('/products/sale', undefined, undefined),
                () => ssrApi.products.sale({ region_id: regionId }),
                { ttl: 120 },
              ),
              withCacheSWR(
                createCacheKey('/sliders', undefined, undefined),
                () => ssrApi.sliders.list(),
                { ttl: 120 },
              ),
              withCacheSWR(
                createCacheKey('/interior-ideas', { page: 1, per_page: 6 }),
                () => ssrApi.interiorIdeas.list({ page: 1, per_page: 6 }),
                { ttl: 300 }, // 5 минут для идей интерьера
              ),
            ]);

            ssrContext.home = {};
            if (categoriesResponse.status === 'fulfilled') {
              ssrContext.home.categories = categoriesResponse.value.data;
            }
            if (featuredResponse.status === 'fulfilled') {
              ssrContext.home.featuredProducts = featuredResponse.value.data;
            }
            if (newResponse.status === 'fulfilled') {
              ssrContext.home.newProducts = newResponse.value.data;
            }
            if (saleResponse.status === 'fulfilled') {
              ssrContext.home.saleProducts = saleResponse.value.data;
            }
            if (slidersResponse.status === 'fulfilled') {
              ssrContext.home.sliders = slidersResponse.value.data;
            }
            if (interiorIdeasResponse.status === 'fulfilled') {
              ssrContext.home.interiorIdeas = interiorIdeasResponse.value.data;
            }
          } catch (error) {
            console.error('SSR home data loading error:', error);
          }
        }

        // Страница товара /product/:slug — один запрос (товар + набор + сопутствующие)
        const productMatch = url.match(/^\/product\/([^/]+)/);
        if (productMatch) {
          const productSlug = decodeURIComponent(productMatch[1]);
          try {
            ssrContext.product = await withCacheSWR(
              createCacheKey(`/products/${productSlug}`, { region_id: regionId }),
              () => ssrApi.products.get(productSlug, { region_id: regionId }),
              { ttl: 300 },
            );
          } catch (productError: any) {
            if (productError.status !== 404) {
              console.error('SSR product loading error:', productError);
            }
          }
        }

        // Страница каталога /catalog — только список категорий (кешируем сегментно, меняются в основном числа)
        const catalogIndexMatch = url.match(/^\/catalog\/?$/);
        if (catalogIndexMatch) {
          try {
            const categoriesResponse = await withCache(
              createCacheKey('/categories', undefined, undefined),
              () => ssrApi.categories.list(),
              { ttl: 300 }, // 5 минут — структура категорий стабильна, обновляются в основном products_count
            );
            if (categoriesResponse?.data) {
              ssrContext.home = ssrContext.home || {};
              ssrContext.home.categories = categoriesResponse.data;
            }
          } catch (error) {
            console.error('SSR catalog index loading error:', error);
          }
        }

        // Страница категории /catalog/:category
        const categoryMatch = url.match(/^\/catalog\/([^/]+)/);
        if (categoryMatch) {
          const categorySlug = categoryMatch[1];
          try {
            // Парсим query параметры из req.query
            const query = req.query as Record<string, string | string[] | undefined>;
            const page = parseInt(
              Array.isArray(query.page) ? query.page[0] : query.page || '1',
              10,
            );
            const priceMin = query.price_min
              ? parseInt(Array.isArray(query.price_min) ? query.price_min[0] : query.price_min, 10)
              : undefined;
            const priceMax = query.price_max
              ? parseInt(Array.isArray(query.price_max) ? query.price_max[0] : query.price_max, 10)
              : undefined;
            const sortBy = (Array.isArray(query.sort) ? query.sort[0] : query.sort) || 'created_at';
            const sortOrder = ((Array.isArray(query.order) ? query.order[0] : query.order) ||
              'desc') as 'asc' | 'desc';
            const colors = query.colors
              ? (Array.isArray(query.colors) ? query.colors : [query.colors]).flatMap((c) =>
                  c.split(',').filter(Boolean),
                )
              : [];
            const sizes = query.sizes
              ? (Array.isArray(query.sizes) ? query.sizes : [query.sizes]).flatMap((s) =>
                  s.split(',').filter(Boolean),
                )
              : [];

            const attributes: Record<string, string[]> = {};
            Object.keys(query).forEach((key) => {
              if (key.startsWith('attr_')) {
                const slug = key.replace('attr_', '');
                const value = query[key];
                attributes[slug] = Array.isArray(value)
                  ? value.flatMap((v) => v.split(',').filter(Boolean))
                  : value
                    ? value.split(',').filter(Boolean)
                    : [];
              }
            });

            const categoryParams = {
              category_slug: categorySlug,
              price_min: priceMin,
              price_max: priceMax,
              sort_by: sortBy,
              sort_order: sortOrder,
              page,
              per_page: 20,
              colors,
              sizes,
              attributes,
              region_id: regionId,
            };

            const [categoryResponse, productsResponse] = await Promise.allSettled([
              withCache(
                createCacheKey(`/categories/${categorySlug}`, undefined, undefined),
                () => ssrApi.categories.get(categorySlug),
                { ttl: 300 }, // 5 минут для категории
              ),
              withCache(
                createCacheKey('/products', categoryParams, undefined),
                () =>
                  ssrApi.products.list({
                    category_slug: categorySlug,
                    price_min: priceMin,
                    price_max: priceMax,
                    sort_by: sortBy,
                    sort_order: sortOrder,
                    page,
                    per_page: 20,
                    colors,
                    sizes,
                    attributes,
                    region_id: regionId,
                  }),
                { ttl: 120 }, // 2 минуты для списка товаров
              ),
            ]);

            ssrContext.category = {};
            if (categoryResponse.status === 'fulfilled') {
              ssrContext.category.category = categoryResponse.value.category;
            }
            if (productsResponse.status === 'fulfilled') {
              ssrContext.category.products = productsResponse.value;
            }
          } catch (error) {
            console.error('SSR category data loading error:', error);
          }
        }

        // Страница поиска /search?q=...
        const searchMatch = url.match(/^\/search/);
        if (searchMatch) {
          try {
            const queryParams = req.query as Record<string, string | string[] | undefined>;
            const query = Array.isArray(queryParams.q) ? queryParams.q[0] : queryParams.q;
            if (query) {
              const page = parseInt(
                Array.isArray(queryParams.page) ? queryParams.page[0] : queryParams.page || '1',
                10,
              );
              const sortBy =
                (Array.isArray(queryParams.sort) ? queryParams.sort[0] : queryParams.sort) ||
                undefined;
              const sortOrder = ((Array.isArray(queryParams.order)
                ? queryParams.order[0]
                : queryParams.order) || undefined) as 'asc' | 'desc' | undefined;
              const searchParams = {
                page,
                per_page: 24,
                sort_by: sortBy,
                sort_order: sortOrder,
                region_id: regionId,
              };
              const productsResponse = await withCache(
                createCacheKey('/products/search', { q: query, ...searchParams }),
                () => ssrApi.products.search(query, searchParams),
                { ttl: 60 }, // 1 минута для поиска (часто меняется)
              );
              ssrContext.search = {
                query,
                products: productsResponse,
              };
            }
          } catch (error) {
            console.error('SSR search data loading error:', error);
          }
        }

        // Страница наборов /sets
        if (url.startsWith('/sets') && !url.match(/^\/set\//)) {
          try {
            const queryParams = req.query as Record<string, string | string[] | undefined>;
            const page = parseInt(
              Array.isArray(queryParams.page) ? queryParams.page[0] : queryParams.page || '1',
              10,
            );
            const setsResponse = await withCache(
              createCacheKey('/sets', { page, per_page: 24 }),
              () => ssrApi.sets.list({ page, per_page: 24 }),
              { ttl: 180 }, // 3 минуты для наборов
            );
            ssrContext.sets = setsResponse;
          } catch (error) {
            console.error('SSR sets data loading error:', error);
          }
        }

        // Страница набора /set/:id
        const setMatch = url.match(/^\/set\/(\d+)/);
        if (setMatch) {
          const setId = parseInt(setMatch[1], 10);
          try {
            const setResponse = await withCache(
              createCacheKey(`/sets/${setId}`, undefined, undefined),
              () => ssrApi.sets.get(setId),
              { ttl: 180 }, // 3 минуты для набора
            );
            ssrContext.set = setResponse;
          } catch (error) {
            console.error('SSR set data loading error:', error);
          }
        }
      } catch (error) {
        // Игнорируем ошибки загрузки данных - приложение все равно должно работать
        console.error('SSR data loading error:', error);
      }

      // SEO текущей страницы для подстановки в <head>
      const pageSeo =
        ssrContext.product?.product?.seo ?? ssrContext.category?.category?.seo ?? null;
      if (pageSeo && pageSeo.title) {
        ssrContext.seo = pageSeo;
      }

      // Рендерим React приложение
      let html: string;
      if (!isProduction && vite) {
        // В dev режиме используем Vite для загрузки модулей
        try {
          const entryServer = await vite.ssrLoadModule('/src/entry-server.tsx');
          html = await entryServer.render(url, ssrContext);
        } catch (loadError: any) {
          console.error('Error loading entry-server:', loadError);
          throw loadError;
        }
      } else {
        // В production используем собранный модуль
        // Путь относительно текущего файла (server/index.ts)
        const { render: renderApp } = await import('../dist/server/entry-server.js');
        html = await renderApp(url, ssrContext);
      }

      // Читаем index.html
      let template: string;
      if (!isProduction && vite) {
        template = await vite.transformIndexHtml(
          url,
          fs.readFileSync(path.resolve(__dirname, '../index.html'), 'utf-8'),
        );
        // Ранняя подстановка стилей в начало head — меньше FOUC при SSR
        if (!template.includes('href="/src/index.css"')) {
          template = template.replace(
            /<head>/,
            '<head>\n<link rel="stylesheet" href="/src/index.css" />',
          );
        }
      } else {
        const indexPath = path.resolve(__dirname, '../dist/client/index.html');
        if (!fs.existsSync(indexPath)) {
          throw new Error(`Index file not found at ${indexPath}. Run 'npm run build:ssr' first.`);
        }
        template = fs.readFileSync(indexPath, 'utf-8');
      }

      // Подставляем SEO в head при SSR (title, description, og, canonical)
      if (ssrContext.seo) {
        const s = ssrContext.seo;
        if (s.title) {
          template = template.replace(
            /<title>[\s\S]*?<\/title>/,
            `<title>${escapeHtml(s.title)}</title>`,
          );
        }
        if (s.description) {
          template = template.replace(
            /<meta\s+name="description"\s+content="[^"]*"\s*\/?>/,
            `<meta name="description" content="${escapeHtml(s.description)}">`,
          );
        }
        if (s.canonical_url) {
          template = template.replace(
            /<link\s+rel="canonical"\s+href="[^"]*"\s*\/?>/,
            `<link rel="canonical" href="${escapeHtml(s.canonical_url)}">`,
          );
        }
        if (s.open_graph_title ?? s.title) {
          const ogTitle = s.open_graph_title ?? s.title ?? '';
          template = template.replace(
            /<meta\s+property="og:title"\s+content="[^"]*"\s*\/?>/,
            `<meta property="og:title" content="${escapeHtml(ogTitle)}">`,
          );
        }
        if (s.description) {
          template = template.replace(
            /<meta\s+property="og:description"\s+content="[^"]*"\s*\/?>/,
            `<meta property="og:description" content="${escapeHtml(s.description)}">`,
          );
        }
        if (s.image) {
          const ogImageTag = `<meta property="og:image" content="${escapeHtml(s.image)}">`;
          if (template.includes('property="og:image"')) {
            template = template.replace(
              /<meta\s+property="og:image"\s+content="[^"]*"\s*\/?>/,
              ogImageTag,
            );
          } else {
            template = template.replace('</head>', `${ogImageTag}</head>`);
          }
        }
        if (s.canonical_url) {
          const ogUrl = s.canonical_url;
          template = template.replace(
            /<meta\s+property="og:url"\s+content="[^"]*"\s*\/?>/,
            `<meta property="og:url" content="${escapeHtml(ogUrl)}">`,
          );
        }
      }

      // Вставляем рендеренный HTML и initial state
      const initialState = JSON.stringify(ssrContext).replace(/</g, '\\u003c');
      const htmlWithState = template
        .replace(`<!--ssr-outlet-->`, html)
        .replace('</head>', `<script>window.__INITIAL_STATE__ = ${initialState}</script></head>`);

      res
        .status(200)
        .set({
          'Content-Type': 'text/html; charset=utf-8',
          'Cache-Control': 'private, no-cache, no-store, must-revalidate',
          Pragma: 'no-cache',
          Expires: '0',
        })
        .end(htmlWithState);
    } catch (e: any) {
      // В режиме разработки показываем ошибку Vite
      if (!isProduction && vite && vite.ssrFixStacktrace) {
        try {
          vite.ssrFixStacktrace(e);
        } catch (fixError) {
          // Игнорируем ошибки при исправлении стека
          console.error('SSR Error:', e);
        }
      } else {
        console.error('SSR Error:', e);
      }
      next(e);
    }
  });

  // Обработка ошибок
  app.use((err: any, req: Request, res: Response, next: NextFunction) => {
    console.error('SSR Error:', err);
    res.status(500).end(err.message);
  });

  return app;
}

createServer().then((app) => {
  app.listen(port, () => {
    console.log(`SSR Server running at http://localhost:${port}`);
  });
});
