
import { useState, useRef, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { useCounters } from '../../hooks/useCounters';
import RegionSelector from '../RegionSelector';

export default function Header() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);
  const menuButtonRef = useRef<HTMLButtonElement>(null);
  const headerRef = useRef<HTMLDivElement>(null);
  const [menuTop, setMenuTop] = useState(0);
  const auth = useAuth();
  const { isAuthenticated, user, isLoading } = auth;
  const { cartCount, wishlistCount, compareCount } = useCounters();
  const [searchQuery, setSearchQuery] = useState('');
  const navigate = useNavigate();

  const closeMenu = useCallback(() => setIsMenuOpen(false), []);

  useEffect(() => {
    const headerEl = headerRef.current;
    if (!headerEl) return;

    const updateMenuTop = () => {
      setMenuTop(headerEl.getBoundingClientRect().bottom);
    };

    updateMenuTop();

    const observer = new ResizeObserver(updateMenuTop);
    observer.observe(headerEl);
    window.addEventListener('resize', updateMenuTop);
    window.addEventListener('scroll', updateMenuTop, { passive: true });

    return () => {
      observer.disconnect();
      window.removeEventListener('resize', updateMenuTop);
      window.removeEventListener('scroll', updateMenuTop);
    };
  }, []);

  useEffect(() => {
    if (!isMenuOpen) return;

    const handlePointerDown = (event: PointerEvent) => {
      const target = event.target as Node;
      if (menuRef.current?.contains(target) || menuButtonRef.current?.contains(target)) {
        return;
      }
      closeMenu();
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        closeMenu();
      }
    };

    document.addEventListener('pointerdown', handlePointerDown);
    document.addEventListener('keydown', handleKeyDown);

    return () => {
      document.removeEventListener('pointerdown', handlePointerDown);
      document.removeEventListener('keydown', handleKeyDown);
    };
  }, [isMenuOpen, closeMenu]);

  useEffect(() => {
    if (!isMenuOpen) return;
    headerRef.current && setMenuTop(headerRef.current.getBoundingClientRect().bottom);
  }, [isMenuOpen]);

  useEffect(() => {
    const mediaQuery = window.matchMedia('(min-width: 1024px)');
    const handleChange = () => {
      if (mediaQuery.matches) {
        closeMenu();
      }
    };

    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, [closeMenu]);

  useEffect(() => {
    document.body.style.overflow = isMenuOpen ? 'hidden' : '';
    return () => {
      document.body.style.overflow = '';
    };
  }, [isMenuOpen]);

  return (
    <>
      <div className='hidden lg:flex justify-between items-center max-w-[1280px] w-full mx-auto px-4 py-2 border-b border-gray-200'>
        {/* Region Selector - Desktop Only */}
        <div className="hidden lg:flex lg:min-w-0 lg:max-w-[220px] xl:max-w-[300px]">
          <RegionSelector />
        </div>
        <div className='flex gap-10'>
          <Link to="/about" className="text-gray-900 hover:text-red-600 transition-colors whitespace-nowrap text-sm">
            О нас
          </Link>
          <Link to="/stores" className="text-gray-900 hover:text-red-600 transition-colors whitespace-nowrap text-sm">
            Магазины
          </Link>
          <Link to="/delivery" className="text-gray-900 hover:text-red-600 transition-colors whitespace-nowrap text-sm">
            Доставка
          </Link>
          <Link to="/returns" className="text-gray-900 hover:text-red-600 transition-colors whitespace-nowrap text-sm">
            Возврат
          </Link>
        </div>
        <div className="flex flex-col items-center gap-1 text-sm">
          <a href="tel:88002228586" className="text-gray-900 hover:text-red-600 font-semibold whitespace-nowrap">
            8 (800) 222-85-86
          </a>
          <span className="text-gray-400 text-xs">Ежедневно с 9:00 до 21:00</span>
        </div>
      </div>
      <div ref={headerRef} className="header-locator sticky top-0 z-40 ">
        {/* Top Bar - Mobile Only */}
        <div className="lg:hidden bg-white border-b border-gray-200">
          <div className="max-w-[1280px] mx-auto px-4 py-2">
            <div className="flex items-center justify-center">
              <RegionSelector />
            </div>
          </div>
        </div>

        <header className="bg-white border-b border-gray-200">
          {/* Main Header - 64px height */}
          <div className="max-w-[1280px] mx-auto px-4">
            <div className="header-locator-grid grid grid-cols-2 lg:grid-cols-3 items-center h-16 gap-4">
              {/* Left Container - Logo */}
              <div className="flex items-center gap-4">
                <button
                  ref={menuButtonRef}
                  type="button"
                  onClick={() => setIsMenuOpen((open) => !open)}
                  className="lg:hidden w-10 h-10 flex items-center justify-center mr-2"
                  aria-expanded={isMenuOpen}
                  aria-controls="mobile-nav"
                  aria-label={isMenuOpen ? 'Закрыть меню' : 'Открыть меню'}
                >
                  <i className={`${isMenuOpen ? 'ri-close-line' : 'ri-menu-line'} text-2xl`}></i>
                </button>
                {/* ВАЖНО: НЕ МЕНЯТЬ ЛОГОТИП БЕЗ УКАЗАНИЯ ПОЛЬЗОВАТЕЛЯ */}
                <Link to="/" className="flex items-center">
                  <img
                    src="/logo.png"
                    alt="Светофор Мебели"
                    className="h-10 md:h-12 object-contain"
                  />
                </Link>
                <Link to="/catalog" className="hidden lg:flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
                  <i className="ri-menu-line text-lg"></i>
                  Каталог
                </Link>
              </div>

              {/* Center Container - Search */}
              <div className="hidden lg:flex items-center justify-center">
                <form
                  className="relative w-full max-w-md"
                  onSubmit={(e) => {
                    e.preventDefault();
                    if (searchQuery.trim()) {
                      navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}`);
                    }
                  }}
                >
                  <input
                    type="text"
                    placeholder="Поиск товаров..."
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                    className="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-full text-sm focus:outline-none focus:border-red-600 transition-colors bg-white"
                  />
                  <i className="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                  {searchQuery && (
                    <button
                      type="button"
                      onClick={() => setSearchQuery('')}
                      className="absolute right-12 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      <i className="ri-close-line text-lg"></i>
                    </button>
                  )}
                  <button
                    type="submit"
                    className="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-600 transition-colors"
                  >
                    <i className="ri-search-line text-lg"></i>
                  </button>
                </form>
              </div>

              {/* Right Container - Icons */}
              <div className="min-w-0 flex items-center justify-end gap-1.5 sm:gap-2 lg:gap-3">
                

                {/* Icons Container */}
                <div className="flex shrink-0 items-center gap-1.5 sm:gap-2 lg:gap-3">
                  <Link
                    to="/favorites"
                    className="relative w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center hover:bg-gray-200 rounded-full cursor-pointer transition-colors"
                    title="Избранное"
                  >
                    <i className="ri-heart-line text-lg sm:text-xl"></i>
                    {wishlistCount > 0 && (
                      <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-600 text-white text-[10px] sm:text-xs rounded-full flex items-center justify-center px-1 font-medium shadow-sm">
                        {wishlistCount > 99 ? '99+' : wishlistCount}
                      </span>
                    )}
                  </Link>

                  {/* Compare - Hidden on very small screens, show from 360px */}
                  <Link
                    to="/compare"
                    className="hidden min-[360px]:flex relative w-9 h-9 sm:w-10 sm:h-10 items-center justify-center hover:bg-gray-200 rounded-full cursor-pointer transition-colors"
                    title="Сравнение"
                  >
                    <i className="ri-scales-3-line text-lg sm:text-xl"></i>
                    {compareCount > 0 && (
                      <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-600 text-white text-[10px] sm:text-xs rounded-full flex items-center justify-center px-1 font-medium shadow-sm">
                        {compareCount > 99 ? '99+' : compareCount}
                      </span>
                    )}
                  </Link>

                  {/* Orders - Hidden on mobile */}
                  <Link
                    to="/orders"
                    className="hidden md:flex w-9 h-9 sm:w-10 sm:h-10 items-center justify-center hover:bg-gray-200 rounded-full cursor-pointer transition-colors"
                    title="Заказы"
                  >
                    <i className="ri-file-list-3-line text-lg sm:text-xl"></i>
                  </Link>

                  {/* Profile */}
                  {!isLoading && isAuthenticated && user ? (
                    <Link
                      to="/account"
                      className="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center hover:bg-gray-200 rounded-full cursor-pointer transition-colors"
                      title={user.name}
                    >
                      <i className="ri-user-line text-lg sm:text-xl"></i>
                    </Link>
                  ) : !isLoading ? (
                    <Link
                      to="/login"
                      className="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center hover:bg-gray-200 rounded-full cursor-pointer transition-colors"
                      title="Войти"
                    >
                      <i className="ri-user-line text-lg sm:text-xl"></i>
                    </Link>
                  ) : (
                    <div className="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center">
                      <div className="w-5 h-5 border-2 border-gray-300 border-t-red-600 rounded-full animate-spin"></div>
                    </div>
                  )}

                  <Link
                    to="/cart"
                    className="relative w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center hover:bg-gray-200 rounded-full cursor-pointer transition-colors"
                    title="Корзина"
                  >
                    <i className="ri-shopping-cart-line text-lg sm:text-xl"></i>
                    {cartCount > 0 && (
                      <span className="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-600 text-white text-[10px] sm:text-xs rounded-full flex items-center justify-center px-1 font-medium shadow-sm">
                        {cartCount > 99 ? '99+' : cartCount}
                      </span>
                    )}
                  </Link>
                </div>
              </div>
            </div>

            {/* Mobile Search */}
            <div className="lg:hidden py-3 border-t border-gray-200">
              <form
                className="relative"
                onSubmit={(e) => {
                  e.preventDefault();
                  if (searchQuery.trim()) {
                    navigate(`/search?q=${encodeURIComponent(searchQuery.trim())}`);
                  }
                }}
              >
                <input
                  type="text"
                  placeholder="Поиск товаров..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-10 pr-12 py-2.5 border border-gray-300 rounded-full text-sm focus:outline-none focus:border-red-600 bg-white"
                />
                <i className="ri-search-line absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                {searchQuery && (
                  <button
                    type="button"
                    onClick={() => setSearchQuery('')}
                    className="absolute right-12 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  >
                    <i className="ri-close-line text-lg"></i>
                  </button>
                )}
                <button
                  type="submit"
                  className="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 flex items-center justify-center text-gray-400 hover:text-red-600 transition-colors"
                >
                  <i className="ri-search-line text-lg"></i>
                </button>
              </form>
            </div>
          </div>
        </header>
      </div>

      {isMenuOpen && (
        <>
          <div
            className="fixed inset-x-0 bottom-0 z-30 bg-black/20 lg:hidden"
            style={{ top: menuTop }}
            onClick={closeMenu}
            aria-hidden="true"
          />
          <div
            ref={menuRef}
            className="fixed inset-x-0 z-40 lg:hidden border-t border-gray-200 animate-fadeIn bg-[#F5F5F5] shadow-lg overflow-y-auto"
            style={{ top: menuTop, maxHeight: `calc(100dvh - ${menuTop}px)` }}
          >
            <nav id="mobile-nav" className="flex flex-col">
              <Link
                to="/catalog"
                onClick={closeMenu}
                className="px-4 py-3 text-gray-900 hover:bg-gray-200 border-b border-gray-200"
              >
                Каталог
              </Link>
              <Link
                to="/about"
                onClick={closeMenu}
                className="px-4 py-3 text-gray-900 hover:bg-gray-200 border-b border-gray-200"
              >
                О нас
              </Link>
              <Link
                to="/stores"
                onClick={closeMenu}
                className="px-4 py-3 text-gray-900 hover:bg-gray-200 border-b border-gray-200"
              >
                Магазины
              </Link>
              <Link
                to="/delivery"
                onClick={closeMenu}
                className="px-4 py-3 text-gray-900 hover:bg-gray-200 border-b border-gray-200"
              >
                Доставка
              </Link>
              <Link
                to="/returns"
                onClick={closeMenu}
                className="px-4 py-3 text-gray-900 hover:bg-gray-200 border-b border-gray-200"
              >
                Возврат
              </Link>
              <a
                href="tel:+78001234567"
                onClick={closeMenu}
                className="px-4 py-3 text-gray-900 hover:bg-gray-200 font-medium"
              >
                +7 (800) 123-45-67
              </a>
            </nav>
          </div>
        </>
      )}

      {/* Navigation Links - Below header, scrolls with content */}
      {/* тут тогда остается список пунктов меню категорий корневых */}
      {/* <div className="hidden lg:block bg-white border-b border-gray-200">
        <div className="max-w-[1280px] mx-auto px-4">
          <nav className="flex items-center justify-center gap-8 py-3">
            
            
            
          </nav>
        </div>
      </div> */}
    </>
  );
}
