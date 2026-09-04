import { renderToPipeableStream } from 'react-dom/server';
import { Writable } from 'stream';
import { StaticRouter } from 'react-router';
import App from './App.tsx';
import type { SSRContext } from './types/ssr';

/**
 * Рендер с поддержкой Suspense (lazy-роутов).
 * renderToString не поддерживает Suspense; используем renderToPipeableStream
 * и собираем поток в строку для совместимости с текущим API сервера.
 */
export function render(url: string, context: SSRContext): Promise<string> {
  return new Promise((resolve, reject) => {
    const chunks: Buffer[] = [];
    const writable = new Writable({
      write(chunk: Buffer | string, _enc, cb) {
        chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
        cb();
      },
    });
    writable.on('finish', () => {
      resolve(Buffer.concat(chunks).toString('utf8'));
    });
    writable.on('error', reject);

    let piped = false;
    const { pipe, abort } = renderToPipeableStream(
      <App ssrContext={context} Router={StaticRouter as any} routerProps={{ location: url }} />,
      {
        onAllReady() {
          if (!piped) {
            piped = true;
            pipe(writable);
          }
        },
        onError(err) {
          reject(err);
        },
      },
    );
  });
}
