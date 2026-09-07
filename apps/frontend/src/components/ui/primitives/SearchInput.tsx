import { forwardRef } from 'react';
import type { FormEvent, InputHTMLAttributes } from 'react';
import { Search } from 'lucide-react';
import Input from './Input';
import IconButton from './IconButton';
import { cn } from '../../../lib/cn';

interface SearchInputOwnProps {
  onSearch?: (value: string) => void;
  submitLabel?: string;
  className?: string;
  wrapperClassName?: string;
}

type SearchInputProps = SearchInputOwnProps &
  Omit<InputHTMLAttributes<HTMLInputElement>, keyof SearchInputOwnProps | 'size' | 'type'>;

/**
 * Поле поиска из шапки. Замерено с макета 07.09.2026:
 * высота 44, кегль 14, строка 17, круглая жёлтая кнопка 36 с иконкой 20,
 * в покое светлая рамка, в фокусе тёмная.
 *
 * Ширина адаптивная: максимум 475 на десктопе, дальше сжимается по экрану.
 * Здесь она НЕ задана — поле занимает ширину родителя, а ограничение ставится
 * в месте использования: className="w-full max-w-[475px]".
 *
 * Обёрнуто в <form>, чтобы Enter отправлял запрос без своего обработчика клавиш.
 */
const SearchInput = forwardRef<HTMLInputElement, SearchInputProps>(function SearchInput(
  { onSearch, submitLabel = 'Найти', className, wrapperClassName, ...rest },
  ref,
) {
  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!onSearch) return;
    const field = event.currentTarget.elements.namedItem('q');
    if (field instanceof HTMLInputElement) {
      onSearch(field.value.trim());
    }
  };

  return (
    <form role="search" onSubmit={handleSubmit} className={cn('w-full', wrapperClassName)}>
      <Input
        ref={ref}
        {...rest}
        name="q"
        type="search"
        tone="white"
        className={cn('[&::-webkit-search-cancel-button]:appearance-none', className)}
        rightSlot={
          <IconButton
            type="submit"
            label={submitLabel}
            variant="yellow"
            size="sm"
            disabled={rest.disabled}
          >
            <Search className="size-5" />
          </IconButton>
        }
      />
    </form>
  );
});

export default SearchInput;
