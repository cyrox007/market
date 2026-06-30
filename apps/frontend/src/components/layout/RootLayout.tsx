import { Outlet } from 'react-router-dom';
import Header from '../feature/Header';
import Footer from '../feature/Footer';
import CookieConsentBanner from '../CookieConsentBanner';

/**
 * Общий layout: Header и Footer не размонтируются при навигации,
 * меняется только контент (Outlet). Убирает «прыжки» стилей и скелет всей страницы.
 */
export default function RootLayout() {
  return (
    <div className="min-h-screen bg-white flex flex-col">
      <Header />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
      <CookieConsentBanner />
    </div>
  );
}
