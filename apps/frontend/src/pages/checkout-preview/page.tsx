import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { MapPin } from 'lucide-react';
import { PAGE_CONTAINER } from '../../lib/layout';
import { Button, Checkbox, Modal, Radio, RadioGroup } from '../../components/ui/primitives';
import CheckoutServices from '../checkout/components/CheckoutServices';
import { CheckoutContacts, type CheckoutContact } from '../checkout/components/CheckoutContacts';
import CheckoutSummary, { type CheckoutSummaryState } from '../checkout/components/CheckoutSummary';
import CheckoutPayment from '../checkout/components/CheckoutPayment';
import CheckoutSelect from '../checkout/components/CheckoutSelect';
import CheckoutPickupNotice from '../checkout/components/CheckoutPickupNotice';
import { checkoutContactErrors } from '../checkout/contactValidation';
import { previewCart, previewPaymentMethods, previewDeliveryDays } from './fixtures';
import DeliverySchedule, { type DeliverySelection } from './DeliverySchedule';

export default function CheckoutPreview() {
  const [mode, setMode] = useState<'delivery' | 'pickup'>('delivery');
  const [address, setAddress] = useState('Дача');
  const [payment, setPayment] = useState('card_in_store');
  const [contact, setContact] = useState<CheckoutContact>({
    lastName: 'Иванов',
    firstName: 'Иван',
    middleName: '',
    phone: '+79991234567',
    email: 'demo@example.test',
  });
  const [attempted, setAttempted] = useState(false);
  const [completed, setCompleted] = useState(false);
  const [delivery, setDelivery] = useState<DeliverySelection>({
    date: '2026-11-03',
    slotId: 'afternoon',
  });
  const [assembly, setAssembly] = useState(false);
  const [required, setRequired] = useState(false);
  const [configured, setConfigured] = useState(true);
  const [handling, setHandling] = useState<number | null>(null);
  const [floor, setFloor] = useState(5);
  const [additional, setAdditional] = useState<number[]>([]);
  const assemblyCost = configured && (assembly || required) ? 3000 : 0;
  const servicesCost = configured
    ? assemblyCost +
      (handling ? (handling === 1 ? 1000 : floor * 300) : 0) +
      (additional.includes(2) ? 500 : 0)
    : 0;
  const fulfillment = mode === 'delivery' ? 2000 + servicesCost : 0;
  const summary: CheckoutSummaryState = {
    cart: previewCart,
    direct: false,
    prepared: null,
    directProduct: null,
    items: [],
    subtotal: previewCart.subtotal,
    total: previewCart.subtotal + fulfillment,
    fulfillment,
    mode,
    calculating: false,
    selected: {
      warehouse_id: 1,
      warehouse_name: 'Демо-склад',
      delivery_cost: 2000,
      assembly_cost: assemblyCost,
      total_fulfillment_cost: fulfillment,
    },
    hasUnavailable: false,
    busy: false,
    canSubmit: true,
  };
  function submit(event: FormEvent) {
    event.preventDefault();
    setAttempted(true);
    if (Object.keys(checkoutContactErrors(contact)).length) return;
    setCompleted(true);
  }
  return (
    <main className={`${PAGE_CONTAINER} py-6 pb-16 text-ink`}>
      <div className="mb-6 space-y-2 rounded-card bg-brand-yellow/15 p-4 text-14">
        <h1 className="font-semibold">Демонстрация оформления — только локально</h1>
        <p>
          Товары, адреса, цены и даты здесь тестовые. Кнопка не создаёт заказ и не запускает оплату.
        </p>
        <Link to="/checkout" className="underline">
          Вернуться к рабочему оформлению
        </Link>
        <div className="flex flex-wrap gap-4">
          <Checkbox label="Услуги настроены (демо)" checked={configured} onChange={setConfigured} />
          <Checkbox label="Обязательная сборка (демо)" checked={required} onChange={setRequired} />
        </div>
      </div>
      <form
        onSubmit={submit}
        className="grid grid-cols-[minmax(0,480px)_minmax(0,1fr)] items-start gap-11 max-md:grid-cols-1"
      >
        <div className="min-w-0 space-y-8">
          <CheckoutContacts value={contact} onChange={setContact} showErrors={attempted} />
          <section className="space-y-4" aria-labelledby="preview-delivery">
            <div className="flex min-h-9 items-center justify-between gap-3">
              <h2 id="preview-delivery" className="text-20 font-semibold max-vsm:text-16">
                Способ доставки
              </h2>
              <span className="rounded-pill bg-brand-green px-3 py-2 text-14 text-white">
                {mode === 'delivery' ? '+ 2 000 ₽' : 'Бесплатно'}
              </span>
            </div>
            <RadioGroup
              label="Способ получения"
              value={mode}
              onChange={(value) => setMode(value as typeof mode)}
              className="!flex-row gap-6"
            >
              <Radio
                value="delivery"
                label="Доставка"
                className="!flex-row-reverse !bg-transparent !p-0 !text-14"
              />
              <Radio
                value="pickup"
                label="Самовывоз"
                className="!flex-row-reverse !bg-transparent !p-0 !text-14"
              />
            </RadioGroup>
            {mode === 'delivery' ? (
              <>
                <RadioGroup
                  label="Адрес доставки"
                  value={address}
                  onChange={setAddress}
                  className="scroll-hidden gap-3 max-md:grid max-md:grid-cols-2 max-vsm:!flex max-vsm:!flex-row max-vsm:overflow-x-auto max-vsm:overscroll-x-contain"
                >
                  {['Дом', 'Дача'].map((title) => (
                    <Radio
                      key={title}
                      value={title}
                      className={`!items-start border p-5 !bg-surface max-vsm:min-w-[280px] max-vsm:max-w-[90%] max-vsm:shrink-0 ${address === title ? 'border-brand-yellow' : 'border-surface-border'}`}
                      label={
                        <span className="block space-y-3">
                          <span className="flex items-center gap-2 text-16 font-semibold">
                            <MapPin aria-hidden="true" className="size-5" />
                            {title}
                            {title === 'Дом' && (
                              <span className="rounded-pill bg-brand-yellow px-2 py-1 text-12 font-medium">
                                Основной
                              </span>
                            )}
                          </span>
                          <span className="block text-14 font-medium">
                            г. Липецк, ул. Советская, д. 64, кв. 17
                          </span>
                          <span className="block text-12 font-normal text-ink-secondary">
                            Подъезд 2, этаж 5, индекс 398001
                          </span>
                        </span>
                      }
                    />
                  ))}
                </RadioGroup>
                <DeliverySchedule
                  days={previewDeliveryDays}
                  value={delivery}
                  onChange={setDelivery}
                />
                <CheckoutServices
                  services={{
                    assembly: {
                      available: configured,
                      required: configured && required,
                      price: 3000,
                    },
                    handling: configured
                      ? [
                          {
                            id: 1,
                            name: 'На лифте — 1 000 ₽',
                            requires_floor: true,
                            max_floor: 30,
                          },
                          {
                            id: 2,
                            name: 'По лестнице — 300 ₽ / этаж',
                            requires_floor: true,
                            max_floor: 20,
                          },
                        ]
                      : [],
                    additional: configured
                      ? [
                          { id: 1, name: 'Вывоз упаковки', price: 0 },
                          { id: 2, name: 'Вынос старой мебели', price: 500 },
                        ]
                      : [],
                  }}
                  assembly={assembly}
                  setAssembly={setAssembly}
                  handling={handling}
                  setHandling={setHandling}
                  floor={floor}
                  setFloor={setFloor}
                  additional={additional}
                  setAdditional={setAdditional}
                />
              </>
            ) : (
              <div className="space-y-4">
                <CheckoutSelect
                  label="Точка самовывоза"
                  value="demo"
                  onChange={() => {}}
                  options={[
                    { value: 'demo', label: 'Демо-магазин — г. Липецк, ул. Советская, 64' },
                  ]}
                />
                <CheckoutPickupNotice
                  point={{
                    ready: { days_min: 0, days_max: 6, label: 'с 02.11.26 по 08.11.26' },
                    address: null,
                  }}
                />
              </div>
            )}
          </section>
          <section className="space-y-4">
            <h2 className="text-20 font-semibold max-vsm:text-16">
              <label htmlFor="preview-comment">Комментарий к заказу</label>
            </h2>
            <textarea
              id="preview-comment"
              placeholder="Комментарий"
              rows={4}
              className="block min-h-[114px] w-full rounded-btn border border-surface-border p-5 text-14 outline-none focus:border-ink-secondary"
            />
          </section>
          <CheckoutPayment checkout={{ methods: previewPaymentMethods, payment, setPayment }} />
        </div>
        <CheckoutSummary checkout={summary} />
      </form>
      <Modal open={completed} onClose={() => setCompleted(false)} title="Демонстрация оформления">
        <p className="mb-6 text-16">Заказ не создан. Деньги не списывались.</p>
        <Button fullWidth onClick={() => setCompleted(false)}>
          Продолжить проверку
        </Button>
      </Modal>
    </main>
  );
}
