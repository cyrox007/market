import { useId, useState } from 'react';

interface CategoryDescriptionProps {
  title: string;
  description: string;
}

export default function CategoryDescription({
  title,
  description,
}: CategoryDescriptionProps) {
  const [expanded, setExpanded] = useState(false);
  const contentId = useId();

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
        {description}
      </div>

      <button
        type="button"
        aria-expanded={expanded}
        aria-controls={contentId}
        onClick={() => setExpanded((value) => !value)}
        className="mt-3 text-14 font-semibold text-ink transition-colors hover:text-brand-green"
      >
        {expanded ? 'Скрыть' : 'Показать полностью'}
      </button>
    </section>
  );
}
