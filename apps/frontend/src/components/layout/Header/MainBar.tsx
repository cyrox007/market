import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Columns2, Menu, Search, X } from 'lucide-react';
import { Button, SearchInput } from '../../ui/primitives';
import UserActions from './UserActions';
import { useMediaQuery } from '../../../hooks/useMediaQuery';
import { cn } from '../../../lib/cn';
import { HEADER_CONTAINER } from './container';

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

/**
 * Основная полоса шапки: логотип, «Каталог», «Комнаты», поиск, правая группа.
 *
 * Замерено с макета 07.09.2026: высота полосы **80**, поле поиска до **475**,
 * кнопки 44, правая группа 298 × 43.
 *
 * Крупные блоки разложены `justify-between` — в макете расстояния между ними
 * равномерные и не round-числа (около 18.5), то есть это распределение, а не
 * заданный промежуток.
 *
 * Адаптив:
 *   ≤ 1279 (max-xl)  «Каталог» и «Комнаты» сжимаются в круги 40 с иконкой 20
 *   ≤ 979 (max-md)   у правой группы пропадают подписи, остаются иконки
 *   ≤ 549 (max-vsm)  кнопки становятся голыми иконками 24, правая группа
 *                    исчезает целиком, поле поиска сворачивается в иконку
 *
 * Порог сворачивания кнопок — 1280, а не макетные 980. В макете нарисованы только
 * 1440 и 980, а между ними пара кнопок с текстом занимает 289 пикселей, и поле
 * поиска на 1024 ужимается примерно до 110. С кругами блоки занимают 517 вместо
 * 714, и поиску остаётся около 307. Взят существующий `xl`, своих чисел не
 * добавлено; на 1280 заодно пропадает режим работы, то есть шапка переходит в
 * компактный вид одним шагом.
 *
 * Логотип замерен 07.09.2026: **125.62 × 48.03** на десктопе и **104.68 × 40.03**
 * ниже 980, где он и остаётся до самых узких экранов. Пропорция одна и та же
 * (2.615 против 2.616), то есть это простое уменьшение, а не другая отрисовка.
 * ⚠️ Раскрытие поиска по клику на узких экранах в макете не нарисовано. Сделано
 * простейшее: поле разворачивается отдельной строкой под логотипом.
 */

/** Общие классы кнопок «Каталог» и «Комнаты» на трёх ширинах */
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

        <div className="flex shrink-0 items-center gap-3">
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

        {/* min-w-0 обязателен: без него флекс-элемент не сжимается уже своего
            содержимого, и на 1000 полоса переполнялась на 19 пикселей */}
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
