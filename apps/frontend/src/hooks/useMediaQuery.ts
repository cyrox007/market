import { useEffect, useState } from 'react';

/**
 * Подписка на медиазапрос.
 *
 * Нужен там, где ширину экрана нельзя выразить классом: например, в макете
 * шапки плейсхолдер поиска на узких экранах другой, а CSS текст не подменяет.
 * Для всего, что решается вариантами `max-md:` и `max-vsm:`, хук не нужен —
 * он тянет за собой ререндер и лишнее состояние.
 *
 * Первый рендер всегда возвращает `false`, даже если запрос совпадает. Это
 * намеренно: на сервере `window` нет, и совпади они по-разному, гидрация
 * ругалась бы на рассинхрон. Значение уточняется в эффекте, уже после гидрации.
 */
export function useMediaQuery(query: string): boolean {
  const [matches, setMatches] = useState(false);

  useEffect(() => {
    const media = window.matchMedia(query);
    setMatches(media.matches);

    const handleChange = (event: MediaQueryListEvent) => setMatches(event.matches);
    media.addEventListener('change', handleChange);
    return () => media.removeEventListener('change', handleChange);
  }, [query]);

  return matches;
}
