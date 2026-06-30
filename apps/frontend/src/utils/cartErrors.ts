/** Сообщение об ошибке из ответа API корзины */
export function getCartErrorMessage(error: unknown): string {
  const err = error as { data?: { message?: string }; message?: string; status?: number };
  const apiMessage = err?.data?.message;
  if (typeof apiMessage === 'string' && apiMessage.trim()) {
    return apiMessage;
  }
  const raw = err?.message ?? '';
  if (raw.includes('variation_attributes')) {
    return 'Выберите параметры товара на странице товара';
  }
  if (raw.includes('422') || raw.includes('недоступен')) {
    return 'Товар недоступен для заказа в вашем регионе';
  }
  if (raw.includes('404')) {
    return 'Выбранная комбинация недоступна';
  }
  if (err?.status === 0 || raw.includes('Failed to fetch')) {
    return 'Нет связи с сервером. Проверьте интернет и попробуйте снова';
  }
  return 'Не удалось добавить товар в корзину';
}
