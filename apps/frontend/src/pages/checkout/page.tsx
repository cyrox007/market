import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { api, type PaymentMethod, type ShippingLocation, type ShippingMethod, type DeliveryHandlingType, type AdditionalService, type Order, type Address } from '../../lib/api';
import { useRegion } from '../../hooks/useRegion';
import { useAuth } from '../../hooks/useAuth';
import { useCart } from '../../hooks/useCart';
import PhoneInput from '../../components/ui/PhoneInput';
import LocationSearchInput from '../../components/ui/LocationSearchInput';
import AccountCreatedModal from '../../components/AccountCreatedModal';
import {
	buildPaymentUrl,
	closePaymentTab,
	isOnlineCardPaymentMethod,
	openPaymentInNewTab,
	preparePaymentTab,
	showPaymentTabError,
	type GatewayClientConfig,
} from '../../utils/paymentUtils';

import { usePageSeo } from '../../hooks/usePageSeo';

interface AddressForm {
	city: string;
	street: string;
	house: string;
	apartment?: string;
	entrance?: string;
}

interface ContactForm {
	name: string;
	phone: string;
	email: string;
}

const normalizeAddressValue = (value?: string | null): string =>
	(value ?? '').trim().toLowerCase();

export default function Checkout() {
	const navigate = useNavigate();
	const { region, getRegionId, selectRegion } = useRegion();
	const { user, refreshUser } = useAuth();
	const { cart, isLoading: isCartLoading, error: cartError, reloadCart } = useCart();

	// State
	const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
	const [shippingLocations, setShippingLocations] = useState<ShippingLocation[]>([]);
	const [shippingMethodsRaw, setShippingMethodsRaw] = useState<ShippingMethod[]>([]);
	const [savedAddresses, setSavedAddresses] = useState<Address[]>([]);
	const [selectedAddressId, setSelectedAddressId] = useState<number | null>(null);

	usePageSeo({
		title: 'Оформление заказа – Светофор-Мебель',
		description: 'Оформление заказа в интернет-магазине Светофор-Мебель. Быстрая доставка, удобная оплата.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'noindex, follow',
		open_graph_title: 'Оформление заказа – Светофор-Мебель',
		locale: 'ru_RU',
	});

	// Вычисляем финальные цены методов доставки с учетом суммы заказа
	const shippingMethods = useMemo(() => {
		if (!cart?.subtotal && shippingMethodsRaw.length > 0) {
			// Если нет суммы заказа, используем base_price из метода
			return shippingMethodsRaw.map((method) => ({
				...method,
				price: method.base_price || 0,
			}));
		}

		if (shippingMethodsRaw.length === 0) return [];

		return shippingMethodsRaw.map((method) => {
			// Используем price из API (уже рассчитан с учетом суммы заказа), если есть
			// Иначе используем base_price
			let price = method.price !== undefined && method.price !== null
				? method.price
				: (method.base_price !== undefined && method.base_price !== null ? method.base_price : 0);

			// Проверяем порог бесплатной доставки (если цена еще не была обнулена на бэкенде)
			if (method.free_delivery_threshold && cart?.subtotal && cart.subtotal >= method.free_delivery_threshold) {
				price = 0;
			}

			return {
				...method,
				price,
			};
		});
	}, [shippingMethodsRaw, cart?.subtotal]);

	const mergeShippingLocationFromPicker = useCallback((loc: ShippingLocation) => {
		setShippingLocations((prev) => {
			const idx = prev.findIndex((l) => l.id === loc.id);
			if (idx >= 0) {
				const next = [...prev];
				next[idx] = { ...next[idx], ...loc };
				return next;
			}
			return [...prev, loc];
		});
	}, []);

	const [deliveryHandlingTypes, setDeliveryHandlingTypes] = useState<DeliveryHandlingType[]>([]);
	const [additionalServices, setAdditionalServices] = useState<AdditionalService[]>([]);
	const [selectedAdditionalServices, setSelectedAdditionalServices] = useState<Map<number, { price?: number }>>(new Map());
	const [isLoadDataLoading, setIsLoadDataLoading] = useState(true);
	const [isSubmitting, setIsSubmitting] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [showAccountCreatedModal, setShowAccountCreatedModal] = useState(false);
	const [createdOrderId, setCreatedOrderId] = useState<number | undefined>();

	const isLoading = isCartLoading || isLoadDataLoading;

	// Form state
	const [deliveryType, setDeliveryType] = useState<'delivery' | 'pickup'>('delivery');
	const [paymentMethod, setPaymentMethod] = useState<string>('');
	const [assemblyNeeded, setAssemblyNeeded] = useState(false);
	const [deliveryHandlingTypeId, setDeliveryHandlingTypeId] = useState<number | null>(null);
	const [deliveryFloor, setDeliveryFloor] = useState<number | null>(null);
	const [shippingLocationId, setShippingLocationId] = useState<number | null>(null);
	const [shippingMethodId, setShippingMethodId] = useState<number | null>(null);
	const [deliveryDate, setDeliveryDate] = useState<string>('');
	const [deliveryTime, setDeliveryTime] = useState<string>('');
	const [comment, setComment] = useState<string>('');

	const pickupNoticeForSelectedLocation = useMemo(() => {
		const loc = shippingLocations.find((l) => l.id === shippingLocationId);
		const n = loc?.pickup_notice?.trim();
		return n || null;
	}, [shippingLocations, shippingLocationId]);

	const isPickupEnabledForSelectedLocation = useMemo(() => {
		const selectedId = shippingLocationId || region?.id;
		if (!selectedId) return true;

		const loc = shippingLocations.find((l) => l.id === selectedId);
		if (!loc) return true;

		if (typeof loc.effective_pickup_enabled === 'boolean') return loc.effective_pickup_enabled;
		if (typeof loc.pickup_enabled === 'boolean') return loc.pickup_enabled;
		return true;
	}, [shippingLocations, shippingLocationId, region?.id]);

	// Contact form
	const [contactForm, setContactForm] = useState<ContactForm>({
		name: '',
		phone: '',
		email: '',
	});

	// Флаг для отслеживания, было ли автозаполнение
	const [hasAutoFilled, setHasAutoFilled] = useState(false);
	const [hasAutoFilledAddress, setHasAutoFilledAddress] = useState(false);

	// Address form
	const [addressForm, setAddressForm] = useState<AddressForm>({
		city: '',
		street: '',
		house: '',
		apartment: '',
		entrance: '',
	});

	// Calculation
	const [deliveryCost, setDeliveryCost] = useState(0);
	const [assemblyCost, setAssemblyCost] = useState(0);
	const [isCalculating, setIsCalculating] = useState(false);
	/** true после успешного расчёта доставки (delivery); иначе «Бесплатно» не показываем */
	const [deliveryCostKnown, setDeliveryCostKnown] = useState(false);

	const loadData = useCallback(async () => {
		setIsLoadDataLoading(true);
		setError(null);
		try {
			// Используем region?.id напрямую, чтобы гарантировать использование выбранного региона
			// (даже если он выбран автоматически и еще не сохранен в localStorage)
			const regionId = region?.id || getRegionId();
			const [paymentMethodsResponse, locationsResponse]: any = await Promise.all([
				api.paymentMethods.list(regionId ? { region_id: regionId } : undefined),
				api.shipping.getLocations({ type: 'locality' }),
			]);

			setPaymentMethods(paymentMethodsResponse.data || []);
			setShippingLocations(locationsResponse.data || []);

			if (paymentMethodsResponse.data && paymentMethodsResponse.data.length > 0) {
				setPaymentMethod(paymentMethodsResponse.data[0].code);
			}

			if (user) {
				api.addresses
					.list()
					.then((addressesResponse) => {
						if (addressesResponse?.data) {
							setSavedAddresses(addressesResponse.data);
							const defaultAddress = addressesResponse.data.find((addr: Address) => addr.is_default);
							if (defaultAddress) {
								setSelectedAddressId(defaultAddress.id);
							}
						}
					})
					.catch((err) => {
						console.error('Failed to load addresses:', err);
					});
			}
		} catch (err: any) {
			console.error('Failed to load checkout data:', err);
			setError('Не удалось загрузить данные. Пожалуйста, обновите страницу.');
		} finally {
			setIsLoadDataLoading(false);
		}
	}, [region?.id, getRegionId, user]);

	// Загружаем методы доставки и типы обработки при изменении локации доставки
	const loadShippingOptions = useCallback(async () => {
		// Используем shippingLocationId или region.id (если регион выбран автоматически)
		const finalShippingLocationId = shippingLocationId || region?.id;

		if (!finalShippingLocationId) {
			setShippingMethodsRaw([]);
			setDeliveryHandlingTypes([]);
			setAdditionalServices([]);
			setShippingMethodId(null);
			setDeliveryCostKnown(false);
			return;
		}

		try {
			const [methodsResponse, handlingTypesResponse, additionalServicesResponse]: any = await Promise.all([
				api.shipping.getShippingMethods({
					location_id: finalShippingLocationId,
					order_amount: cart?.subtotal || 0,
				}),
				api.shipping.getDeliveryHandlingTypes({
					location_id: finalShippingLocationId,
				}),
				api.shipping.getAdditionalServices({
					location_id: finalShippingLocationId,
				}),
			]);

			setShippingMethodsRaw(methodsResponse.data || []);
			setDeliveryHandlingTypes(handlingTypesResponse.data || []);
			setAdditionalServices(additionalServicesResponse.data || []);

			// Всегда выбираем первый метод доставки по умолчанию
			if (methodsResponse.data && methodsResponse.data.length > 0) {
				setShippingMethodId(methodsResponse.data[0].id);
			} else {
				setShippingMethodId(null);
			}
		} catch (err: any) {
			console.error('Failed to load shipping options:', err);
			setShippingMethodId(null);
		}
	}, [shippingLocationId, region?.id, cart?.subtotal]);

	useEffect(() => {
		// Используем shippingLocationId или region.id (если регион выбран автоматически)
		const finalShippingLocationId = shippingLocationId || region?.id;

		if (finalShippingLocationId) {
			loadShippingOptions();
		} else if (deliveryType === 'pickup') {
			setShippingMethodsRaw([]);
			setDeliveryHandlingTypes([]);
			setShippingMethodId(null);
		}
	}, [deliveryType, shippingLocationId, region?.id, loadShippingOptions]);


	const calculateShipping = useCallback(async () => {
		// Используем shippingLocationId или region.id (если регион выбран автоматически)
		const finalShippingLocationId = shippingLocationId || region?.id;
		if (!cart || !finalShippingLocationId) return;

		setIsCalculating(true);
		if (deliveryType === 'delivery') {
			setDeliveryCostKnown(false);
		}
		try {
			const regionId = getRegionId();

			// Если выбран конкретный метод доставки, используем его для расчета
			let calculation: any;
			if (shippingMethodId && deliveryType === 'delivery') {
				calculation = await api.shipping.calculateShippingMethod({
					shipping_method_id: shippingMethodId,
					location_id: finalShippingLocationId,
					order_amount: cart?.subtotal || 0,
					delivery_handling_type_id: deliveryHandlingTypeId || undefined,
					floor: deliveryFloor || undefined,
					requires_assembly: assemblyNeeded,
				} as any);

				const deliveryPrice = calculation.calculation?.delivery_price || 0;
				const handlingPrice = calculation.calculation?.handling_price || 0;
				setDeliveryCost(deliveryPrice + handlingPrice);
				setDeliveryCostKnown(true);

				if (assemblyNeeded) {
					setAssemblyCost(calculation.calculation?.assembly_price || 0);
				} else {
					setAssemblyCost(0);
				}
			} else {
				// Стандартный расчет (метод не выбран или самовывоз)
				calculation = await api.shipping.calculateShipping({
					location_id: finalShippingLocationId,
					order_amount: cart?.subtotal || 0,
					delivery_handling_type_id: deliveryHandlingTypeId || undefined,
					floor: deliveryFloor || undefined,
					requires_assembly: assemblyNeeded,
					region_id: regionId || undefined,
				} as any);

				const handlingPrice = calculation.calculation?.handling_price || 0;
				const deliveryPrice = calculation.calculation?.delivery_price || 0;
				if (deliveryType === 'pickup') {
					// Для самовывоза доставка всегда бесплатна в UI.
					setDeliveryCost(0);
					setDeliveryCostKnown(false);

					const pickupAssembly = (calculation.calculation?.assembly_price || 0) + handlingPrice;
					setAssemblyCost(assemblyNeeded ? pickupAssembly : 0);
				} else {
					const calculatedDeliveryCost = deliveryPrice + handlingPrice;
					setDeliveryCost(calculatedDeliveryCost);
					setDeliveryCostKnown(true);

					if (assemblyNeeded) {
						setAssemblyCost(calculation.calculation?.assembly_price || 0);
					} else {
						setAssemblyCost(0);
					}
				}
			}
		} catch (err: any) {
			console.error('Failed to calculate shipping:', err);
			setDeliveryCost(0);
			setAssemblyCost(0);
			setDeliveryCostKnown(false);
		} finally {
			setIsCalculating(false);
		}
	}, [cart, shippingLocationId, region?.id, deliveryHandlingTypeId, deliveryFloor, assemblyNeeded, shippingMethodId, deliveryType, getRegionId]);

	useEffect(() => {
		loadData();
	}, [loadData]);

	// Автозаполнение формы данными из профиля
	useEffect(() => {
		if (user && !hasAutoFilled) {
			setContactForm({
				name: user.name || '',
				phone: user.phone || '',
				email: user.email || '',
			});
			setHasAutoFilled(true);
		}
	}, [user, hasAutoFilled]);

	// Автозаполнение адреса адресом по умолчанию
	useEffect(() => {
		if (deliveryType === 'delivery' && savedAddresses.length > 0 && !hasAutoFilledAddress) {
			const defaultAddress = savedAddresses.find(addr => addr.is_default) || savedAddresses[0];
			if (defaultAddress) {
				setSelectedAddressId(defaultAddress.id);
				setAddressForm({
					city: defaultAddress.city || '',
					street: defaultAddress.street || '',
					house: defaultAddress.house || '',
					apartment: defaultAddress.apartment || '',
					entrance: defaultAddress.entrance || '',
				});
				if (defaultAddress.shipping_location_id) {
					setShippingLocationId(defaultAddress.shipping_location_id);
				}
				setHasAutoFilledAddress(true);
			}
		}
	}, [deliveryType, savedAddresses, hasAutoFilledAddress]);

	// Обработка выбора сохраненного адреса
	const handleAddressSelect = (addressId: number | null) => {
		setSelectedAddressId(addressId);
		if (addressId) {
			const address = savedAddresses.find(addr => addr.id === addressId);
			if (address) {
				setAddressForm({
					city: address.city || '',
					street: address.street || '',
					house: address.house || '',
					apartment: address.apartment || '',
					entrance: address.entrance || '',
				});
				if (address.shipping_location_id) {
					setShippingLocationId(address.shipping_location_id);
				}
			}
		} else {
			// Очищаем форму при выборе "Новый адрес"
			setAddressForm({
				city: '',
				street: '',
				house: '',
				apartment: '',
				entrance: '',
			});
		}
	};

	// Флаг для отслеживания, что изменение shippingLocationId происходит из-за изменения region
	// Это предотвращает циклические обновления
	const isRegionChangeRef = useRef(false);
	// Флаг для отслеживания, что изменение region происходит из-за изменения shippingLocationId
	const isLocationChangeRef = useRef(false);
	// Предыдущее значение shippingLocationId для отслеживания изменений
	const prevShippingLocationIdRef = useRef<number | null>(null);
	// Предыдущее значение region.id для отслеживания изменений
	const prevRegionIdRef = useRef<number | null>(null);

	// Обновляем shippingLocationId при изменении региона (синхронизация: регион → локация доставки)
	// Важно: устанавливаем shippingLocationId из region, даже если регион выбран автоматически
	// Это работает, когда пользователь меняет регион из селектора в хедере или другого места
	useEffect(() => {
		// Пропускаем, если изменение region произошло из-за изменения shippingLocationId
		if (isLocationChangeRef.current) {
			// Обновляем prevRegionIdRef, чтобы не сработало при следующем изменении
			if (region?.id) {
				prevRegionIdRef.current = region.id;
			}
			return;
		}

		// Пропускаем, если регион не изменился
		if (region?.id === prevRegionIdRef.current) {
			return;
		}

		if (region?.id) {
			// Если shippingLocationId не установлен, или если он не совпадает с текущим регионом
			// (например, регион был выбран автоматически после загрузки страницы или изменен из хедера)
			if (!shippingLocationId || shippingLocationId !== region.id) {
				isRegionChangeRef.current = true; // Помечаем, что изменение идет от region
				setShippingLocationId(region.id);
				prevShippingLocationIdRef.current = region.id;
				prevRegionIdRef.current = region.id;

				// Перезагружаем корзину для обновления цен с учетом нового региона
				setTimeout(() => {
					reloadCart();
				}, 150);

				// Сбрасываем флаг после небольшой задержки
				setTimeout(() => {
					isRegionChangeRef.current = false;
				}, 100);
			} else {
				// Обновляем prevRegionIdRef, даже если shippingLocationId уже совпадает
				prevRegionIdRef.current = region.id;
			}
		}
	}, [region?.id, shippingLocationId, reloadCart]);

	// Синхронизируем регион сайта при изменении локации доставки в форме (синхронизация: локация доставки → регион)
	// Это позволяет обновлять цены и корзину при выборе другого города/региона
	useEffect(() => {
		// Пропускаем, если изменение идет от region (чтобы избежать цикла)
		if (isRegionChangeRef.current) {
			return;
		}

		// Пропускаем, если shippingLocationId не установлен
		if (!shippingLocationId) {
			prevShippingLocationIdRef.current = null;
			return;
		}

		// Пропускаем, если shippingLocationId не изменился
		if (shippingLocationId === prevShippingLocationIdRef.current) {
			return;
		}

		// Пропускаем, если совпадает с текущим регионом (уже синхронизировано)
		if (shippingLocationId === region?.id) {
			prevShippingLocationIdRef.current = shippingLocationId;
			return;
		}

		// Пропускаем, если список локаций еще не загружен
		if (shippingLocations.length === 0) {
			return;
		}

		// Находим локацию в списке
		const selectedLocation = shippingLocations.find(loc => loc.id === shippingLocationId);

		if (selectedLocation) {
			// Помечаем, что изменение region происходит из-за изменения shippingLocationId
			isLocationChangeRef.current = true;
			// Обновляем регион на всем сайте
			// Это вызовет событие 'region-changed', которое обновит корзину, цены и все остальное
			selectRegion(selectedLocation);
			prevShippingLocationIdRef.current = shippingLocationId;

			// Явно перезагружаем корзину для обновления цен после небольшой задержки
			// (чтобы дать время региону обновиться)
			setTimeout(() => {
				reloadCart();
			}, 150);

			// Сбрасываем флаг после небольшой задержки
			setTimeout(() => {
				isLocationChangeRef.current = false;
			}, 100);
		}
	}, [shippingLocationId, shippingLocations, region?.id, selectRegion, reloadCart]);

	// Перезагружаем данные при изменении региона
	useEffect(() => {
		const handleRegionChange = () => {
			// Перезагружаем корзину для обновления цен с учетом нового региона
			reloadCart();
			// Перезагружаем данные формы (методы оплаты, локации и т.д.)
			loadData();
		};

		window.addEventListener('region-changed', handleRegionChange);
		return () => {
			window.removeEventListener('region-changed', handleRegionChange);
		};
	}, [loadData, reloadCart]);

	// Перезагружаем данные при изменении region (включая автоматический выбор)
	// Используем отдельный ref для отслеживания изменений region.id для loadData
	const prevRegionIdForLoadDataRef = useRef<number | null>(null);
	useEffect(() => {
		if (region?.id && prevRegionIdForLoadDataRef.current !== region.id) {
			prevRegionIdForLoadDataRef.current = region.id;
			// Вызываем loadData только если регион действительно изменился
			loadData();
		}
	}, [region?.id]); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect(() => {
		if (deliveryType === 'pickup' && !isPickupEnabledForSelectedLocation) {
			setDeliveryType('delivery');
		}
	}, [deliveryType, isPickupEnabledForSelectedLocation]);

	const calculateShippingTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
	const DEBOUNCE_MS = 400;

	useEffect(() => {
		if (calculateShippingTimerRef.current) {
			clearTimeout(calculateShippingTimerRef.current);
			calculateShippingTimerRef.current = null;
		}

		if (cart && deliveryType === 'delivery' && shippingLocationId) {
			calculateShippingTimerRef.current = setTimeout(() => {
				calculateShippingTimerRef.current = null;
				calculateShipping();
			}, DEBOUNCE_MS);
		} else if (deliveryType === 'pickup') {
			setDeliveryCost(0);
			setDeliveryCostKnown(false);
			if (shippingLocationId) {
				calculateShippingTimerRef.current = setTimeout(() => {
					calculateShippingTimerRef.current = null;
					calculateShipping();
				}, DEBOUNCE_MS);
			}
		}

		return () => {
			if (calculateShippingTimerRef.current) {
				clearTimeout(calculateShippingTimerRef.current);
				calculateShippingTimerRef.current = null;
			}
		};
	}, [cart, deliveryType, shippingLocationId, deliveryHandlingTypeId, deliveryFloor, assemblyNeeded, shippingMethodId, calculateShipping]);

	const handleSubmit = async (e: React.FormEvent) => {
		e.preventDefault();
		setError(null);

		// Validation
		if (!contactForm.name || !contactForm.phone || !contactForm.email) {
			setError('Заполните все обязательные поля контактной информации');
			window.scrollTo({ top: 0, behavior: 'smooth' });
			return;
		}

		if (deliveryType === 'delivery') {
			if (!addressForm.city || !addressForm.street || !addressForm.house) {
				setError('Заполните адрес доставки');
				window.scrollTo({ top: 0, behavior: 'smooth' });
				return;
			}
			// Проверяем shippingLocationId или region.id (если регион выбран автоматически)
			const finalShippingLocationId = shippingLocationId || region?.id;
			if (!finalShippingLocationId) {
				setError('Выберите локацию доставки');
				window.scrollTo({ top: 0, behavior: 'smooth' });
				return;
			}
		}

		if (deliveryType === 'delivery' && !shippingMethodId) {
			setError('Выберите способ доставки');
			window.scrollTo({ top: 0, behavior: 'smooth' });
			return;
		}

		if (!paymentMethod) {
			setError('Выберите способ оплаты');
			window.scrollTo({ top: 0, behavior: 'smooth' });
			return;
		}

		setIsSubmitting(true);

		const paymentTab = isOnlineCardPaymentMethod(paymentMethod) ? preparePaymentTab() : null;

		try {
			// Получаем region_id из региона или через getRegionId
			// Приоритет: region?.id > getRegionId() > shippingLocationId
			const regionId = region?.id || getRegionId() || shippingLocationId;

			// Если shippingLocationId не установлен, но есть region, используем его
			const finalShippingLocationId = shippingLocationId || region?.id;

			// Убеждаемся, что стоимость доставки рассчитана
			if (deliveryType === 'delivery' && finalShippingLocationId) {
				// Временно устанавливаем shippingLocationId, если он не был установлен
				if (!shippingLocationId && region?.id) {
					setShippingLocationId(region.id);
				}
				await calculateShipping();
			}

			const orderData: any = {
				contact_name: contactForm.name,
				contact_phone: contactForm.phone,
				contact_email: contactForm.email,
				payment_method: paymentMethod,
				delivery_type: deliveryType,
				comment: comment || undefined,
				requires_assembly: assemblyNeeded,
				delivery_cost: deliveryType === 'pickup' ? 0 : deliveryCost,
				assembly_cost: assemblyCost,
			};

			// Добавляем region_id для применения правил корзины
			// Критично: region_id должен быть всегда, если регион выбран (даже автоматически)
			if (regionId) {
				orderData.region_id = regionId;
			} else if (region?.id) {
				// Fallback: используем region.id напрямую
				orderData.region_id = region.id;
			}

			if (deliveryType === 'delivery') {
				// Используем finalShippingLocationId, который учитывает автоматически выбранный регион
				orderData.shipping_location_id = finalShippingLocationId || shippingLocationId;
				if (selectedAddressId) {
					orderData.address_id = selectedAddressId;
				}
				orderData.address = {
					city: addressForm.city,
					street: addressForm.street,
					house: addressForm.house,
					apartment: addressForm.apartment || undefined,
					entrance: addressForm.entrance || undefined,
				};
				if (deliveryHandlingTypeId) {
					orderData.delivery_handling_type_id = deliveryHandlingTypeId;
				}
				if (deliveryFloor) {
					orderData.delivery_floor = deliveryFloor;
				}
				if (shippingMethodId && deliveryType === 'delivery') {
					orderData.shipping_method_id = shippingMethodId;
				}
				if (deliveryDate) {
					orderData.delivery_date = deliveryDate;
				}
				if (deliveryTime) {
					orderData.delivery_time = deliveryTime;
				}
			} else {
				// Для самовывоза также используем finalShippingLocationId
				if (finalShippingLocationId) {
					orderData.shipping_location_id = finalShippingLocationId;
				}
			}

			// Добавляем дополнительные услуги
			if (selectedAdditionalServices.size > 0) {
				orderData.additional_services = Array.from(selectedAdditionalServices.entries()).map(([serviceId, serviceData]) => {
					const service = additionalServices.find(s => s.id === serviceId);
					return {
						id: serviceId,
						price: serviceData.price !== undefined ? serviceData.price : (service?.price ?? null),
					};
				});
			}

			const response = await api.orders.create(orderData) as {
				order?: Order;
				message?: string;
				user_registered?: boolean;
				gateway_client_config?:
				| { publicId: string; url: string; useSdk?: boolean }
				| { payformUrl: string; useEcomApi: true };
			};

			// Если была автоматическая регистрация, обновляем пользователя и показываем модалку
			if (response.user_registered && response.order) {
				closePaymentTab(paymentTab);
				// Обновляем данные пользователя в контексте
				await refreshUser();
				setCreatedOrderId(response.order.id);
				setShowAccountCreatedModal(true);
				return; // Не делаем редирект, модалка сама перенаправит
			}

			// Проверяем, нужно ли предложить обновить профиль
			if (user && response.order) {
				const profileNeedsUpdate =
					(user.name !== contactForm.name) ||
					(user.phone !== contactForm.phone) ||
					(user.email !== contactForm.email);

				const hasAddress = deliveryType === 'delivery' && addressForm.city && addressForm.street && addressForm.house;
				const selectedAddress = selectedAddressId
					? savedAddresses.find((addr) => addr.id === selectedAddressId) ?? null
					: null;
				const addressNeedsUpdate = hasAddress && (
					!selectedAddress ||
					normalizeAddressValue(selectedAddress.city) !== normalizeAddressValue(addressForm.city) ||
					normalizeAddressValue(selectedAddress.street) !== normalizeAddressValue(addressForm.street) ||
					normalizeAddressValue(selectedAddress.house) !== normalizeAddressValue(addressForm.house) ||
					normalizeAddressValue(selectedAddress.apartment) !== normalizeAddressValue(addressForm.apartment) ||
					normalizeAddressValue(selectedAddress.entrance) !== normalizeAddressValue(addressForm.entrance) ||
					(selectedAddress.shipping_location_id ?? null) !== (shippingLocationId ?? null)
				);

				// Сохраняем данные для показа модального окна после редиректа
				if (profileNeedsUpdate || addressNeedsUpdate) {
					sessionStorage.setItem('checkout_sync_data', JSON.stringify({
						profileNeedsUpdate,
						addressData: addressNeedsUpdate ? addressForm : null,
						shippingLocationId: shippingLocationId,
						orderId: response.order.id,
					}));
				}
			}

			// При оплате картой онлайн — редирект на форму банка (конфиг уже в ответе create)
			if (response.order?.id && response.gateway_client_config) {
				try {
					const gw = response.gateway_client_config as GatewayClientConfig;
					let paymentUrl: string;

					if ('payformUrl' in gw && gw.payformUrl) {
						paymentUrl = buildPaymentUrl(gw);
					} else {
						const config = await api.orders.getPaymentConfig(response.order.id);
						paymentUrl = buildPaymentUrl(
							config.gateway_client_config as GatewayClientConfig,
							config,
						);
					}

					const paymentOpened = openPaymentInNewTab(paymentUrl, paymentTab);
					if (!paymentOpened) {
						const msg = 'Заказ создан. Разрешите всплывающие окна или нажмите «Оплатить» на странице заказа.';
						showPaymentTabError(paymentTab, msg);
						setError(msg);
						window.scrollTo({ top: 0, behavior: 'smooth' });
					}
				} catch (e) {
					console.error('Failed to open payment:', e);
					const msg = 'Заказ создан, но не удалось открыть оплату. Нажмите «Оплатить» на странице заказа.';
					showPaymentTabError(paymentTab, msg);
					closePaymentTab(paymentTab);
					setError(msg);
					window.scrollTo({ top: 0, behavior: 'smooth' });
				}
			} else {
				closePaymentTab(paymentTab);
			}

			if (response.order?.id) {
				const openPaymentQuery =
					isOnlineCardPaymentMethod(paymentMethod) && !response.gateway_client_config
						? '?openPayment=1'
						: '';
				navigate(`/orders/${response.order.id}${openPaymentQuery}`);
			} else {
				navigate('/orders');
			}
		} catch (err: any) {
			closePaymentTab(paymentTab);
			console.error('Failed to create order:', err);
			setError(err?.data?.message || err?.message || 'Ошибка при создании заказа. Пожалуйста, попробуйте снова.');
			window.scrollTo({ top: 0, behavior: 'smooth' });
		} finally {
			setIsSubmitting(false);
		}
	};

	// Рассчитываем стоимость дополнительных услуг (до условных возвратов!)
	const additionalServicesCost = useMemo(() => {
		let total = 0;
		selectedAdditionalServices.forEach((serviceData, serviceId) => {
			const service = additionalServices.find(s => s.id === serviceId);
			if (service && serviceData.price !== undefined && serviceData.price !== null) {
				total += serviceData.price;
			} else if (service && service.price !== undefined && service.price !== null) {
				total += service.price;
			}
		});
		return total;
	}, [selectedAdditionalServices, additionalServices]);

	if (isLoading) {
		return (
			<div className="min-h-screen bg-white">
				<div className="max-w-7xl mx-auto px-4 py-6">
					<div className="text-center py-12">
						<div className="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
						<p className="mt-4 text-gray-600">Загрузка...</p>
					</div>
				</div>
			</div>
		);
	}

	if (!cart || cart.items.length === 0) {
		return (
			<div className="min-h-screen bg-white">
				<div className="max-w-7xl mx-auto px-4 py-6">
					<div className="bg-white border border-gray-200 rounded-2xl p-20 text-center">
						<h2 className="text-2xl font-bold mb-4">Корзина пуста</h2>
						<p className="text-gray-600 mb-6">Добавьте товары в корзину перед оформлением заказа</p>
						<Link
							to="/catalog"
							className="inline-block bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors"
						>
							Перейти в каталог
						</Link>
					</div>
				</div>
			</div>
		);
	}

	const subtotal = cart?.subtotal || 0;
	const total = subtotal + deliveryCost + assemblyCost + additionalServicesCost;

	return (
		<div className="min-h-screen bg-white">

			<div className="max-w-7xl mx-auto px-4 py-6">
				{/* Breadcrumbs */}
				<div className="flex items-center gap-2 text-sm mb-2">
					<Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
					<i className="ri-arrow-right-s-line text-gray-400"></i>
					<Link to="/cart" className="text-gray-600 hover:text-red-600">Корзина</Link>
					<i className="ri-arrow-right-s-line text-gray-400"></i>
					<span className="text-gray-900">Оформление заказа</span>
				</div>

				<h1 className="text-4xl font-bold mb-8">Оформление заказа</h1>

				{/* Progress Steps */}
				<div className="mb-12">
					<div className="flex items-center justify-between max-w-3xl mx-auto">
						{[
							{ step: 1, label: 'Контакты', active: true },
							{ step: 2, label: 'Доставка', active: true },
							{ step: 3, label: 'Оплата', active: true },
							{ step: 4, label: 'Подтверждение', active: false }
						].map((item, idx) => (
							<div key={idx} className="flex items-center flex-1">
								<div className="flex flex-col items-center flex-1">
									<div className={`w-12 h-12 rounded-full flex items-center justify-center font-bold mb-2 ${item.active ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-500'
										}`}>
										{item.step}
									</div>
									<span className={`text-sm font-medium ${item.active ? 'text-red-600' : 'text-gray-500'}`}>
										{item.label}
									</span>
								</div>
								{idx < 3 && (
									<div className={`h-1 flex-1 mx-4 ${item.active ? 'bg-red-600' : 'bg-gray-200'}`}></div>
								)}
							</div>
						))}
					</div>
				</div>

				{(error || cartError) && (
					<div className="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
						<p className="text-red-600 text-sm">{error || cartError}</p>
					</div>
				)}

				<form onSubmit={handleSubmit}>
					<div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
						{/* Form */}
						<div className="lg:col-span-2 space-y-8">
							{/* Contact Information */}
							<div className="bg-white border border-gray-200 rounded-2xl p-8">
								<h2 className="text-2xl font-bold mb-6">Контактная информация</h2>
								<div className="space-y-4">
									<div>
										<label className="block text-sm font-medium mb-2">Имя *</label>
										<input
											type="text"
											required
											value={contactForm.name}
											onChange={(e) => setContactForm({ ...contactForm, name: e.target.value })}
											className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
											placeholder="Иван"
										/>
									</div>
									<div>
										<label className="block text-sm font-medium mb-2">Телефон *</label>
										<PhoneInput
											type="tel"
											required
											value={contactForm.phone}
											onChange={(e) => setContactForm({ ...contactForm, phone: e.target.value })}
											className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
											placeholder="+7 (999) 123-45-67"
										/>
									</div>
									<div>
										<label className="block text-sm font-medium mb-2">Email *</label>
										<input
											type="email"
											required
											value={contactForm.email}
											onChange={(e) => setContactForm({ ...contactForm, email: e.target.value })}
											className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
											placeholder="example@mail.ru"
										/>
									</div>
								</div>
							</div>

							{/* Delivery */}
							<div className="bg-white border border-gray-200 rounded-2xl p-8">
								<h2 className="text-2xl font-bold mb-6">Способ получения</h2>
								<div className="space-y-4 mb-6">
									<label className="flex items-start gap-4 p-4 border-2 rounded-lg cursor-pointer hover:border-red-600 transition-colors">
										<input
											type="radio"
											name="delivery"
											value="delivery"
											checked={deliveryType === 'delivery'}
											onChange={(e) => setDeliveryType(e.target.value as 'delivery' | 'pickup')}
											className="mt-1"
										/>
										<div className="flex-1">
											<div className="flex items-center gap-3 mb-2">
												<i className="ri-truck-line text-2xl text-red-600"></i>
												<span className="font-bold">Доставка курьером</span>
											</div>
											<p className="text-sm text-gray-600">Доставка по указанному адресу</p>
										</div>
									</label>
									{isPickupEnabledForSelectedLocation && (
										<label className="flex items-start gap-4 p-4 border-2 rounded-lg cursor-pointer hover:border-red-600 transition-colors">
											<input
												type="radio"
												name="delivery"
												value="pickup"
												checked={deliveryType === 'pickup'}
												onChange={(e) => setDeliveryType(e.target.value as 'delivery' | 'pickup')}
												className="mt-1"
											/>
											<div className="flex-1">
												<div className="flex items-center gap-3 mb-2">
													<i className="ri-store-line text-2xl text-red-600"></i>
													<span className="font-bold">Самовывоз из магазина</span>
												</div>
												<div className="text-sm text-gray-600 space-y-1">
													<p className="font-medium text-green-700">Бесплатно</p>
													<p>{pickupNoticeForSelectedLocation || 'Готов к выдаче через 2 часа'}</p>
												</div>
											</div>
										</label>
									)}
								</div>

								{deliveryType === 'delivery' && (
									<div className="space-y-4">
										{/* Выбор сохраненного адреса */}
										{savedAddresses.length > 0 && (
											<div>
												<label className="block text-sm font-medium mb-2">Выберите адрес доставки</label>
												<select
													value={selectedAddressId || ''}
													onChange={(e) => handleAddressSelect(e.target.value ? parseInt(e.target.value) : null)}
													className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
												>
													<option value="">Новый адрес</option>
													{savedAddresses.map((address) => (
														<option key={address.id} value={address.id}>
															{address.title || 'Адрес'} - {address.full_address || `${address.city}, ${address.street}, ${address.house}`}
															{address.is_default && ' (По умолчанию)'}
														</option>
													))}
												</select>
											</div>
										)}

										<div>
											<label className="block text-sm font-medium mb-2">Локация доставки *</label>
											<LocationSearchInput
												key="checkout-delivery-location"
												value={shippingLocationId}
												selectedDisplayName={region?.id === shippingLocationId ? region.name : ''}
												onChange={(loc) => {
													mergeShippingLocationFromPicker(loc);
													isLocationChangeRef.current = true;
													prevShippingLocationIdRef.current = loc.id;
													setShippingLocationId(loc.id);
													selectRegion(loc);
													reloadCart();
													setTimeout(() => { isLocationChangeRef.current = false; }, 100);
												}}
												initialLocations={shippingLocations}
												placeholder="Найти город или регион..."
												required
												aria-label="Локация доставки"
											/>
											<p className="mt-1 text-xs text-gray-500">
												Выбор локации меняет регион на всём сайте: цены и наличие товаров зависят от региона.
											</p>
										</div>
										<div>
											<label className="block text-sm font-medium mb-2">Город *</label>
											<input
												type="text"
												required
												value={addressForm.city}
												onChange={(e) => {
													setAddressForm({ ...addressForm, city: e.target.value });
													setSelectedAddressId(null); // Сбрасываем выбор при ручном редактировании
												}}
												className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
												placeholder="Москва"
											/>
										</div>
										<div>
											<label className="block text-sm font-medium mb-2">Улица *</label>
											<input
												type="text"
												required
												value={addressForm.street}
												onChange={(e) => {
													setAddressForm({ ...addressForm, street: e.target.value });
													setSelectedAddressId(null); // Сбрасываем выбор при ручном редактировании
												}}
												className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
												placeholder="ул. Ленина"
											/>
										</div>
										<div className="grid grid-cols-3 gap-4">
											<div>
												<label className="block text-sm font-medium mb-2">Дом *</label>
												<input
													type="text"
													required
													value={addressForm.house}
													onChange={(e) => {
														setAddressForm({ ...addressForm, house: e.target.value });
														setSelectedAddressId(null); // Сбрасываем выбор при ручном редактировании
													}}
													className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
													placeholder="123"
												/>
											</div>
											<div>
												<label className="block text-sm font-medium mb-2">Квартира</label>
												<input
													type="text"
													value={addressForm.apartment || ''}
													onChange={(e) => {
														setAddressForm({ ...addressForm, apartment: e.target.value });
														setSelectedAddressId(null); // Сбрасываем выбор при ручном редактировании
													}}
													className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
													placeholder="45"
												/>
											</div>
											<div>
												<label className="block text-sm font-medium mb-2">Подъезд</label>
												<input
													type="text"
													value={addressForm.entrance || ''}
													onChange={(e) => {
														setAddressForm({ ...addressForm, entrance: e.target.value });
														setSelectedAddressId(null); // Сбрасываем выбор при ручном редактировании
													}}
													className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
													placeholder="2"
												/>
											</div>
										</div>
										<div>
											<label className="block text-sm font-medium mb-2">Дата доставки</label>
											<input
												type="date"
												value={deliveryDate}
												onChange={(e) => setDeliveryDate(e.target.value)}
												className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
											/>
										</div>
										<div>
											<label className="block text-sm font-medium mb-2">Время доставки</label>
											<select
												value={deliveryTime}
												onChange={(e) => setDeliveryTime(e.target.value)}
												className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none pr-8"
											>
												<option value="">Выберите время</option>
												<option value="10:00 - 14:00">10:00 - 14:00</option>
												<option value="14:00 - 18:00">14:00 - 18:00</option>
												<option value="18:00 - 22:00">18:00 - 22:00</option>
											</select>
										</div>

										{/* Методы доставки */}
										{shippingMethods.length > 0 && (
											<div>
												<label className="block text-sm font-medium mb-3">Служба доставки *</label>
												<div className="space-y-2">
													{shippingMethods.map((method) => (
														<label
															key={method.id}
															className={`flex items-start gap-4 p-4 border-2 rounded-lg cursor-pointer transition-all ${shippingMethodId === method.id
																? 'border-red-600 bg-red-50'
																: 'border-gray-200 hover:border-red-300'
																}`}
														>
															<input
																type="radio"
																name="shipping_method"
																value={method.id}
																checked={shippingMethodId === method.id}
																onChange={(e) => setShippingMethodId(parseInt(e.target.value))}
																className="mt-1"
															/>
															<div className="flex-1">
																<div className="flex items-center justify-between mb-1">
																	<span className="font-semibold">{method.name}</span>
																	<span className="font-bold text-red-600">
																		{(() => {
																			// Используем вычисленную цену из useMemo (уже учитывает порог бесплатной доставки)
																			const methodPrice = method.price !== undefined && method.price !== null ? method.price : 0;
																			return methodPrice > 0
																				? `${methodPrice.toLocaleString()} ₽`
																				: 'Бесплатно';
																		})()}
																	</span>
																</div>
																{method.carrier && (
																	<p className="text-sm text-gray-600">{method.carrier.name}</p>
																)}
																{(method.delivery_days_min || method.delivery_days_max) && (
																	<p className="text-xs text-gray-500 mt-1">
																		Срок доставки: {
																			method.delivery_days_min && method.delivery_days_max
																				? `${method.delivery_days_min}-${method.delivery_days_max} дн.`
																				: method.delivery_days_min
																					? `от ${method.delivery_days_min} дн.`
																					: method.delivery_days_max
																						? `до ${method.delivery_days_max} дн.`
																						: ''
																		}
																	</p>
																)}
																{method.free_delivery_threshold && method.price > 0 && (
																	<p className="text-xs text-gray-500 mt-1">
																		Бесплатная доставка от {method.free_delivery_threshold.toLocaleString()} ₽
																	</p>
																)}
															</div>
														</label>
													))}
												</div>
											</div>
										)}

										{/* Типы обработки доставки (дополнительные услуги) */}
										{deliveryHandlingTypes.length > 0 && (
											<div>
												<label className="block text-sm font-medium mb-3">Дополнительные услуги</label>
												<div className="space-y-2">
													{deliveryHandlingTypes.map((type) => (
														<div
															key={type.id}
															className={`p-4 border-2 rounded-lg transition-all ${deliveryHandlingTypeId === type.id
																? 'border-red-600 bg-red-50'
																: 'border-gray-200'
																}`}
														>
															<label className="flex items-start gap-4 cursor-pointer">
																<input
																	type="radio"
																	name="delivery_handling_type"
																	value={type.id}
																	checked={deliveryHandlingTypeId === type.id}
																	onChange={(e) => {
																		setDeliveryHandlingTypeId(parseInt(e.target.value));
																		if (!type.requires_floor) {
																			setDeliveryFloor(null);
																		}
																	}}
																	className="mt-1"
																/>
																<div className="flex-1">
																	<div className="flex items-center justify-between mb-1">
																		<span className="font-semibold">{type.name}</span>
																		{type.base_price !== undefined && type.base_price > 0 && (
																			<span className="font-bold text-red-600">
																				от {type.base_price.toLocaleString()} ₽
																			</span>
																		)}
																	</div>
																	{type.description && (
																		<p className="text-sm text-gray-600 mb-2">{type.description}</p>
																	)}
																	{type.requires_floor && deliveryHandlingTypeId === type.id && (
																		<div className="mt-3">
																			<label className="block text-sm font-medium mb-2">
																				Этаж {type.max_floor ? `(до ${type.max_floor})` : ''} *
																			</label>
																			<input
																				type="number"
																				min="1"
																				max={type.max_floor || 20}
																				value={deliveryFloor || ''}
																				onChange={(e) => setDeliveryFloor(parseInt(e.target.value) || null)}
																				className="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
																				placeholder="Укажите этаж"
																			/>
																			{type.example_prices && Object.keys(type.example_prices).length > 0 && (
																				<p className="text-xs text-gray-500 mt-1">
																					Пример: {Object.entries(type.example_prices).slice(0, 3).map(([floor, price]) => (
																						<span key={floor} className="mr-3">
																							{floor} этаж: {price.toLocaleString()} ₽
																						</span>
																					))}
																				</p>
																			)}
																		</div>
																	)}
																</div>
															</label>
														</div>
													))}
													<label className="flex items-start gap-4 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-red-300 transition-all">
														<input
															type="radio"
															name="delivery_handling_type"
															value=""
															checked={deliveryHandlingTypeId === null}
															onChange={() => {
																setDeliveryHandlingTypeId(null);
																setDeliveryFloor(null);
															}}
															className="mt-1"
														/>
														<div className="flex-1">
															<span className="font-semibold">Без дополнительных услуг</span>
															<p className="text-sm text-gray-600 mt-1">Доставка до подъезда</p>
														</div>
													</label>
												</div>
											</div>
										)}
									</div>
								)}

								{deliveryType === 'pickup' && (
									<div className="bg-gray-50 rounded-lg p-4">
										<p className="font-medium mb-2">Выберите магазин для самовывоза</p>
										<LocationSearchInput
											key="checkout-pickup-location"
											value={shippingLocationId}
											selectedDisplayName={region?.id === shippingLocationId ? region.name : ''}
											onChange={(loc) => {
												mergeShippingLocationFromPicker(loc);
												isLocationChangeRef.current = true;
												prevShippingLocationIdRef.current = loc.id;
												setShippingLocationId(loc.id);
												selectRegion(loc);
												reloadCart();
												setTimeout(() => { isLocationChangeRef.current = false; }, 100);
											}}
											initialLocations={shippingLocations}
											placeholder="Найти магазин или город..."
											required
											aria-label="Магазин самовывоза"
										/>
									</div>
								)}
							</div>

							{/* Additional Services */}
							<div className="bg-white border border-gray-200 rounded-2xl p-8">
								<h2 className="text-2xl font-bold mb-6">Дополнительные услуги</h2>

								{additionalServices.length === 0 ? (
									<p className="text-gray-500 text-center py-8">Нет доступных дополнительных услуг для выбранной локации</p>
								) : (
									<div className="space-y-4">
										{additionalServices.map((service) => {
											const isSelected = selectedAdditionalServices.has(service.id);
											const servicePrice = selectedAdditionalServices.get(service.id)?.price ?? service.price;

											return (
												<label
													key={service.id}
													className={`flex items-start gap-4 p-4 border-2 rounded-lg cursor-pointer transition-all ${isSelected
														? 'border-red-600 bg-red-50'
														: 'border-gray-200 hover:border-red-300'
														}`}
												>
													<input
														type="checkbox"
														checked={isSelected}
														onChange={(e) => {
															const newSelected = new Map(selectedAdditionalServices);
															if (e.target.checked) {
																newSelected.set(service.id, { price: servicePrice });
															} else {
																newSelected.delete(service.id);
															}
															setSelectedAdditionalServices(newSelected);
														}}
														className="mt-1"
													/>
													<div className="flex-1">
														<div className="flex items-center justify-between mb-2">
															<div className="flex items-center gap-3">
																{service.icon && (
																	<i className={`${service.icon} text-2xl text-red-600`}></i>
																)}
																<span className="font-bold">{service.name}</span>
															</div>
															<div className="flex flex-col items-end">
																{service.price_type === 'custom' ? (
																	<div className="flex items-center gap-2">
																		<span className="text-sm text-gray-600">Цена:</span>
																		<input
																			type="number"
																			min="0"
																			step="0.01"
																			value={servicePrice ?? ''}
																			onChange={(e) => {
																				const newSelected = new Map(selectedAdditionalServices);
																				const price = e.target.value ? parseFloat(e.target.value) : undefined;
																				if (isSelected) {
																					newSelected.set(service.id, { price });
																				} else {
																					// Автоматически выбираем услугу при вводе цены
																					newSelected.set(service.id, { price });
																					setSelectedAdditionalServices(newSelected);
																				}
																				setSelectedAdditionalServices(newSelected);
																			}}
																			onClick={(e) => e.stopPropagation()}
																			className="w-24 px-2 py-1 border border-gray-300 rounded text-sm"
																			placeholder="0"
																		/>
																		<span className="text-sm text-gray-600">₽</span>
																	</div>
																) : service.price_type === 'from' ? (
																	<span className="font-bold text-gray-700">
																		от {servicePrice ? `${servicePrice.toLocaleString()} ₽` : '—'}
																	</span>
																) : (
																	<span className={`font-bold ${servicePrice === 0 ? 'text-green-600' : 'text-gray-700'}`}>
																		{servicePrice === 0 ? 'Бесплатно' : servicePrice ? `${servicePrice.toLocaleString()} ₽` : '—'}
																	</span>
																)}
															</div>
														</div>
														{service.description && (
															<p className="text-sm text-gray-600">{service.description}</p>
														)}
														{service.price_type === 'custom' && (
															<p className="text-xs text-gray-500 mt-1">
																Цена будет указана менеджером при обработке заказа
															</p>
														)}
													</div>
												</label>
											);
										})}
									</div>
								)}
							</div>

							{/* Payment */}
							<div className="bg-white border border-gray-200 rounded-2xl p-8">
								<h2 className="text-2xl font-bold mb-6">Способ оплаты</h2>
								<div className="space-y-4">
									{paymentMethods.map((method) => (
										<label
											key={method.id}
											className={`flex items-start gap-4 p-4 border-2 rounded-lg cursor-pointer hover:border-red-600 transition-colors ${paymentMethod === method.code ? 'border-red-600' : ''
												}`}
										>
											<input
												type="radio"
												name="payment"
												value={method.code}
												checked={paymentMethod === method.code}
												onChange={(e) => setPaymentMethod(e.target.value)}
												className="mt-1"
											/>
											<div className="flex-1">
												<div className="flex items-center gap-3 mb-2">
													{method.icon && <i className={`${method.icon} text-2xl text-red-600`}></i>}
													<span className="font-bold">{method.name}</span>
												</div>
												{method.description && (
													<p className="text-sm text-gray-600">{method.description}</p>
												)}
											</div>
										</label>
									))}
								</div>
							</div>

							{/* Comment */}
							<div className="bg-white border border-gray-200 rounded-2xl p-8">
								<h2 className="text-2xl font-bold mb-6">Комментарий к заказу</h2>
								<textarea
									rows={4}
									maxLength={1000}
									value={comment}
									onChange={(e) => setComment(e.target.value)}
									className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none resize-none"
									placeholder="Укажите дополнительную информацию для курьера..."
								></textarea>
							</div>
						</div>

						{/* Order Summary */}
						<div className="lg:col-span-1">
							<div className="bg-white border border-gray-200 rounded-2xl p-6 sticky top-4">
								<h3 className="text-xl font-bold mb-6">Ваш заказ</h3>
								<div className="space-y-4 mb-6">
									{cart && cart.items && cart.items.length > 0 ? (
										cart.items.map((item) => (
											<div key={item.id} className="flex justify-between text-sm">
												<span className="text-gray-700">
													{item.name || `Товар #${item.product_id}`} × {item.quantity}
												</span>
												<span className="font-medium">{item.total.toLocaleString()} ₽</span>
											</div>
										))
									) : (
										<p className="text-gray-500 text-sm">Загрузка товаров...</p>
									)}
								</div>
								<div className="border-t border-gray-200 pt-4 space-y-3 mb-6">
									<div className="flex justify-between">
										<span className="text-gray-600">Товары</span>
										<span className="font-medium">{cart?.subtotal ? cart.subtotal.toLocaleString() : '0'} ₽</span>
									</div>
									<div className="flex justify-between">
										<span className="text-gray-600">Доставка</span>
										<span className={`font-medium ${deliveryCost === 0 && deliveryCostKnown ? 'text-green-600' : ''}`}>
											{isCalculating
												? 'Расчёт…'
												: !deliveryCostKnown
													? '—'
													: deliveryCost === 0
														? 'Бесплатно'
														: `${deliveryCost.toLocaleString()} ₽`}
										</span>
									</div>
									{assemblyNeeded && (
										<div className="flex justify-between">
											<span className="text-gray-600">Сборка</span>
											<span className={`font-medium ${assemblyCost === 0 ? 'text-green-600' : ''}`}>
												{assemblyCost === 0 ? 'Бесплатно' : `${assemblyCost.toLocaleString()} ₽`}
											</span>
										</div>
									)}
									{selectedAdditionalServices.size > 0 && (
										<div className="flex flex-col gap-2">
											{Array.from(selectedAdditionalServices.entries()).map(([serviceId, serviceData]) => {
												const service = additionalServices.find(s => s.id === serviceId);
												if (!service) return null;
												const servicePrice = serviceData.price ?? service.price ?? 0;
												return (
													<div key={serviceId} className="flex justify-between">
														<span className="text-gray-600">{service.name}</span>
														<span className="font-medium">
															{service.price_type === 'from' ? `от ${servicePrice.toLocaleString()} ₽` :
																servicePrice === 0 ? 'Бесплатно' :
																	service.price_type === 'custom' ? 'По договоренности' :
																		`${servicePrice.toLocaleString()} ₽`}
														</span>
													</div>
												);
											})}
										</div>
									)}
									<div className="border-t border-gray-200 pt-3">
										<div className="flex justify-between items-center">
											<span className="text-lg font-bold">Итого</span>
											<span className="text-3xl font-bold text-red-600">{total.toLocaleString()} ₽</span>
										</div>
									</div>
								</div>

								<button
									type="submit"
									disabled={isSubmitting || isCalculating}
									className="w-full bg-red-600 text-white py-4 rounded-lg font-medium text-lg hover:bg-red-700 transition-colors mb-4 whitespace-nowrap disabled:bg-gray-400 disabled:cursor-not-allowed"
								>
									{isSubmitting ? 'Оформление...' : 'Подтвердить заказ'}
								</button>

								<p className="text-xs text-gray-500 text-center">
									Нажимая кнопку, вы соглашаетесь с условиями оферты и политикой конфиденциальности
								</p>
							</div>
						</div>
					</div>
				</form>
			</div>


			{/* Модалка об автоматической регистрации */}
			<AccountCreatedModal
				isOpen={showAccountCreatedModal}
				onClose={() => setShowAccountCreatedModal(false)}
				orderId={createdOrderId}
			/>
		</div>
	);
}
