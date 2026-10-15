import { useId, useState } from 'react';

interface CategoryDescriptionProps {
  /** Название раздела — заголовок, если в описании одна строка */
  name: string;
  /** category.description из админки: первая строка — заголовок блока (market-docs/44, ответ 8) */
  description: string;
}

function splitTitle(name: string, description: string) {
  const [first, ...rest] = description.trim().split('\n');
  const body = rest.join('\n').trim();
  return body ? { title: first.trim(), body } : { title: name, body: description.trim() };
}

export default function CategoryDescription({ name, description }: CategoryDescriptionProps) {
  const [expanded, setExpanded] = useState(false);
  const contentId = useId();
  const { title, body } = splitTitle(name, description);

  return (
    <section className="rounded-card bg-surface-grey px-6 py-5 max-vsm:rounded-[16px] max-vsm:px-4 max-vsm:py-4">
      <h2 className="text-18 font-semibold text-ink max-vsm:text-16">{title}</h2>

      <div
        id={contentId}
        className={[
          'mt-2 whitespace-pre-line text-14 leading-[1.45] text-ink-secondary',
          expanded ? '' : 'line-clamp-2',
        ].join(' ')}
      >
        {body}
      </div>

      <button
        type="button"
        aria-expanded={expanded}
        aria-controls={contentId}
        onClick={() => setExpanded((value) => !value)}
        className="mt-3 text-14 font-semibold text-ink transition-colors hover:text-ink-secondary"
      >
        {expanded ? 'Скрыть' : 'Показать полностью'}
      </button>
    </section>
  );
}
