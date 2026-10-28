import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { api, type PaymentMethod, type PickupOption, type WarehouseQuote } from '../../lib/api';
import { useCart } from '../../hooks/useCart';
import { useAuth } from '../../hooks/useAuth';
import CheckoutAddress from '../../components/CheckoutAddress';

const field = 'block w-full border border-gray-300 rounded-lg p-3 mt-1';
export default function WarehouseCheckout() {
  const { cart, reloadCart } = useCart();
  const { user } = useAuth();
  const [search] = useSearchParams();
  const navigate = useNavigate();
  const product = Number(search.get('product_id'));
  const bundleId = Number(search.get('bundle_id'));
  const primarySkuId = Number(search.get('primary_sku_id'));
  const quantity = Number(search.get('quantity') || 1);
  const isBundle = Number.isSafeInteger(bundleId) && bundleId > 0;
  const direct = isBundle || (Number.isSafeInteger(product) && product > 0);
  const [prepared, setPrepared] = useState<{ items: Array<{ product_id: number; quantity: number }>; subtotal: number; priced_components?: Array<{ product_id: number; name: string; sku: string; quantity: number; unit_price: number; total: number }> } | null>(null);
  const prepare = () => isBundle ? api.orders.prepareBundle(bundleId, primarySkuId, quantity) : api.orders.prepare(product, quantity);
  const items = useMemo(() => direct ? prepared?.items || [] : (cart?.items || []).map((i) => ({ product_id: i.product_id, quantity: i.quantity })), [direct, prepared, cart]);
  const itemsKey = JSON.stringify(items);
  const [methods, setMethods] = useState<PaymentMethod[]>([]);
  const [payment, setPayment] = useState('');
  const [mode, setMode] = useState<'delivery' | 'pickup'>('delivery');
  const [building, setBuilding] = useState<string | null>(null);
  const [pickup, setPickup] = useState<PickupOption[]>([]);
  const [point, setPoint] = useState('');
  const [quote, setQuote] = useState<WarehouseQuote | null>(null);
  const [warehouse, setWarehouse] = useState<number | null>(null);
  const [assembly, setAssembly] = useState(false);
  const [handling, setHandling] = useState<number | null>(null);
  const [floor, setFloor] = useState(1);
  const [additional, setAdditional] = useState<number[]>([]);
  const [contact, setContact] = useState({ name: user?.name || '', phone: user?.phone || '', email: user?.email || '' });
  const [details, setDetails] = useState({ apartment: '', entrance: '', intercom: '' });
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [calculating, setCalculating] = useState(false);
  const [quoteRevision, setQuoteRevision] = useState(0);
  const [error, setError] = useState('');
  const selected = quote?.selected;
  const services = selected?.service_options;
  const subtotal = direct ? prepared?.subtotal || 0 : cart?.subtotal || 0;
  const total = (quote?.subtotal ?? subtotal) + (mode === 'delivery' ? selected?.total_fulfillment_cost || 0 : 0);
  useEffect(() => {
    let active = true;
    api.paymentMethods.list().then((r) => { if (active) { setMethods(r.data); setPayment(r.data[0]?.code || ''); } }).catch(() => { if (active) setError('Не удалось загрузить способы оплаты.'); });
    return () => { active = false; };
  }, []);
  useEffect(() => {
    if (!direct) return;
    let active = true;
    prepare().then((r) => { if (active) { setPrepared(r); setError(''); } }).catch(() => { if (active) setError('Состав комплекта недоступен или изменился.'); });
    return () => { active = false; };
  }, [direct, product, bundleId, primarySkuId, quantity]);
  useEffect(() => {
    if (!items.length) return;
    let active = true;
    setPickup([]); setPoint('');
    api.shipping.pickupOptions(items).then((r) => { if (active) setPickup(r.pickup); }).catch(() => { if (active) setError('Не удалось проверить точки самовывоза.'); });
    return () => { active = false; };
  }, [itemsKey]);
  useEffect(() => {
    setQuote(null);
    if (mode !== 'delivery' || !building || !items.length) return;
    let active = true;
    setCalculating(true);
    api.shipping.quote({ directory_external_id: building, ...(direct ? { items } : {}), warehouse_id: warehouse,
      requires_assembly: assembly, delivery_handling_type_id: handling, delivery_floor: floor,
      additional_services: additional.map((id) => ({ id })) }).then((r) => {
        if (active) { setQuote(r); setError(r.available ? '' : 'Доставка по выбранному адресу недоступна для всего заказа.'); }
      }).catch((e) => { if (active) setError(e?.data?.message || 'Не удалось рассчитать доставку.'); })
      .finally(() => { if (active) setCalculating(false); });
    return () => { active = false; };
  }, [mode, building, itemsKey, warehouse, assembly, handling, floor, additional, quoteRevision]);
  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (busy || calculating || !consent || !payment || !items.length) return;
    const pickupPoint = pickup.find((p) => `${p.warehouse_id}:${p.store_id || ''}` === point);
    if (mode === 'delivery' && (!selected || !building)) { setError('Выберите дом и дождитесь расчёта доставки.'); return; }
    if (mode === 'pickup' && !pickupPoint) { setError('Выберите доступную точку самовывоза.'); return; }
    setBusy(true); setError('');
    try {
      const response = await api.orders.create({ contact_name: contact.name, contact_phone: contact.phone, contact_email: contact.email,
        payment_method: payment, delivery_type: mode, expected_subtotal: isBundle ? subtotal : (quote?.subtotal ?? subtotal),
        ...(direct ? { items } : {}),
        ...(isBundle ? { bundle_id: bundleId, primary_sku_id: primarySkuId, bundle_quantity: quantity } : {}),
        accept_offer: true, accept_personal_data: true,
        ...(mode === 'delivery' ? { directory_external_id: building, warehouse_id: selected!.warehouse_id,
          delivery_details: details, requires_assembly: assembly, delivery_handling_type_id: handling, delivery_floor: floor,
          additional_services: additional.map((id) => ({ id })) } : { warehouse_id: pickupPoint!.warehouse_id, store_id: pickupPoint!.store_id }),
      });
      if (!response.order) throw new Error('Заказ не получен. Повторите запрос.');
      navigate(`/orders/${response.order.id}${response.order.can_pay ? '?openPayment=1' : ''}`);
      if (!direct) void reloadCart();
    } catch (e) {
      const problem = e as { status?: number; data?: { message?: string; errors?: Record<string, string[]> } };
      if (problem.status === 409) { if (direct) setPrepared(await prepare()); else await reloadCart(); setQuote(null); setWarehouse(null); setQuoteRevision((value) => value + 1); setError('Цена изменилась. Проверьте сумму и подтвердите заказ повторно.'); }
      else setError(Object.values(problem.data?.errors || {}).flat().join(' ') || problem.data?.message || 'Не удалось создать заказ.');
    } finally { setBusy(false); }
  }
  return <main className="max-w-5xl mx-auto px-4 py-8"><h1 className="text-3xl font-bold mb-6">Оформление заказа</h1>
    <form onSubmit={submit} className="space-y-6">
      {error && <p role="alert" className="text-red-600">{error}</p>}
      {!items.length && <p>Нет товаров для оформления. <Link to="/cart">Перейти в корзину</Link></p>}
      {isBundle && prepared?.priced_components && (
        <section className="rounded-2xl border p-6 space-y-3">
          <h2 className="text-xl font-bold">Состав комплекта</h2>
          {prepared.priced_components.map((part) => (
            <div key={part.product_id} className="flex flex-wrap justify-between gap-3 text-sm">
              <span>{part.name} (SKU: {part.sku}) × {part.quantity}</span>
              <strong>{part.total.toLocaleString('ru-RU')} ₽</strong>
            </div>
          ))}
          <p className="font-semibold">Стоимость компонентов: {prepared.subtotal.toLocaleString('ru-RU')} ₽</p>
          <p className="text-xs text-gray-500">Состав и остатки всех SKU проверяются повторно на выбранном складе перед оплатой.</p>
        </section>
      )}
      <section className="border rounded-2xl p-6 space-y-4"><h2 className="text-xl font-bold">Контактные данные</h2>
        {(['name', 'phone', 'email'] as const).map((key) => <label key={key} className="block">{{ name: 'Имя', phone: 'Телефон', email: 'Email' }[key]}<input required type={key === 'email' ? 'email' : key === 'phone' ? 'tel' : 'text'} value={contact[key]} onChange={(e) => setContact({ ...contact, [key]: e.target.value })} className={field} /></label>)}
      </section>
      <section className="border rounded-2xl p-6 space-y-4"><h2 className="text-xl font-bold">Получение заказа</h2>
        <label className="block">Способ получения<select value={mode} onChange={(e) => { setMode(e.target.value as 'delivery' | 'pickup'); setQuote(null); }} className={field}><option value="delivery">Доставка</option><option value="pickup">Самовывоз</option></select></label>
        {mode === 'delivery' ? <><CheckoutAddress onChange={(id) => { setBuilding(id); setWarehouse(null); setHandling(null); setAdditional([]); }} />
          {(['apartment', 'entrance', 'intercom'] as const).map((key) => <label className="block" key={key}>{{ apartment: 'Квартира / помещение', entrance: 'Подъезд', intercom: 'Домофон' }[key]}<input value={details[key]} onChange={(e) => setDetails({ ...details, [key]: e.target.value })} className={field} /></label>)}
          {quote?.options.length ? <label className="block">Склад<select value={selected?.warehouse_id || ''} onChange={(e) => { setWarehouse(Number(e.target.value)); setHandling(null); setAdditional([]); }} className={field}>{quote.options.map((o) => <option key={o.warehouse_id} value={o.warehouse_id}>{o.warehouse_name} — {o.total_fulfillment_cost} ₽</option>)}</select></label> : null}
          {services?.assembly.available && <label className="block"><input type="checkbox" checked={assembly || services.assembly.required} disabled={services.assembly.required} onChange={(e) => setAssembly(e.target.checked)} /> Сборка{services.assembly.required ? ' (обязательная)' : ''} — {services.assembly.price} ₽</label>}
          {!!services?.handling.length && <><label className="block">Подъём / занос<select value={handling || ''} onChange={(e) => setHandling(Number(e.target.value) || null)} className={field}><option value="">Не требуется</option>{services.handling.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}</select></label><label className="block">Этаж<input type="number" min={1} max={20} value={floor} onChange={(e) => setFloor(Number(e.target.value))} className={field} /></label></>}
          {services?.additional.map((s) => <label className="block" key={s.id}><input type="checkbox" checked={additional.includes(s.id)} onChange={(e) => setAdditional(e.target.checked ? [...additional, s.id] : additional.filter((id) => id !== s.id))} /> {s.name} — {s.price} ₽</label>)}
        </> : <label className="block">Точка самовывоза<select required value={point} onChange={(e) => setPoint(e.target.value)} className={field}><option value="">Выберите точку</option>{pickup.map((p) => <option key={`${p.warehouse_id}:${p.store_id}`} value={`${p.warehouse_id}:${p.store_id || ''}`}>{p.name} — {p.address}</option>)}</select>{!pickup.length && <p>Для этих товаров нет доступных точек самовывоза.</p>}</label>}
      </section>
      <section className="border rounded-2xl p-6 space-y-4"><h2 className="text-xl font-bold">Оплата</h2><select required aria-label="Способ оплаты" value={payment} onChange={(e) => setPayment(e.target.value)} className={field}>{methods.map((m) => <option value={m.code} key={m.code}>{m.name}</option>)}</select>
        <p>Товары: {quote?.subtotal ?? subtotal} ₽</p><p>{calculating ? 'Рассчитываем доставку…' : mode === 'delivery' && !selected ? 'Выберите адрес для расчёта полной стоимости.' : `Итого: ${total} ₽`}</p>
        <label className="block"><input required type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} /> Принимаю <Link to="/oferta">оферту</Link> и условия <Link to="/privacy">обработки персональных данных</Link>.</label>
        <button disabled={busy || calculating || !items.length || !payment || !consent || (mode === 'delivery' ? !selected : !point)} className="bg-red-600 text-white rounded-lg px-6 py-3 disabled:opacity-50">{busy ? 'Оформляем…' : 'Оформить заказ'}</button>
        <p>После оформления — 60 минут в обработке у менеджера. В это время заказ можно отменить самостоятельно.</p>
      </section>
    </form>
  </main>;
}
