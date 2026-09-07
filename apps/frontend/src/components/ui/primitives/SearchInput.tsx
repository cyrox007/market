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
 * Поле поиска из шапки: пилюля на сером фоне с жёлтой круглой кнопкой внутри.
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
        className={cn('[&::-webkit-search-cancel-button]:appearance-none', className)}
        rightSlot={
          <IconButton
            type="submit"
            label={submitLabel}
            variant="yellow"
            size="md"
            disabled={rest.disabled}
          >
            <Search className="size-[1em] text-18" />
          </IconButton>
        }
      />
    </form>
  );
});

export default SearchInput;
