import { describe, expect, it } from 'vitest';
import { buildProfileUpdate, initialProfileForm, type ProfileFormValues } from './profile-name';

const legacy: ProfileFormValues = {
  name: 'Анна Мария де ла Крус',
  first_name: '',
  last_name: '',
  patronymic: '',
  phone: '',
};

describe('structured profile names', () => {
  it('does not guess or split historical composite names', () => {
    expect(initialProfileForm({ id: 1, name: legacy.name, email: 'a@example.com' }))
      .toEqual(legacy);
    expect(buildProfileUpdate(legacy, false)).toEqual({ name: legacy.name });
  });

  it('migrates only after both parts were explicitly entered', () => {
    expect(buildProfileUpdate({
      ...legacy, first_name: 'Анна Мария', last_name: 'де ла Крус',
    }, false)).toEqual({
      first_name: 'Анна Мария', last_name: 'де ла Крус', patronymic: null,
    });
  });

  it('allows clearing a patronymic in an existing structured account', () => {
    expect(buildProfileUpdate({
      ...legacy, first_name: 'Иван', last_name: 'Петров',
    }, true)).toEqual({
      first_name: 'Иван', last_name: 'Петров', patronymic: null,
    });
  });

  it('refuses incomplete structured edits', () => {
    expect(() => buildProfileUpdate({ ...legacy, first_name: 'Иван' }, false))
      .toThrow('имя и фамилию');
    expect(() => buildProfileUpdate(legacy, true)).toThrow('имя и фамилию');
  });

  it('keeps legacy name intact when only changing phone', () => {
    expect(buildProfileUpdate({ ...legacy, phone: '+7 (999) 123-45-67' }, false))
      .toEqual({ name: legacy.name, phone: '+7 (999) 123-45-67' });
  });
});
