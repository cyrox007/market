import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react-swc';
import { resolve } from 'node:path';
import AutoImport from 'unplugin-auto-import/vite';

const base = process.env.BASE_PATH || '/';
const isPreview = process.env.IS_PREVIEW ? true : false;
const apiProxyTarget = process.env.API_PROXY || 'http://localhost:8000';

/** В проде убираем ранний link на /src/index.css — в сборке CSS инжектится из бандла */
function earlyCssPlugin() {
  return {
    name: 'early-css-build',
    transformIndexHtml(html: string, ctx: { server?: unknown }) {
      if (ctx.server) return html; // dev: оставляем link на /src/index.css
      return html.replace(/<link\s+rel="stylesheet"\s+href="\/src\/index\.css"\s*\/?>\s*/i, '');
    },
  };
}

// https://vite.dev/config/
export default defineConfig({
  define: {
    __BASE_PATH__: JSON.stringify(base),
    __IS_PREVIEW__: JSON.stringify(isPreview),
  },
  plugins: [
    earlyCssPlugin(),
    react(),
    AutoImport({
      imports: [
        {
          react: [
            'React',
            'useState',
            'useEffect',
            'useContext',
            'useReducer',
            'useCallback',
            'useMemo',
            'useRef',
            'useImperativeHandle',
            'useLayoutEffect',
            'useDebugValue',
            'useDeferredValue',
            'useId',
            'useInsertionEffect',
            'useSyncExternalStore',
            'useTransition',
            'startTransition',
            'lazy',
            'memo',
            'forwardRef',
            'createContext',
            'createElement',
            'cloneElement',
            'isValidElement',
          ],
        },
        {
          'react-router-dom': [
            'useNavigate',
            'useLocation',
            'useParams',
            'useSearchParams',
            'Link',
            'NavLink',
            'Navigate',
            'Outlet',
          ],
        },
        // React i18n
        {
          'react-i18next': ['useTranslation', 'Trans'],
        },
      ],
      dts: true,
    }),
  ],
  base,
  build: {
    sourcemap: true,
    outDir: 'dist/client',
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'index.html'),
      },
    },
  },
  ssr: {
    resolve: {
      conditions: ['node', 'import'],
      dedupe: ['react', 'react-dom'],
    },
    noExternal: ['swr', 'dequal'],
  },
  optimizeDeps: {
    include: ['react-router-dom', 'react-router'],
    esbuildOptions: {
      target: 'node18',
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
    },
  },
  server: {
    port: 3000,
    host: '0.0.0.0',
    proxy: {
      '/api': apiProxyTarget,
      '/api-docs': apiProxyTarget,
      '/admin_sv': apiProxyTarget,
      '/storage': apiProxyTarget,
    }
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: './src/test/setup.ts',
    include: ['src/**/*.test.ts', 'src/**/*.test.tsx'],
  },
});
