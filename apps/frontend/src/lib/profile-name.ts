import type { ProfileUpdateData, User } from './api';

export interface ProfileFormValues {
  name: string;
  first_name: string;
  last_name: string;
  patronymic: string;
  phone: string;
}

export function initialProfileForm(user: User): ProfileFormValues {
  return {
    name: user.name ?? '',
    first_name: user.first_name ?? '',
    last_name: user.last_name ?? '',
    patronymic: user.patronymic ?? '',
    phone: user.phone ?? '',
  };
}

/**
 * Existing full names are never parsed into components without user confirmation.
 */
export function buildProfileUpdate(form: ProfileFormValues, hasStructuredName: boolean): ProfileUpdateData {
  const first = form.first_name.trim();
  const last = form.last_name.trim();
  const patronymic = form.patronymic.trim();
  const fullName = form.name.trim();
  const phone = form.phone.trim();
  const usingParts = hasStructuredName || Boolean(first || last || patronymic);

  if (usingParts && (!first || !last)) {
    throw new Error('Для сохранения укажите имя и фамилию отдельно.');
  }
  if (!usingParts && !fullName) {
    throw new Error('Укажите полное имя.');
  }
  if (phone && phone.replace(/\D/g, '').length !== 11) {
    throw new Error('Укажите корректный номер телефона');
  }

  const payload: ProfileUpdateData = usingParts
    ? { first_name: first, last_name: last, patronymic: patronymic || null }
    : { name: fullName };

  if (phone) payload.phone = phone;
  return payload;
}
