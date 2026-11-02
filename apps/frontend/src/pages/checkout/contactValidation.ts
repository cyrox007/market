import type { CheckoutContact } from './components/CheckoutContacts';

/** Те же формы российского номера, которые нормализует серверный PhoneNumber. */
export function normalizeCheckoutPhone(value: string) {
  const trimmed = value.trim();
  if (!/^\+?[\d\s()-]+$/.test(trimmed)) return trimmed;
  const digits = trimmed.replace(/\D/g, '');
  if (!trimmed.startsWith('+') && digits.length === 10) return `+7${digits}`;
  if (digits.length === 11 && /^[78]/.test(digits)) return `+7${digits.slice(1)}`;
  return trimmed;
}

export function formatCheckoutPhoneInput(value: string, deleting: boolean) {
  const filtered = value.replace(/[^\d+()\s-]/g, '');
  const digits = filtered.replace(/\D/g, '');
  // Не восстанавливаем префикс при удалении и не трогаем уже введённый +7.
  if (deleting || filtered.startsWith('+') || digits.length < 2) return filtered;
  // При вставке готовых 10 цифр все они относятся к самому номеру.
  if (digits.length === 10) return `+7${digits}`;
  return `+7${/^[78]/.test(digits) ? digits.slice(1) : digits}`;
}

export function checkoutContactErrors(value: CheckoutContact) {
  const errors: Partial<Record<keyof CheckoutContact, string>> = {};
  if (!value.lastName.trim()) errors.lastName = 'Введите фамилию.';
  if (!value.firstName.trim()) errors.firstName = 'Введите имя.';
  if (!/^\+7\d{10}$/.test(normalizeCheckoutPhone(value.phone))) {
    errors.phone = 'Введите российский номер телефона: 10 цифр или 11 с 7/8.';
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.email.trim()) || value.email.trim().length > 255) {
    errors.email = 'Введите почту в формате name@example.ru.';
  }
  return errors;
}
