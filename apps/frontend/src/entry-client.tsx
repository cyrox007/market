import { StrictMode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import './i18n';
import './index.css';
import App from './App.tsx';
import type { SSRContext } from './types/ssr';

declare global {
  interface Window {
    __INITIAL_STATE__?: SSRContext;
  }
}

const rootElement = document.getElementById('root')!;
const initialState = window.__INITIAL_STATE__;

// После деплоя старые вкладки могут ссылаться на удалённые чанки — один авто-reload.
window.addEventListener(
  'error',
  (event) => {
    const target = event.target as HTMLElement | null;
    const isScriptChunk =
      target?.tagName === 'SCRIPT' && (target as HTMLScriptElement).src?.includes('/assets/');
    const message = event.message || '';
    if (
      isScriptChunk ||
      message.includes('Failed to fetch dynamically imported module') ||
      message.includes('Loading chunk')
    ) {
      if (!sessionStorage.getItem('chunk_reload')) {
        sessionStorage.setItem('chunk_reload', '1');
        window.location.reload();
      }
    }
  },
  true,
);

// Если есть отрендеренный SSR-контент — гидратируем, иначе рендерим с нуля (избегаем hydration mismatch и слёта стилей)
const hasRenderedContent = rootElement.hasChildNodes();

if (hasRenderedContent) {
  hydrateRoot(
    rootElement,
    <StrictMode>
      <App ssrContext={initialState} Router={BrowserRouter} />
    </StrictMode>,
  );
} else {
  createRoot(rootElement).render(
    <StrictMode>
      <App ssrContext={initialState} Router={BrowserRouter} />
    </StrictMode>,
  );
}

sessionStorage.removeItem('chunk_reload');
