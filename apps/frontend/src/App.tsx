
import { Suspense, useEffect } from "react";
import { AppRoutes } from "./router/index.tsx";
import { I18nextProvider } from "react-i18next";
import i18n from "./i18n";
import { SWRConfig } from "swr";
import { AuthProvider } from "./contexts/AuthContext";
import { CountersProvider } from "./contexts/CountersContext";
import { RegionProvider } from "./contexts/RegionContext";
import { SSRProvider } from "./contexts/SSRContext";
import PageContentFallback from "./components/layout/PageContentFallback";
import ErrorBoundary from "./components/ErrorBoundary";
import { swrConfig } from "./lib/swr-config";
import { subscribeToRevalidate, revalidateCache } from "./lib/revalidate-cache";
import type { SSRContext } from "./types/ssr";
import FirstVisitRegionModal from "./components/FirstVisitRegionModal";
import { CartToastProvider } from "./contexts/CartToastContext";

interface AppProps {
  ssrContext?: SSRContext;
  Router?: any;
  routerProps?: any;
}

function App({ ssrContext, Router, routerProps = {} }: AppProps = {}) {
  // Ревалидация кэша SWR по событию (например, из другой вкладки или после изменения в API)
  useEffect(() => {
    return subscribeToRevalidate((keys) => revalidateCache(keys));
  }, []);

  if (!Router) {
    // Fallback если Router не передан
    throw new Error('Router component is required')
  }

  const RouterComponent = Router;
  const routerPropsWithBasename = { ...routerProps, basename: __BASE_PATH__ };

  return (
    <I18nextProvider i18n={i18n}>
      <RouterComponent {...routerPropsWithBasename}>
        <ErrorBoundary>
          <SWRConfig value={swrConfig}>
            <SSRProvider data={ssrContext || {}}>
              <AuthProvider initialUser={ssrContext?.user}>
                <CountersProvider initialCounters={ssrContext?.counters}>
                  <RegionProvider initialRegion={ssrContext?.region}>
                    <CartToastProvider>
                      <Suspense fallback={<PageContentFallback />}>
                        <AppRoutes />
                      </Suspense>
                      <FirstVisitRegionModal />
                    </CartToastProvider>
                  </RegionProvider>
                </CountersProvider>
              </AuthProvider>
            </SSRProvider>
          </SWRConfig>
        </ErrorBoundary>
      </RouterComponent>
    </I18nextProvider>
  );
}

export default App;
