/**
 * UI-примитивы по макету. Этап 1 — то, что встречается на главной.
 * Спецификации и принятые решения: market-docs/04-button-hover-motion.md.
 */
export { default as Button } from './Button';
// ButtonFill наружу не отдаём: заливка — заготовка, а не публичный API
export type { ButtonVariant, ButtonSize, ButtonShape } from './Button';

export { default as IconButton } from './IconButton';
export type { IconButtonVariant, IconButtonSize } from './IconButton';

export { default as Badge } from './Badge';
export type { BadgeVariant, BadgeSize, BadgeShape } from './Badge';

export { default as Input } from './Input';
export type { InputSize, InputShape } from './Input';

export { default as SearchInput } from './SearchInput';

export { default as Tooltip } from './Tooltip';

export { default as SmartLink } from './SmartLink';
