import { StrictMode } from 'react';
import './i18n';
import { BrowserRouter } from 'react-router-dom';
import { createRoot, hydrateRoot } from 'react-dom/client';
import './index.css';
import App from './App.tsx';
import type { SSRContext } from './types/ssr';

const rootElement = document.getElementById('root')!;

declare global {
  interface Window {
    __INITIAL_STATE__?: SSRContext;
  }
}

// При гидрации используем тот же ssrContext, что и на сервере — иначе разный вывод (region и т.д.) даёт hydration mismatch
const ssrContext = typeof window !== 'undefined' ? window.__INITIAL_STATE__ : undefined;

// Проверяем, есть ли уже отрендеренный контент (SSR режим)
/* const hasRenderedContent = rootElement.hasChildNodes() */

/* if (hasRenderedContent) {
  // SSR режим - используем гидратацию
  hydrateRoot(
    rootElement,
    <StrictMode>
      <App Router={BrowserRouter} ssrContext={ssrContext} />
    </StrictMode>
  )
} else { */
// SPA режим - обычный рендеринг
createRoot(rootElement).render(
  <StrictMode>
    <App Router={BrowserRouter} ssrContext={ssrContext} />
  </StrictMode>,
);
/* } */
