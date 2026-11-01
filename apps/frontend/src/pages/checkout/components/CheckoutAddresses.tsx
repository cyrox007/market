import { useContext, useEffect, useState } from 'react';
import { MapPin, Plus } from 'lucide-react';
import { api, type Address } from '../../../lib/api';
import { RegionContext } from '../../../contexts/region-context';
import { runSessionRead } from '../../../lib/session-queue';
import CheckoutAddress from '../../../components/CheckoutAddress';
import { Radio, RadioGroup } from '../../../components/ui/primitives/Radio';
import CheckoutAddressModal from './CheckoutAddressModal';
import type { CheckoutState } from '../hooks/useWarehouseCheckout';

export default function CheckoutAddresses({ checkout: c }: { checkout: CheckoutState }) {
  const region = useContext(RegionContext);
  const [addresses, setAddresses] = useState<Address[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [open, setOpen] = useState(false);
  const userId = c.user?.id;
  const selectAddress = c.selectAddress;
  useEffect(() => {
    if (!userId) return;
    let active = true;
    setLoading(true);
    runSessionRead(() => api.addresses.list())
      .then(({ data }) => {
        if (!active) return;
        setAddresses(data);
        const initial = data.find((address) => address.is_default) || data[0];
        if (initial) selectAddress(initial);
      })
      .catch(() => {
        if (active) setError('Не удалось загрузить адреса. Можно добавить новый.');
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
    // Выбор пользователя после загрузки не должен заново переключать основной адрес.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);
  if (!c.user)
    return <CheckoutAddress initialCity={region?.locality} onChange={c.selectBuilding} />;
  return (
    <div className="space-y-3">
      {loading && (
        <p role="status" className="text-14 text-ink-secondary">
          Загружаем адреса…
        </p>
      )}
      {error && (
        <p role="alert" className="text-14 text-brand-red">
          {error}
        </p>
      )}
      <RadioGroup
        label="Адрес доставки"
        value={String(c.addressId ?? '')}
        onChange={(value) => {
          const address = addresses.find((item) => item.id === Number(value));
          if (address) c.selectAddress(address);
        }}
        className="gap-3 max-md:grid max-md:grid-cols-2 max-vsm:!flex max-vsm:!flex-row max-vsm:overflow-x-auto max-vsm:pb-1"
      >
        {addresses.map((address) => (
          <Radio
            key={address.id}
            value={String(address.id)}
            className={`items-start border p-5 max-vsm:min-w-[280px] max-vsm:max-w-[90%] max-vsm:shrink-0 ${address.id === c.addressId ? 'border-brand-yellow !bg-surface' : 'border-surface-border !bg-surface'}`}
            label={
              <span className="block space-y-3">
                <span className="flex flex-wrap items-center gap-2">
                  <MapPin aria-hidden="true" className="size-5" />
                  <span className="text-16 font-semibold">{address.title || 'Адрес доставки'}</span>
                  {address.is_default && (
                    <span className="rounded-pill bg-brand-yellow px-2 py-1 text-12 font-medium">
                      Основной
                    </span>
                  )}
                </span>
                <span className="block break-words text-14 font-medium">
                  {address.full_address ||
                    [address.city, address.street, address.house].filter(Boolean).join(', ')}
                </span>
                {(address.entrance || address.floor) && (
                  <span className="block text-12 font-normal text-ink-secondary">
                    {[
                      address.entrance && `Подъезд ${address.entrance}`,
                      address.floor && `этаж ${address.floor}`,
                    ]
                      .filter(Boolean)
                      .join(', ')}
                  </span>
                )}
              </span>
            }
          />
        ))}
      </RadioGroup>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="inline-flex items-center gap-2 text-14 font-medium hover:text-brand-green"
      >
        <Plus aria-hidden="true" className="size-5" />
        Добавить новый адрес
      </button>
      <CheckoutAddressModal
        open={open}
        authenticated
        initialCity={region?.locality}
        onClose={() => setOpen(false)}
        onSaved={(address) => {
          setAddresses((previous) => [
            ...previous.map((item) => ({
              ...item,
              is_default: address.is_default ? false : item.is_default,
            })),
            address,
          ]);
          c.selectAddress(address);
        }}
      />
    </div>
  );
}
