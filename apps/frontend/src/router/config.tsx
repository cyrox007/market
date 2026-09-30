import { lazy } from 'react';
import type { RouteObject } from 'react-router';
import RootLayout from '../components/layout/RootLayout';
import ProtectedRoute from '../components/auth/ProtectedRoute';
import Catalog from '../pages/catalog/page';
import CatalogCategory from '../pages/catalog-category/page';
import Product from '../pages/product/page';

const Home = lazy(() => import('../pages/home/page'));
const Sets = lazy(() => import('../pages/sets/page'));
const SetPage = lazy(() => import('../pages/set/page'));
const Stores = lazy(() => import('../pages/stores/page'));
const Delivery = lazy(() => import('../pages/delivery/page'));
const Returns = lazy(() => import('../pages/returns/page'));
const Rental = lazy(() => import('../pages/rental/page'));
const Careers = lazy(() => import('../pages/careers/page'));
const Documents = lazy(() => import('../pages/documents/page'));
const Privacy = lazy(() => import('../pages/privacy/page'));
const Oferta = lazy(() => import('../pages/oferta/page'));
const Cookies = lazy(() => import('../pages/cookies/page'));
const About = lazy(() => import('../pages/about/page'));
const Contacts = lazy(() => import('../pages/contacts/page'));
const Cart = lazy(() => import('../pages/cart/page'));
const Checkout = lazy(() => import('../pages/checkout/page'));
const Account = lazy(() => import('../pages/account/page'));
const Login = lazy(() => import('../pages/login/page'));
const Register = lazy(() => import('../pages/register/page'));
const ForgotPassword = lazy(() => import('../pages/forgot-password/page'));
const ResetPassword = lazy(() => import('../pages/reset-password/page'));
const Favorites = lazy(() => import('../pages/favorites/page'));
const Compare = lazy(() => import('../pages/compare/page'));
const Orders = lazy(() => import('../pages/orders/page'));
const OrderDetail = lazy(() => import('../pages/orders/order-detail/page'));
const Search = lazy(() => import('../pages/search/page'));
const Collection = lazy(() => import('../pages/collection/page.tsx'));
const NotFound = lazy(() => import('../pages/NotFound'));

/**
 * Песочница UI-примитивов, маршрут /__ui. Отдельно от RootLayout: шапка и
 * подвал сидят на генерических палитрах Tailwind и мешали бы сверять цвета.
 *
 * lazy() вызывается ВНУТРИ условия намеренно. На уровне модуля сборщик выпускает
 * чанк для любого динамического импорта, даже если маршрут не зарегистрирован, —
 * проверено, песочница уезжала в прод отдельным файлом. Внутри `if` с константой
 * import.meta.env.DEV вся ветка вырезается вместе с импортом.
 */
const devRoutes: RouteObject[] = [];

if (import.meta.env.DEV) {
  const UiSandbox = lazy(() => import('../pages/ui-sandbox/page'));
  devRoutes.push({ path: '__ui', element: <UiSandbox /> });
}

const routes: RouteObject[] = [
  ...devRoutes,
  {
    element: <RootLayout />,
    children: [
      { index: true, element: <Home /> },
      { path: 'catalog', element: <Catalog /> },
      { path: 'catalog/:category', element: <CatalogCategory /> },
      { path: 'rooms/:category', element: <CatalogCategory /> },
      { path: 'collections/:slug', element: <Collection /> },
      { path: 'product/:slug', element: <Product /> },
      { path: 'sets', element: <Sets /> },
      { path: 'set/:id', element: <SetPage /> },
      { path: 'stores', element: <Stores /> },
      { path: 'delivery', element: <Delivery /> },
      { path: 'returns', element: <Returns /> },
      { path: 'rental', element: <Rental /> },
      { path: 'careers', element: <Careers /> },
      { path: 'documents', element: <Documents /> },
      { path: 'privacy', element: <Privacy /> },
      { path: 'oferta', element: <Oferta /> },
      { path: 'cookies', element: <Cookies /> },
      { path: 'about', element: <About /> },
      { path: 'contacts', element: <Contacts /> },
      { path: 'cart', element: <Cart /> },
      { path: 'checkout', element: <Checkout /> },
      { path: 'login', element: <Login /> },
      { path: 'register', element: <Register /> },
      { path: 'forgot-password', element: <ForgotPassword /> },
      { path: 'reset-password', element: <ResetPassword /> },
      {
        path: 'account',
        element: (
          <ProtectedRoute>
            <Account />
          </ProtectedRoute>
        ),
      },
      { path: 'favorites', element: <Favorites /> },
      { path: 'compare', element: <Compare /> },
      {
        path: 'orders',
        element: (
          <ProtectedRoute>
            <Orders />
          </ProtectedRoute>
        ),
      },
      {
        path: 'orders/:id',
        element: (
          <ProtectedRoute>
            <OrderDetail />
          </ProtectedRoute>
        ),
      },
      { path: 'search', element: <Search /> },
      { path: '*', element: <NotFound /> },
    ],
  },
];

export default routes;
