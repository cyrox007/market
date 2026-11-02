import { isOnlineCardPaymentMethod } from '../../../utils/paymentUtils';
import { useCallback, useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import {
  api,
  type Address,
  type PaymentMethod,
  type PickupOption,
  type ProductDetail,
  type WarehouseQuote,
} from '../../../lib/api';
import { useCart } from '../../../hooks/useCart';
import { useAuth } from '../../../hooks/useAuth';
import { runSessionRead, runSessionTask } from '../../../lib/session-queue';
import { cartPanelTotals } from '../../../components/cart/cartPanelModel';
import type { CheckoutContact } from '../components/CheckoutContacts';
import { checkoutContactErrors, normalizeCheckoutPhone } from '../contactValidation';

export const selectable = (method?: PaymentMethod) =>
  !!method && method.is_active !== false && !method.is_coming_soon && method.selectable !== false;
type Prepared = {
  items: Array<{ product_id: number; quantity: number }>;
  subtotal: number;
  unit_price?: number;
  priced_components?: Array<{
    product_id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price: number;
    total: number;
  }>;
};
const contactFrom = (name: string, phone: string, email: string): CheckoutContact => {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  return {
    lastName: parts.length > 1 ? parts[0] : '',
    firstName: parts.length > 1 ? parts[1] : parts[0] || '',
    middleName: parts.slice(2).join(' '),
    phone,
    email,
  };
};

export function useWarehouseCheckout() {
  const { cart, reloadCart, isLoading } = useCart();
  const { user } = useAuth();
  const [search] = useSearchParams();
  const navigate = useNavigate();
  const location = useLocation();
  const product = Number(search.get('product_id'));
  const bundleId = Number(search.get('bundle_id'));
  const primarySkuId = Number(search.get('primary_sku_id'));
  const quantity = Number(search.get('quantity') || 1);
  const isBundle = Number.isSafeInteger(bundleId) && bundleId > 0;
  const direct = isBundle || (Number.isSafeInteger(product) && product > 0);
  const directKey = `${product}:${bundleId}:${primarySkuId}:${quantity}:${search.get('offer') || ''}`;
  const [preparation, setPreparation] = useState<{ key: string; data: Prepared } | null>(null);
  const prepared = preparation?.key === directKey ? preparation.data : null;
  const setPrepared = (data: Prepared) => setPreparation({ key: directKey, data });
  const prepare = useCallback(
    () =>
      isBundle
        ? api.orders.prepareBundle(bundleId, primarySkuId, quantity)
        : api.orders.prepare(product, quantity),
    [isBundle, bundleId, primarySkuId, product, quantity],
  );
  const [directProduct, setDirectProduct] = useState<ProductDetail | null>(null);
  const items = useMemo(
    () =>
      direct
        ? prepared?.items || []
        : (cart?.items || []).map((item) => ({
            product_id: item.product_id,
            quantity: item.quantity,
          })),
    [direct, prepared, cart],
  );
  const itemsKey = JSON.stringify(items);
  const [methods, setMethods] = useState<PaymentMethod[]>([]);
  const [payment, setPayment] = useState('');
  const [mode, setMode] = useState<'delivery' | 'pickup'>('delivery');
  const [building, setBuilding] = useState<string | null>(null);
  const [addressId, setAddressId] = useState<number | null>(null);
  const [points, setPoints] = useState<{ key: string; data: PickupOption[] } | null>(null);
  const pickup = points?.key === itemsKey ? points.data : [];
  const [point, setPoint] = useState('');
  const pickupPoint = pickup.find((p) => `${p.warehouse_id}:${p.store_id || ''}` === point);
  const [warehouse, setWarehouse] = useState<number | null>(null);
  const [assembly, setAssembly] = useState(false);
  const [handling, setHandling] = useState<number | null>(null);
  const [floor, setFloor] = useState(1);
  const [additional, setAdditional] = useState<number[]>([]);
  const [details, setDetails] = useState({ apartment: '', entrance: '', intercom: '' });
  const oneClick = (
    location.state as { oneClick?: { name?: string; phone?: string; comment?: string } } | null
  )?.oneClick;
  const [contact, setContact] = useState(() =>
    contactFrom(
      oneClick?.name || user?.name || '',
      oneClick?.phone || user?.phone || '',
      user?.email || '',
    ),
  );
  const editedContact = useRef(false);
  const [contactValidationAttempted, setContactValidationAttempted] = useState(false);
  const updateContact = (value: CheckoutContact) => {
    editedContact.current = true;
    setContact(value);
  };
  useEffect(() => {
    if (user && !editedContact.current && !oneClick)
      setContact(contactFrom(user.name, user.phone || '', user.email || ''));
  }, [user, oneClick]);
  const [comment, setComment] = useState(oneClick?.comment || '');
  const [busy, setBusy] = useState(false);
  const submitting = useRef(false);
  const [quoteRevision, setQuoteRevision] = useState(0);
  const [refreshFailed, setRefreshFailed] = useState(false);
  const [error, setError] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const destination = useMemo(
    () => (addressId ? { address_id: addressId } : { directory_external_id: building }),
    [addressId, building],
  );
  const quoteKey = JSON.stringify([
    mode,
    addressId,
    building,
    direct,
    itemsKey,
    warehouse,
    assembly,
    handling,
    floor,
    additional,
    quoteRevision,
  ]);
  const [calculation, setCalculation] = useState<{ key: string; data: WarehouseQuote } | null>(
    null,
  );
  const quote = calculation?.key === quoteKey ? calculation.data : null;
  const [calculatingKey, setCalculatingKey] = useState<string | null>(null);
  const calculating = mode === 'delivery' && calculatingKey === quoteKey;
  const selected = quote?.available ? quote.selected : null;
  const services = selected?.service_options;
  const subtotal = direct ? prepared?.subtotal || 0 : cart?.subtotal || 0;
  const effectiveSubtotal = quote?.subtotal ?? subtotal;
  const fulfillment =
    mode === 'delivery' ? (selected?.total_fulfillment_cost ?? null) : pickupPoint ? 0 : null;
  const total = effectiveSubtotal + (fulfillment ?? 0);
  const hasUnavailable = !direct && cartPanelTotals(cart?.items ?? []).hasUnavailable;

  useEffect(() => {
    let active = true;
    api.paymentMethods
      .list()
      .then((response) => {
        if (active) {
          const checkoutMethods = response.data.filter(
            (method) => method.code === 'cash' || isOnlineCardPaymentMethod(method.code),
          );
          setMethods(checkoutMethods);
          setPayment(checkoutMethods.find(selectable)?.code || '');
        }
      })
      .catch(() => {
        if (active) setError('Не удалось загрузить способы оплаты.');
      });
    return () => {
      active = false;
    };
  }, []);
  useEffect(() => {
    if (!direct) return;
    let active = true;
    setDirectProduct(null);
    runSessionTask(prepare)
      .then((response) => {
        if (active) {
          setPreparation({ key: directKey, data: response });
          setError('');
        }
      })
      .catch(() => {
        if (active) setError('Товар или состав комплекта недоступен или изменился.');
      });
    // Метаданные нужны только для строки в деталях, цены берём из подготовки/расчёта.
    if (api.products?.get)
      api.products
        .get(String(isBundle ? primarySkuId || bundleId : product))
        .then((response) => {
          if (active) setDirectProduct(response.product);
        })
        .catch(() => {});
    return () => {
      active = false;
    };
  }, [direct, directKey, prepare, isBundle, bundleId, primarySkuId, product]);
  useEffect(() => {
    let active = true;
    setPoint('');
    if (items.length)
      runSessionRead(() => api.shipping.pickupOptions(items))
        .then((response) => {
          if (active) setPoints({ key: itemsKey, data: response.pickup });
        })
        .catch(() => {
          if (active) setError('Не удалось проверить точки самовывоза.');
        });
    return () => {
      active = false;
    };
  }, [itemsKey, items]);
  useEffect(() => {
    if (mode !== 'delivery' || (!building && !addressId) || !items.length) {
      setCalculatingKey(null);
      return;
    }
    let active = true;
    setCalculatingKey(quoteKey);
    runSessionRead(() =>
      api.shipping.quote({
        ...destination,
        ...(direct ? { items } : {}),
        warehouse_id: warehouse,
        requires_assembly: assembly,
        delivery_handling_type_id: handling,
        delivery_floor: floor,
        additional_services: additional.map((id) => ({ id })),
      }),
    )
      .then((response) => {
        if (active) {
          setCalculation({ key: quoteKey, data: response });
          setError(
            response.available ? '' : 'Доставка по выбранному адресу недоступна для всего заказа.',
          );
        }
      })
      .catch((problem) => {
        if (active) setError(problem?.data?.message || 'Не удалось рассчитать доставку.');
      })
      .finally(() => {
        if (active) setCalculatingKey(null);
      });
    return () => {
      active = false;
    };
  }, [
    quoteKey,
    mode,
    building,
    addressId,
    items,
    destination,
    direct,
    warehouse,
    assembly,
    handling,
    floor,
    additional,
  ]);

  function selectBuilding(id: string | null) {
    setAddressId(null);
    setBuilding(id);
    setWarehouse(null);
    setHandling(null);
    setAdditional([]);
    setAssembly(false);
  }
  function selectAddress(address: Address) {
    setAddressId(address.id > 0 ? address.id : null);
    setBuilding(address.directory_external_id || null);
    setDetails({
      apartment: address.apartment || '',
      entrance: address.entrance || '',
      intercom: address.intercom || '',
    });
    setFloor(Math.max(1, Number(address.floor) || 1));
    setWarehouse(null);
    setHandling(null);
    setAdditional([]);
    setAssembly(false);
  }
  const canSubmit =
    !refreshFailed &&
    !busy &&
    !calculating &&
    !!items.length &&
    !hasUnavailable &&
    selectable(methods.find((method) => method.code === payment)) &&
    (mode === 'delivery' ? !!selected && !!(building || addressId) : !!pickupPoint);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!canSubmit || submitting.current) return;
    setContactValidationAttempted(true);
    const contactErrors = checkoutContactErrors(contact);
    const firstInvalid = Object.keys(contactErrors)[0];
    if (firstInvalid) {
      event.currentTarget.querySelector<HTMLInputElement>(`input[name="${firstInvalid}"]`)?.focus();
      return;
    }
    submitting.current = true;
    setBusy(true);
    setError('');
    setConfirmation('');
    try {
      const response = await runSessionTask(() =>
        api.orders.create({
          contact_name: [contact.lastName, contact.firstName, contact.middleName]
            .map((part) => part.trim())
            .filter(Boolean)
            .join(' '),
          contact_phone: normalizeCheckoutPhone(contact.phone),
          contact_email: contact.email.trim(),
          payment_method: payment,
          delivery_type: mode,
          expected_subtotal: isBundle ? subtotal : effectiveSubtotal,
          ...(comment.trim() ? { comment: comment.trim() } : {}),
          ...(direct ? { items } : {}),
          ...(isBundle
            ? { bundle_id: bundleId, primary_sku_id: primarySkuId, bundle_quantity: quantity }
            : {}),
          accept_offer: true,
          accept_personal_data: true,
          ...(mode === 'delivery'
            ? {
                ...destination,
                warehouse_id: selected!.warehouse_id,
                delivery_details: details,
                requires_assembly: assembly || !!services?.assembly.required,
                delivery_handling_type_id: handling,
                delivery_floor: floor,
                additional_services: additional.map((id) => ({ id })),
              }
            : { warehouse_id: pickupPoint!.warehouse_id, store_id: pickupPoint!.store_id }),
        }),
      );
      if (!response.order) throw new Error('Заказ не получен.');
      navigate(`/orders/${response.order.id}${response.order.can_pay ? '?openPayment=1' : ''}`);
      if (!direct) void reloadCart().catch(() => {});
    } catch (problem) {
      const p = problem as {
        status?: number;
        data?: { message?: string; errors?: Record<string, string[]> };
      };
      if (p.status === 409) {
        setCalculation(null);
        try {
          if (direct) setPrepared(await runSessionTask(prepare));
          else await reloadCart();
        } catch {
          setPreparation(null);
          setRefreshFailed(true);
        }
        setWarehouse(null);
        setQuoteRevision((revision) => revision + 1);
        setConfirmation('Цена изменилась. Проверьте сумму и подтвердите заказ повторно.');
      } else
        setError(
          Object.values(p.data?.errors || {})
            .flat()
            .join(' ') ||
            p.data?.message ||
            'Не удалось создать заказ.',
        );
    } finally {
      submitting.current = false;
      setBusy(false);
    }
  }
  return {
    cart,
    user,
    isLoading,
    direct,
    isBundle,
    prepared,
    directProduct,
    items,
    methods,
    payment,
    setPayment,
    mode,
    setMode,
    building,
    addressId,
    selectBuilding,
    selectAddress,
    pickup,
    pickupPoint,
    point,
    setPoint,
    quote,
    selected,
    services,
    subtotal: effectiveSubtotal,
    total,
    fulfillment,
    warehouse,
    setWarehouse,
    assembly,
    setAssembly,
    handling,
    setHandling,
    floor,
    setFloor,
    additional,
    setAdditional,
    contact,
    contactValidationAttempted,
    setContact: updateContact,
    comment,
    setComment,
    details,
    setDetails,
    busy,
    calculating,
    error: [error, confirmation].filter(Boolean).join(' '),
    hasUnavailable,
    canSubmit,
    submit,
  };
}
export type CheckoutState = ReturnType<typeof useWarehouseCheckout>;
