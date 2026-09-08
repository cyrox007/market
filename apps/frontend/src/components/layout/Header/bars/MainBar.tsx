import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Columns2, Menu, Search, X } from 'lucide-react';
import { Button, SearchInput } from '../../../ui/primitives';
import UserActions from '../menu/UserActions';
import { useMediaQuery } from '../../../../hooks/useMediaQuery';
import { cn } from '../../../../lib/cn';
import { HEADER_CONTAINER } from '../lib/container';

interface MainBarProps {
  catalogOpen?: boolean;
  roomsOpen?: boolean;
  onCatalogToggle?: () => void;
  onRoomsToggle?: () => void;
  onSearch?: (query: string) => void;
  compareCount?: number;
  wishlistCount?: number;
  cartCount?: number;
  isAuthenticated?: boolean;
  className?: string;
}

/** Основная полоса шапки. Замеры и адаптив — market-docs/13-header.md */

/** Кнопки «Каталог» и «Комнаты»: текст, круг 40, голая иконка 24 */
const COLLAPSING_BUTTON =
  'max-xl:size-10 max-xl:rounded-full max-xl:p-0 max-vsm:size-auto max-vsm:bg-transparent max-vsm:hover:bg-transparent';

const COLLAPSING_ICON = 'size-6 max-xl:size-5 max-vsm:size-6';

export default function MainBar({
  catalogOpen = false,
  roomsOpen = false,
  onCatalogToggle,
  onRoomsToggle,
  onSearch,
  compareCount,
  wishlistCount,
  cartCount,
  isAuthenticated,
  className,
}: MainBarProps) {
  const [searchOpen, setSearchOpen] = useState(false);
  const isNarrow = useMediaQuery('(max-width: 979.98px)');

  return (
    <div className={cn('bg-surface', className)}>
      <div className={cn(HEADER_CONTAINER, 'flex h-20 items-center justify-between')}>
        <Link to="/" className="inline-flex shrink-0 outline-none" aria-label="На главную">
          <img
            src="/logo.png"
            alt="Светофор Мебели"
            className="h-12 w-[126px] max-md:h-10 max-md:w-[105px]"
          />
        </Link>

        <div className="flex shrink-0 items-center gap-3 max-vsm:gap-4">
          {/* Кнопка поиска только ниже 550: там поле убрано и разворачивается по клику */}
          <button
            type="button"
            onClick={() => setSearchOpen((open) => !open)}
            aria-expanded={searchOpen}
            aria-label={searchOpen ? 'Скрыть поиск' : 'Показать поиск'}
            className="hidden outline-none max-vsm:inline-flex"
          >
            {searchOpen ? <X className="size-6" /> : <Search className="size-6" />}
          </button>

          <Button
            size="sm"
            onClick={onCatalogToggle}
            aria-expanded={catalogOpen}
            className={COLLAPSING_BUTTON}
            leftIcon={
              catalogOpen ? <X className={COLLAPSING_ICON} /> : <Menu className={COLLAPSING_ICON} />
            }
          >
            <span className="max-xl:hidden">Каталог</span>
          </Button>

          <Button
            size="sm"
            variant={roomsOpen ? 'primary' : 'secondary'}
            onClick={onRoomsToggle}
            aria-expanded={roomsOpen}
            className={COLLAPSING_BUTTON}
            leftIcon={
              roomsOpen ? (
                <X className={COLLAPSING_ICON} />
              ) : (
                <Columns2 className={COLLAPSING_ICON} />
              )
            }
          >
            <span className="max-xl:hidden">Комнаты</span>
          </Button>
        </div>

        <div className="w-full min-w-0 max-w-[475px] shrink max-vsm:hidden">
          <SearchInput
            placeholder={isNarrow ? 'Поиск по каталогу' : 'Диван-кровать, шкаф-купе, матрас...'}
            onSearch={onSearch}
          />
        </div>

        <UserActions
          compareCount={compareCount}
          wishlistCount={wishlistCount}
          cartCount={cartCount}
          isAuthenticated={isAuthenticated}
          className="shrink-0 max-vsm:hidden"
        />
      </div>

      {searchOpen && (
        <div className={cn(HEADER_CONTAINER, 'hidden pb-4 max-vsm:block')}>
          <SearchInput placeholder="Поиск по каталогу" onSearch={onSearch} />
        </div>
      )}
    </div>
  );
}
