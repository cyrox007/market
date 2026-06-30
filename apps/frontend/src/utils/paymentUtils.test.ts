import { describe, expect, it, vi } from 'vitest';
import {
  buildPaymentUrl,
  isOnlineCardPaymentMethod,
  openPaymentInNewTab,
  preparePaymentTab,
} from './paymentUtils';

describe('paymentUtils', () => {
  it('recognizes sberbank online payment', () => {
    expect(isOnlineCardPaymentMethod('card_sberbank')).toBe(true);
    expect(isOnlineCardPaymentMethod('cash')).toBe(false);
  });

  it('builds sberbank payform url', () => {
    expect(
      buildPaymentUrl({
        useEcomApi: true,
        payformUrl: 'https://ecomtest.sberbank.ru/pp/pay_ru?orderId=abc',
      }),
    ).toBe('https://ecomtest.sberbank.ru/pp/pay_ru?orderId=abc');
  });

  it('opens payment in prepared tab', () => {
    const focus = vi.fn();
    const prepared = {
      closed: false,
      location: { replace: vi.fn() },
      focus,
    } as unknown as Window;

    expect(openPaymentInNewTab('https://pay.example/form', prepared)).toBe(true);
    expect(prepared.location.replace).toHaveBeenCalledWith('https://pay.example/form');
    expect(focus).toHaveBeenCalled();
  });

  it('preparePaymentTab calls window.open', () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    preparePaymentTab();
    expect(open).toHaveBeenCalledWith('about:blank', 'mebelvdar_payment');
    open.mockRestore();
  });

  it('builds raiffeisen url with query params', () => {
    const url = buildPaymentUrl(
      { publicId: 'pk', url: 'https://pay.raif.ru/pay' },
      {
        amount: 1000,
        order_number: 'ORD-1',
        success_url: 'https://mebelvdar.ru/ok',
        fail_url: 'https://mebelvdar.ru/fail',
      },
    );
    expect(url).toContain('publicId=pk');
    expect(url).toContain('orderNumber=ORD-1');
  });
});
