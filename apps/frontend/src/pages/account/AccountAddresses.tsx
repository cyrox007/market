import { useEffect, useRef, useState } from 'react';
import { api, type Address } from '../../lib/api';
import { runSessionTask } from '../../lib/session-queue';
import { plural } from '../../lib/plural';
import { AccountDataError } from './AccountDataError';
import pin from './assets/address-pin.svg';
import edit from './assets/address-edit.svg';
import trash from './assets/address-trash.svg';
import check from './assets/address-check.svg';
import plus from './assets/address-plus.svg';
import AccountAddressEditor from './AccountAddressEditor';
import Sheet from '../../components/ui/primitives/Sheet';
import Button from '../../components/ui/primitives/Button';
import { useMediaMatch } from '../../hooks/useMediaQuery';
import closeIcon from './assets/address-x.svg';

interface Props {
  addresses: Address[];
  isLoading: boolean;
  error?: unknown;
  onReload: () => Promise<unknown>;
}
function addressLines(address: Address) {
  const base =
    address.address_snapshot?.label ||
    [
      address.city,
      address.street && `ул. ${address.street}`,
      address.house && `д. ${address.house}`,
    ]
      .filter(Boolean)
      .join(', ');
  return {
    main: base
      ? [
          base,
          address.apartment && `кв. ${address.apartment}`,
          address.room && `комн. ${address.room}`,
        ]
          .filter(Boolean)
          .join(', ')
      : address.full_address || 'Адрес уточняется',
    details: [
      base && address.entrance && `Подъезд ${address.entrance}`,
      address.floor && `этаж ${address.floor}`,
    ]
      .filter(Boolean)
      .join(', '),
  };
}
export default function AccountAddresses({ addresses, isLoading, error, onReload }: Props) {
  const [pending, setPending] = useState(false);
  const busy = useRef(false);
  const [problem, setProblem] = useState('');
  const [editor, setEditor] = useState<{ address: Address | null; instance: number } | null>(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const editorInstance = useRef(0);
  const [deleting, setDeleting] = useState<Address | null>(null);
  const [deleteError, setDeleteError] = useState('');
  const deletionSaved = useRef(false);
  const [deleted, setDeleted] = useState(0);
  const heading = useRef<HTMLHeadingElement>(null);
  const narrow = useMediaMatch('(max-width: 979px)');
  const tablet = useMediaMatch('(min-width: 550px)');
  function openEditor(address: Address | null) {
    setEditor({ address, instance: ++editorInstance.current });
    setEditorOpen(true);
  }
  useEffect(() => {
    if (deleted) heading.current?.focus();
  }, [deleted]);
  async function remove() {
    if (!deleting || busy.current) return;
    busy.current = true;
    setPending(true);
    setDeleteError('');
    try {
      if (!deletionSaved.current) {
        await runSessionTask(() => api.addresses.delete(deleting.id));
        deletionSaved.current = true;
      }
      await onReload();
      setDeleting(null);
      setDeleted((n) => n + 1);
    } catch {
      setDeleteError(
        deletionSaved.current
          ? 'Адрес удалён, но не удалось обновить список. Повторите обновление.'
          : 'Не удалось удалить адрес. Попробуйте ещё раз.',
      );
    } finally {
      busy.current = false;
      setPending(false);
    }
  }
  async function makeDefault(id: number) {
    if (busy.current) return;
    busy.current = true;
    setPending(true);
    setProblem('');
    try {
      await runSessionTask(() => api.addresses.setDefault(id));
      await onReload();
    } catch {
      setProblem('Не удалось назначить основной адрес. Попробуйте ещё раз.');
    } finally {
      busy.current = false;
      setPending(false);
    }
  }
  return (
    <section aria-label="Адресная книга" className="min-w-0 text-ink">
      <div className="mb-6 flex flex-wrap items-baseline justify-between gap-3 max-vsm:mb-4">
        <div className="flex flex-wrap items-baseline gap-3">
          <h1
            ref={heading}
            tabIndex={-1}
            className="font-display text-36 font-bold tracking-[-0.02em] max-vsm:text-24"
          >
            Адреса доставки
          </h1>
          {(!isLoading || addresses.length > 0) && !error && (
            <span className="text-16 text-ink-secondary max-vsm:text-14">
              {addresses.length} {plural(addresses.length, ['адрес', 'адреса', 'адресов'])}
            </span>
          )}
        </div>
        <button
          type="button"
          onClick={() => openEditor(null)}
          className="inline-flex items-center gap-2 text-14 font-medium text-ink-secondary max-vsm:w-full"
        >
          <img src={plus} alt="" width={24} height={24} />
          Добавить новый адрес
        </button>
      </div>
      {Boolean(error) && (
        <AccountDataError label="Не удалось загрузить адреса" onRetry={onReload} />
      )}
      {problem && (
        <p role="alert" className="mb-4 rounded-btn bg-brand-red/10 p-4 text-14">
          {problem}
        </p>
      )}
      {isLoading && !addresses.length ? (
        <div
          role="status"
          aria-label="Загрузка адресов"
          className="grid grid-cols-2 gap-6 max-vsm:grid-cols-1"
        >
          {[1, 2].map((n) => (
            <div
              key={n}
              className="h-[205px] animate-pulse rounded-popover bg-surface-grey motion-reduce:animate-none"
            />
          ))}
        </div>
      ) : !error && !addresses.length ? (
        <p className="rounded-popover bg-surface-grey p-7 text-ink-secondary">
          У вас пока нет адресов доставки
        </p>
      ) : null}
      <div className="grid grid-cols-2 gap-6 max-vsm:grid-cols-1 max-vsm:gap-4">
        {addresses.map((address) => (
          <article
            key={address.id}
            aria-label={address.title || 'Адрес доставки'}
            className={`flex min-h-[205px] min-w-0 flex-col justify-between gap-4 rounded-popover border bg-surface py-5 pl-6 pr-5 ${address.is_default ? 'border-brand-yellow' : 'border-surface-border'}`}
          >
            <div>
              <div className="mb-4 flex items-center gap-3">
                <span className="flex size-5 shrink-0 items-center justify-center">
                  <img src={pin} alt="" width={24} height={24} className="max-w-none" />
                </span>
                <h2 className="min-w-0 flex-1 text-16 font-semibold [overflow-wrap:anywhere]">
                  {address.title || 'Адрес доставки'}
                </h2>
                {address.is_default && (
                  <span className="rounded-pill bg-brand-yellow px-2 py-1 text-12 font-medium">
                    Основной
                  </span>
                )}
                <div className="flex shrink-0">
                  <button
                    type="button"
                    onClick={() => openEditor(address)}
                    aria-label={`Редактировать адрес ${address.title || address.id}`}
                    className="flex size-9 items-center justify-center rounded-full focus-visible:outline-brand-green"
                  >
                    <img src={edit} alt="" width={16} height={16} />
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      deletionSaved.current = false;
                      setDeleting(address);
                      setDeleteError('');
                    }}
                    aria-label={`Удалить адрес ${address.title || address.id}`}
                    disabled={pending}
                    className="flex size-9 items-center justify-center rounded-full focus-visible:outline-brand-green disabled:opacity-50"
                  >
                    <img src={trash} alt="" width={16} height={16} />
                  </button>
                </div>
              </div>
              <p className="text-16 font-medium [overflow-wrap:anywhere]">
                {addressLines(address).main}
              </p>
              <p className="mt-1.5 text-14 text-ink-secondary [overflow-wrap:anywhere]">
                {addressLines(address).details}
              </p>
            </div>
            <div className="border-t border-surface-border pt-4 text-14">
              {address.is_default ? (
                <p className="flex items-start gap-2 text-ink-secondary">
                  <img src={check} alt="" width={16} height={16} className="shrink-0" />
                  Подставляется при оформлении заказа
                </p>
              ) : (
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => void makeDefault(address.id)}
                  className="text-left font-medium focus-visible:outline-brand-green disabled:opacity-50"
                >
                  Сделать основным
                </button>
              )}
            </div>
          </article>
        ))}
      </div>
      {editor && (
        <AccountAddressEditor
          key={editor.instance}
          open={editorOpen}
          narrow={Boolean(narrow)}
          contentPadding={!narrow || tablet ? 24 : 16}
          address={editor.address}
          onClose={() => setEditorOpen(false)}
          onSaved={onReload}
        />
      )}
      {deleting && (
        <Sheet
          open
          side={narrow ? 'bottom' : 'center'}
          title="Удалить адрес?"
          onClose={() => {
            if (!busy.current) setDeleting(null);
          }}
          headerAction={
            <button
              type="button"
              aria-label="Закрыть"
              disabled={pending}
              onClick={() => setDeleting(null)}
              className="p-1"
            >
              <img src={closeIcon} alt="" width={24} height={24} />
            </button>
          }
        >
          <p className="mb-6 text-14 [overflow-wrap:anywhere]">
            Удалить адрес «{deleting.title || deleting.full_address || 'Адрес доставки'}»?
          </p>
          {deleteError && (
            <p role="alert" className="mb-4 rounded-btn bg-brand-red/10 p-3 text-14">
              {deleteError}
            </p>
          )}
          <div className="grid grid-cols-2 gap-3">
            <Button
              type="button"
              variant="outline"
              shape="rounded"
              disabled={pending}
              onClick={() => setDeleting(null)}
            >
              Отмена
            </Button>
            <Button type="button" shape="rounded" disabled={pending} onClick={() => void remove()}>
              {pending ? 'Обработка…' : deletionSaved.current ? 'Обновить список' : 'Удалить'}
            </Button>
          </div>
        </Sheet>
      )}
    </section>
  );
}
