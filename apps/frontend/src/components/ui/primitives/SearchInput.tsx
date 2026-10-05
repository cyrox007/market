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
 * Ширину не задаёт — поле занимает ширину родителя, ограничение ставится
 * в месте использования. Обёрнуто в <form>, чтобы Enter работал сам.
 * Замеры — market-docs/09-ui-primitives-stage-1.md.
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
