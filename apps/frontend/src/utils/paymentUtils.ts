/** Способы оплаты с онлайн-эквайрингом (редирект на форму банка) */
export const ONLINE_CARD_PAYMENT_METHODS = [
  'card_in_store',
  'card_ecom',
  'card_sberbank',
] as const;

export type OnlineCardPaymentMethod = (typeof ONLINE_CARD_PAYMENT_METHODS)[number];

export type GatewayClientConfig =
  | { publicId: string; url: string; useSdk?: boolean }
  | { payformUrl: string; useEcomApi?: true };

/** Имя целевого окна — повторное открытие переиспользует ту же вкладку оплаты */
const PAYMENT_TAB_TARGET = 'mebelvdar_payment';

const PAYMENT_TAB_LOADING_HTML = `<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <title>Переход к оплате</title>
  <style>
    body { font-family: system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; background: #f9fafb; color: #111; }
    .box { text-align: center; padding: 2rem; }
    .spinner { width: 40px; height: 40px; border: 3px solid #e5e7eb; border-top-color: #dc2626; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 1rem; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <div class="box">
    <div class="spinner"></div>
    <p>Загрузка формы оплаты…</p>
  </div>
</body>
</html>`;

export function isOnlineCardPaymentMethod(
  code: string | undefined | null,
): code is OnlineCardPaymentMethod {
  return (
    code != null &&
    (ONLINE_CARD_PAYMENT_METHODS as readonly string[]).includes(code)
  );
}

export function buildPaymentUrl(
  gw: GatewayClientConfig,
  raiffeisen?: {
    amount: number;
    order_number: string;
    success_url: string;
    fail_url: string;
  },
): string {
  if ('payformUrl' in gw && gw.payformUrl) {
    return gw.payformUrl;
  }

  if (!raiffeisen) {
    throw new Error('Для оплаты через Райффайзен нужны параметры заказа');
  }

  const params = new URLSearchParams({
    publicId: gw.publicId,
    orderNumber: raiffeisen.order_number,
    amount: String(raiffeisen.amount),
    successUrl: raiffeisen.success_url,
    failUrl: raiffeisen.fail_url,
  });

  return `${gw.url}?${params.toString()}`;
}

function writePaymentTabHtml(tab: Window, html: string): void {
  tab.document.open();
  tab.document.write(html);
  tab.document.close();
}

/**
 * Открыть вкладку синхронно по клику (до await) с экраном загрузки вместо about:blank.
 */
export function preparePaymentTab(): Window | null {
  try {
    const tab = window.open('about:blank', PAYMENT_TAB_TARGET);
    if (tab) {
      writePaymentTabHtml(tab, PAYMENT_TAB_LOADING_HTML);
    }
    return tab;
  } catch {
    return null;
  }
}

export function closePaymentTab(tab: Window | null | undefined): void {
  try {
    if (tab && !tab.closed) {
      tab.close();
    }
  } catch {
    // ignore
  }
}

export function showPaymentTabError(tab: Window | null | undefined, message: string): void {
  if (!tab || tab.closed) {
    return;
  }
  try {
    const safeMessage = message.replace(/</g, '&lt;');
    writePaymentTabHtml(
      tab,
      `<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Оплата</title></head>
<body style="font-family:system-ui,sans-serif;padding:2rem;max-width:32rem;margin:auto">
<h2 style="color:#dc2626">Не удалось открыть оплату</h2>
<p>${safeMessage}</p>
<p>Закройте эту вкладку и нажмите «Оплатить заказ» ещё раз на сайте.</p>
</body></html>`,
    );
  } catch {
    closePaymentTab(tab);
  }
}

/**
 * Открывает форму оплаты в новой вкладке и переводит фокус на неё.
 * @returns true, если вкладка открыта
 */
export function openPaymentInNewTab(url: string, preparedTab?: Window | null): boolean {
  if (preparedTab && !preparedTab.closed) {
    try {
      preparedTab.location.replace(url);
      preparedTab.focus();
      return true;
    } catch {
      // fallback ниже
    }
  }

  const tab = window.open(url, PAYMENT_TAB_TARGET);
  if (tab) {
    tab.focus();
    return true;
  }

  return false;
}
