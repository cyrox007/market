import { useState } from 'react';
import type { ColorSolution } from '../../../lib/api';
import { solutionBackground } from '../../../lib/product-colors';
import { cn } from '../../../lib/cn';

export default function ColorSample({
  solution,
  className,
}: {
  solution: ColorSolution;
  className?: string;
}) {
  const [failedUrl, setFailedUrl] = useState<string | null>(null);
  return (
    <span
      aria-hidden="true"
      className={cn(
        'relative block size-6 shrink-0 overflow-hidden rounded-swatch border border-surface-border',
        className,
      )}
      style={{ background: solutionBackground(solution) }}
    >
      {solution.icon_url && failedUrl !== solution.icon_url && (
        <img
          src={solution.icon_url}
          alt=""
          className="absolute inset-0 size-full object-cover"
          onError={() => setFailedUrl(solution.icon_url)}
        />
      )}
    </span>
  );
}
