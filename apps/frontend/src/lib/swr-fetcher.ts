/**
 * SWR Fetcher - кастомный fetcher для SWR с поддержкой API клиента
 * 
 * SWR ключи должны быть в формате, который можно распарсить для вызова правильного API метода
 * Это будет обрабатываться в конкретных компонентах через кастомные fetcher функции
 */

/**
 * Базовый fetcher для SWR (используется как fallback)
 * В большинстве случаев компоненты будут использовать кастомные fetcher функции
 */
export async function swrFetcher<T>(key: string): Promise<T> {
  // Для прямых URL запросов
  if (key.startsWith('http://') || key.startsWith('https://')) {
    const response = await fetch(key, {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });
    
    if (!response.ok) {
      const error: any = new Error(`Failed to fetch: ${response.statusText}`);
      error.status = response.status;
      throw error;
    }
    
    return response.json();
  }
  
  // Для ключей вида /api/* - это должно обрабатываться в компонентах
  // с кастомными fetcher функциями
  throw new Error(`Unsupported SWR key format: ${key}. Use custom fetcher in components.`);
}
