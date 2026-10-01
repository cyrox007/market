import { Outlet } from 'react-router-dom';
import Header from './Header/HeaderConnected';
import Footer from './Footer';
import BottomBar from './BottomBar/BottomBarConnected';
import CookieConsentBanner from '../CookieConsentBanner';

/**
 * Общий layout: Header и Footer не размонтируются при навигации,
 * меняется только контент (Outlet). Убирает «прыжки» стилей и скелет всей страницы.
 * Ниже 550 снизу запас под закреплённое нижнее меню — иначе оно накроет подвал.
 */
export default function RootLayout() {
  return (
    <div className="min-h-screen bg-white flex flex-col max-vsm:pb-[calc(68px+env(safe-area-inset-bottom))]">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
      <BottomBar />
      <CookieConsentBanner />
    </div>
  );
}
