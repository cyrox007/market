import type { SVGProps } from 'react';

/**
 * Стрелка карусели на главной. Контур из макета без изменений, только `stroke`
 * переведён на `currentColor`. У Lucide сетка 24×24, и на размере 60 её линия
 * утолщается втрое — отсюда своя иконка. Смотрит влево, вправо — зеркалом.
 */
export function SliderArrowIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 17 37" fill="none" aria-hidden {...props}>
      <path
        d="M16 1L1 18.5L16 36"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
