import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import CheckoutAddress from '../../../components/CheckoutAddress';
import type { DirectoryNode, DirectorySelection } from '../../../components/CheckoutAddress';
import Modal from '../../../components/ui/primitives/Modal';
import Input from '../../../components/ui/primitives/Input';
import Button from '../../../components/ui/primitives/Button';
import Checkbox from '../../../components/ui/primitives/Checkbox';
import { api } from '../../../lib/api';
import type { Address } from '../../../lib/api';
import { runSessionTask } from '../../../lib/session-queue';

export interface CheckoutAddressModalProps {
  open: boolean;
  authenticated: boolean;
  initialCity?: DirectoryNode | null;
  onClose: () => void;
  onSaved: (address: Address) => void;
}

function AddressForm({
  authenticated,
  initialCity,
  onClose,
  onSaved,
}: Omit<CheckoutAddressModalProps, 'open'>) {
  const [selection, setSelection] = useState<DirectorySelection | null>(null);
  const [title, setTitle] = useState('Дом');
  const [apartment, setApartment] = useState('');
  const [entrance, setEntrance] = useState('');
  const [floor, setFloor] = useState('');
  const [makeDefault, setMakeDefault] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [created, setCreated] = useState<Address | null>(null);
  const busy = useRef(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    event.stopPropagation();
    if (!selection || busy.current) return;
    busy.current = true;
    setSaving(true);
    setError('');
    try {
      let address: Address;
      if (authenticated) {
        address = await runSessionTask(async () => {
          const saved =
            created ??
            (
              await api.addresses.create({
                directory_external_id: selection.house.externalId,
                title: title.trim(),
                apartment: apartment.trim(),
                entrance: entrance.trim(),
                floor: floor.trim(),
              })
            ).address;
          // При ошибке setDefault повторяем только этот шаг, без второго POST /addresses.
          setCreated(saved);
          if (makeDefault && !saved.is_default) {
            await api.addresses.setDefault(saved.id);
            return { ...saved, is_default: true };
          }
          return saved;
        });
      } else {
        address = {
          id: -1,
          is_default: false,
          directory_external_id: selection.house.externalId,
          city: selection.city.name,
          street: selection.street?.name ?? null,
          house: selection.house.name,
          apartment: apartment.trim(),
          entrance: entrance.trim(),
          floor: floor.trim(),
          full_address: [
            selection.city.label || selection.city.name,
            selection.street?.label || selection.street?.name,
            selection.house.label || selection.house.name,
          ]
            .filter(Boolean)
            .join(', '),
        };
      }
      onSaved(address);
      onClose();
    } catch (problem) {
      const data = problem as { message?: string; data?: { message?: string } };
      setError(
        data?.data?.message || data?.message || 'Не удалось сохранить адрес. Попробуйте ещё раз.',
      );
    } finally {
      busy.current = false;
      setSaving(false);
    }
  }

  return (
    <form onSubmit={(event) => void submit(event)} className="space-y-6" noValidate>
      <fieldset disabled={saving || !!created} className="min-w-0 space-y-4">
        {authenticated && (
          <label className="block space-y-2 text-14 text-ink-secondary">
            <span>Название адреса</span>
            <Input
              value={title}
              onChange={(event) => setTitle(event.target.value)}
              inputSize="field"
              shape="field"
              tone="outline"
            />
          </label>
        )}
        <CheckoutAddress
          initialCity={initialCity}
          onChange={(id) => {
            if (!id) setSelection(null);
          }}
          onSelection={setSelection}
        />
        <div className="grid grid-cols-3 gap-3">
          {(
            [
              ['Квартира', apartment, setApartment],
              ['Подъезд', entrance, setEntrance],
              ['Этаж', floor, setFloor],
            ] as const
          ).map(([label, value, update]) => (
            <label key={label} className="block min-w-0 space-y-2 text-14 text-ink-secondary">
              <span>{label}</span>
              <Input
                value={value}
                onChange={(event) => update(event.target.value)}
                inputSize="field"
                shape="field"
                tone="outline"
              />
            </label>
          ))}
        </div>
      </fieldset>
      {authenticated && (
        <Checkbox
          label="Сделать основным адресом"
          checked={makeDefault}
          onChange={setMakeDefault}
          disabled={saving}
        />
      )}
      {error && (
        <p role="alert" className="text-14 text-brand-red">
          {created ? 'Адрес сохранён. ' : ''}
          {error}
        </p>
      )}
      <div className="grid grid-cols-2 gap-3">
        <Button
          type="button"
          size="lg"
          shape="rounded"
          variant="muted"
          onClick={onClose}
          disabled={saving}
        >
          Отмена
        </Button>
        <Button
          type="submit"
          size="lg"
          shape="rounded"
          className="shadow-card max-vsm:px-3 max-vsm:text-14"
          disabled={!selection || saving}
          isLoading={saving}
        >
          Сохранить адрес
        </Button>
      </div>
    </form>
  );
}

export default function CheckoutAddressModal({ open, ...props }: CheckoutAddressModalProps) {
  return (
    <Modal
      open={open}
      onClose={props.onClose}
      title="Добавление адреса"
      mobileQuery="(max-width: 979px)"
      className="md:w-[600px]"
    >
      <AddressForm {...props} />
    </Modal>
  );
}
