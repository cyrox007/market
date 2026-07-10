import { useState, useEffect, useLayoutEffect, useCallback, useRef, useMemo } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import useSWR from 'swr';
import ReviewModal from '../../components/feature/ReviewModal';
//import ProductLink from '../../components/ui/ProductLink';
import ProductCard from '../../components/ui/ProductCard';
import ProductGallery from '../../components/product/ProductGallery';
import ProductBundleSection from '../../components/product/ProductBundleSection';
import VariantAttributeSelector from '../../components/product/VariantAttributeSelector';
import { api } from '../../lib/api';
import { useCart } from '../../hooks/useCart';
import { useCartActions } from '../../hooks/useCartActions';
import { useCounters } from '../../hooks/useCounters';
import { useRegion } from '../../hooks/useRegion';
import { useWishlistAndCompare } from '../../hooks/useWishlistAndCompare';
import { usePrefetchProduct } from '../../hooks/usePrefetchProduct';
import { useSSR } from '../../contexts/SSRContext';
import { usePageSeo } from '../../hooks/usePageSeo';
import { getProductKey } from '../../utils/ssr-to-swr';
import { resolveProductStockBadge } from '../../utils/productUtils';
import type { ProductDetail, Product, ProductVariant, Review, /* VariationAttributeOption, */ SelectedVariationItem, VariationAttributeOption } from '../../lib/api';
import VariantColorSelector from '../../components/product/VariantColorSelector';

const tabs = [
	{ id: 'description', label: 'Описание', icon: 'ri-file-text-line' },
	{ id: 'specs', label: 'Характеристики', icon: 'ri-list-check' },
	{ id: 'delivery', label: 'Доставка', icon: 'ri-truck-line' },
	{ id: 'reviews', label: 'Отзывы', icon: 'ri-chat-3-line' }
];

type ProductSpecification = { name: string; value: string; slug: string };

/** Нормализует specifications к массиву { name, value, slug } (API может отдавать объект или массив). */
function getSpecificationsArray(
	specifications: ProductDetail['specifications'] | ProductVariant['specifications'] | null | undefined,
	specificationNames?: Record<string, string>,
): ProductSpecification[] {
	if (!specifications) return [];
	if (Array.isArray(specifications)) return specifications as ProductSpecification[];
	const names = specificationNames ?? {};
	return Object.entries(specifications).map(([slug, value]) => ({
		slug,
		name: names[slug] ?? slug,
		value: String(value ?? ''),
	}));
}

function hasMeaningfulProductText(value: string | null | undefined): boolean {
	if (!value) return false;
	return value
		.replace(/<[^>]+>/g, ' ')
		.replace(/&nbsp;/gi, ' ')
		.replace(/\s+/g, ' ')
		.trim().length > 0;
}

/** Описание есть у родителя или хотя бы у одной вариации — вкладку не прячем при смене ТП. */
function productHasAnyDescription(product: ProductDetail): boolean {
	if (hasMeaningfulProductText(product.description)) {
		return true;
	}

	return (product.variants ?? []).some(
		(variant) => hasMeaningfulProductText(variant.description) || hasMeaningfulProductText(variant.excerpt),
	);
}

export default function Product() {
	const { slug } = useParams<{ slug: string }>();
	const navigate = useNavigate();
	const ssrData = useSSR();

	// Используем SSR данные если они есть
	const initialProduct = ssrData?.product?.product || null;
	const initialVariantInfo = ssrData?.product?.variant_info || null;
	const ssrRegion = ssrData?.region;
	const hasSSRProduct = !!initialProduct;

	/** region_id, с которым был загружен текущий товар; нужен чтобы при восстановлении региона перезапросить цены */
	const lastFetchRegionIdRef = useRef<number | null | undefined>(
		hasSSRProduct ? (ssrRegion?.id ?? null) : undefined,
	);

	// Инициализируем product сразу из SSR данных, если они есть
	// Это критично для предотвращения показа "Товар не найден"
	const initialProductForState = hasSSRProduct ? initialProduct : null;

	const [product, setProduct] = useState<ProductDetail | null>(initialProductForState);
	// isLoading = false если есть SSR данные, иначе true
	const [isLoading, setIsLoading] = useState(!hasSSRProduct);

	// Выбранная комбинация атрибутов вариации: attribute_slug -> value_slug (опции берём из product.variation_attributes)
	const [selectedVariation, setSelectedVariation] = useState<Record<string, string>>({});
	const [selectedVariantState, setSelectedVariantState] = useState<ProductVariant | null>(null);

	useLayoutEffect(() => {
		if (hasSSRProduct && initialProduct && (!slug || initialProduct.slug === slug) && !product) {
			setProduct(initialProduct);
			setIsLoading(false);
			const sel = initialProduct.selected_variation;
			if (sel?.length) {
				setSelectedVariation(sel.reduce((acc: Record<string, string>, s: SelectedVariationItem) => { acc[s.attribute_slug] = s.value_slug; return acc; }, {}));
			}
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	const [activeTab, setActiveTab] = useState('description');
	const [selectedImage, setSelectedImage] = useState(0);
	const [quantity, setQuantity] = useState(1);
	const prevSlugRef = useRef<string | undefined>(undefined);

	useEffect(() => {
		if (!slug) return;

		const slugChanged = prevSlugRef.current !== undefined && prevSlugRef.current !== slug;
		prevSlugRef.current = slug;

		if (slugChanged) {
			// Не показываем остатки/кнопки прошлого товара, пока не подтянулись данные нового
			setProduct(null);
			setIsLoading(true);
			setSelectedVariation({});
		}

		setSelectedImage(0);
		setQuantity(1);
		setActiveTab('description');
		setShowShareMenu(false);
		setAddToCartError(null);
	}, [slug]);

	useEffect(() => {
		setSelectedImage(0);
	}, [selectedVariation]);

	/** Вариация подходит, если все её атрибуты совпадают с выбранными (у вариации может быть меньше атрибутов, чем у товара). */
	const variantMatchesSelection = useCallback((v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number }, selection: Record<string, string>): boolean => {
		const va = v.variation_attributes ?? [];
		if (va.length === 0) return Object.keys(selection).length === 0;

		// Проверяем, что все атрибуты вариации совпадают с выбранными
		// Важно: проверяем только те атрибуты, которые есть у вариации
		// Игнорируем лишние атрибуты в selection - они могут быть из других вариаций
		for (const a of va) {
			const selectedVal = selection[a.attribute_slug];
			// Если атрибут не выбран или выбранное значение не совпадает - вариация не подходит
			if (selectedVal === undefined || selectedVal === '' || a.value_slug !== selectedVal) {
				return false;
			}
		}

		return true;
	}, []);

	// Подставляем атрибуты вариации: среди совместимых с текущим выбором берём с макс. числом атрибутов (чтобы показывать материал, размер и т.д.)
	useEffect(() => {
		if (!slug || !product || product.slug !== slug) return;
		if (!product?.is_variable || product?.is_variant || !product.variants?.length) return;
		const va = product.variants;

		// Фильтруем только вариации в наличии
		const inStockVariants = va.filter(
			(v: ProductVariant) => v.in_stock === true && (v.stock ?? 0) > 0,
		);
		if (inStockVariants.length === 0) return;

		// Совместима, если по всем атрибутам вариации выбор не противоречит (нет значения или совпадает)
		const compatible = (v: ProductVariant) => {
			const attrs = v.variation_attributes ?? [];
			if (attrs.length === 0) return Object.keys(selectedVariation).length === 0;
			for (const a of attrs) {
				const sel = selectedVariation[a.attribute_slug];
				if (sel !== undefined && sel !== '' && sel !== a.value_slug) return false;
			}
			return true;
		};

		const matching = inStockVariants.filter(compatible);

		// Сначала ищем точное совпадение (variantMatchesSelection)
		const exactMatch = matching.find((v: ProductVariant) => variantMatchesSelection(v, selectedVariation));
		if (exactMatch?.variation_attributes?.length) {
			const fromVariant = exactMatch.variation_attributes.reduce((acc: Record<string, string>, a: SelectedVariationItem) => {
				acc[a.attribute_slug] = a.value_slug;
				return acc;
			}, {});

			setSelectedVariation((prev) => {
				// Проверяем, нужно ли обновлять
				let needsUpdate = false;
				for (const [k, val] of Object.entries(fromVariant)) {
					if (prev[k] !== val) {
						needsUpdate = true;
						break;
					}
				}
				if (!needsUpdate) return prev;
				// Сохраняем текущие значения и добавляем/обновляем только атрибуты из вариации
				// Это важно: не удаляем атрибуты, которых нет у вариации, но есть в выборе
				return { ...prev, ...fromVariant };
			});
			return;
		}

		// Если точного совпадения нет, но есть совместимые - берем самую богатую
		if (matching.length > 0) {
			const variant = matching.reduce((best, v) =>
				(v.variation_attributes?.length ?? 0) > (best.variation_attributes?.length ?? 0) ? v : best
			);

			if (variant?.variation_attributes?.length) {
				const fromVariant = variant.variation_attributes.reduce((acc: Record<string, string>, a: SelectedVariationItem) => {
					acc[a.attribute_slug] = a.value_slug;
					return acc;
				}, {});

				setSelectedVariation((prev) => {
					// Проверяем, нужно ли обновлять
					let needsUpdate = false;
					for (const [k, val] of Object.entries(fromVariant)) {
						if (prev[k] !== val) {
							needsUpdate = true;
							break;
						}
					}
					if (!needsUpdate) return prev;
					// Сохраняем текущие значения и добавляем/обновляем только атрибуты из вариации
					return { ...prev, ...fromVariant };
				});
			}
		}
	}, [product?.id, product?.is_variable, product?.is_variant, product?.variants, selectedVariation]);

	const [showShareMenu, setShowShareMenu] = useState(false);
	const [isAddingToCart, setIsAddingToCart] = useState(false);
	const [addToCartError, setAddToCartError] = useState<string | null>(null);
	const [isLoadingVariant, setIsLoadingVariant] = useState(false);
	const [isReviewModalOpen, setIsReviewModalOpen] = useState(false);
	const { cart, addToCart, updateQuantity, removeFromCart } = useCart();
	const { changeProductQuantity } = useCartActions();
	const { refreshWishlistCount, refreshCompareCount } = useCounters();
	const { region: clientRegion, getRegionId } = useRegion();
	const region = ssrRegion || clientRegion;
	const regionId = getRegionId();
	const prefetchProduct = usePrefetchProduct();

	const productKey = slug
		? getProductKey(slug, { region_id: regionId ?? undefined })
		: null;

	const ssrProductPayload =
		hasSSRProduct && initialProduct && initialProduct.slug === slug
			? { product: initialProduct, variant_info: initialVariantInfo, bundle: ssrData?.product?.bundle, related: ssrData?.product?.related }
			: undefined;

	const {
		data: productPayload,
		error: productLoadError,
		isLoading: isProductFetching,
		mutate: mutateProduct,
	} = useSWR(
		productKey,
		() => api.products.get(slug!, { region_id: regionId ?? undefined }),
		{
			fallbackData: ssrProductPayload,
			revalidateOnMount: !ssrProductPayload,
			revalidateOnFocus: false,
			dedupingInterval: 60_000,
			keepPreviousData: true,
		},
	);

	const relatedProducts = useMemo(
		() => (Array.isArray(productPayload?.related?.data) ? productPayload.related.data : []),
		[productPayload?.related],
	);

	const bundleProducts = useMemo(
		() => (Array.isArray(productPayload?.bundle?.data) ? productPayload.bundle.data : []),
		[productPayload?.bundle],
	);

	usePageSeo(product?.seo);
	const { wishlistProductIds: favorites, compareProductIds: compareList, mutateWishlist, mutateCompare } = useWishlistAndCompare();

	const getReviewProductId = (): number | null => {
		if (!product) return null;
		return product.parent_id || product.id;
	};

	const { data: stockSettingsData } = useSWR(
		'/api/stock-settings',
		() => api.stockSettings.get(),
		{ revalidateOnFocus: false, dedupingInterval: 60_000 }
	);
	const stockSettings = stockSettingsData?.stock_settings ?? {
		stock_low_max: 1,
		stock_medium_max: 5,
		stock_high_max: 10,
		show_exact_above: 0,
	};

	const reviewProductId = getReviewProductId();
	const { data: reviewsData, isLoading: isLoadingReviews, mutate: mutateReviews } = useSWR(
		activeTab === 'reviews' && reviewProductId ? `/api/products/${reviewProductId}/reviews` : null,
		() => api.reviews.list(reviewProductId!),
		{ revalidateOnFocus: false, dedupingInterval: 30_000 }
	);
	const reviews: Review[] = reviewsData?.data || [];

	useLayoutEffect(() => {
		if (!slug || !productPayload?.product || productPayload.product.slug !== slug) {
			return;
		}

		const data = productPayload;
		const initialFeatureBlocks = data.product.feature_blocks || [];
		const initialDeliveryBlocks = data.product.delivery_blocks || [];

		setProduct((prevProduct) => ({
			...data.product,
			feature_blocks:
				data.product.feature_blocks !== undefined && data.product.feature_blocks !== null
					? data.product.feature_blocks
					: (prevProduct?.feature_blocks || initialFeatureBlocks),
			delivery_blocks:
				data.product.delivery_blocks !== undefined && data.product.delivery_blocks !== null
					? data.product.delivery_blocks
					: (prevProduct?.delivery_blocks || initialDeliveryBlocks),
		}));

		if (data.product.selected_variation?.length) {
			setSelectedVariation(
				data.product.selected_variation.reduce((acc: Record<string, string>, s: SelectedVariationItem) => {
					acc[s.attribute_slug] = s.value_slug;
					return acc;
				}, {}),
			);
		} else if (product?.slug !== slug) {
			setSelectedVariation({});
		}

		lastFetchRegionIdRef.current = regionId ?? null;
		setIsLoading(false);
	}, [productPayload, slug, regionId, product?.slug]);

	useEffect(() => {
		if (!slug) return;
		if (product?.slug === slug && productPayload?.product) {
			return;
		}
		if (isProductFetching && !productPayload?.product) {
			setIsLoading(true);
		}
	}, [slug, isProductFetching, productPayload, product?.slug]);

	useEffect(() => {
		if (!slug) return;

		const handleRegionChange = () => {
			setIsLoadingVariant(true);
			mutateProduct()
				.catch((err) => console.error('Failed to reload product:', err))
				.finally(() => setIsLoadingVariant(false));
		};

		window.addEventListener('region-changed', handleRegionChange);
		return () => {
			window.removeEventListener('region-changed', handleRegionChange);
		};
	}, [slug, mutateProduct]);

	/** В корзину отправляем только атрибуты той вариации, которая реально выбрана (у вариации может быть меньше атрибутов). */
	const getVariationAttributesForCart = (): { attribute_slug: string; value_slug: string }[] | undefined => {
		if (!product?.is_variable || product.is_variant) return undefined;

		const variant = getSelectedVariant();
		if (!variant) {
			// Если вариация не найдена, пытаемся найти любую совместимую вариацию в наличии
			const inStockVariants = (product.variants ?? []).filter(
				(v: ProductVariant) => v.in_stock === true && (v.stock ?? 0) > 0,
			);
			if (inStockVariants.length === 0) return undefined;

			// Ищем вариацию, которая совместима с текущим выбором
			const compatible = (v: ProductVariant) => {
				const va = v.variation_attributes ?? [];
				if (va.length === 0) return Object.keys(selectedVariation).length === 0;
				for (const a of va) {
					const sel = selectedVariation[a.attribute_slug];
					if (sel !== undefined && sel !== '' && sel !== a.value_slug) return false;
				}
				return true;
			};

			const matching = inStockVariants.filter(compatible);
			if (matching.length > 0) {
				// Берем вариацию с максимальным числом атрибутов
				const bestVariant = matching.reduce((best, v) =>
					(v.variation_attributes?.length ?? 0) > (best.variation_attributes?.length ?? 0) ? v : best
				);
				if (bestVariant?.variation_attributes?.length) {
					return bestVariant.variation_attributes.map((a: SelectedVariationItem) => ({ attribute_slug: a.attribute_slug, value_slug: a.value_slug }));
				}
			}

			return undefined;
		}

		// Используем атрибуты найденной вариации
		if (variant.variation_attributes?.length) {
			return variant.variation_attributes.map((a: SelectedVariationItem) => ({ attribute_slug: a.attribute_slug, value_slug: a.value_slug }));
		}

		return undefined;
	};

	const handleBuyNow = async () => {
		if (!product || !slug || product.slug !== slug) return;

		if (product.is_variable && !product.is_variant && product.variation_attributes?.length) {
			const variationAttrs = getVariationAttributesForCart();
			if (!variationAttrs || variationAttrs.length === 0) {
				setAddToCartError('Выберите доступную комбинацию параметров');
				setTimeout(() => setAddToCartError(null), 5000);
				return;
			}

			// Дополнительная проверка: убеждаемся, что вариация найдена
			const variant = getSelectedVariant();
			if (!variant) {
				setAddToCartError('Выберите доступную комбинацию параметров');
				setTimeout(() => setAddToCartError(null), 5000);
				return;
			}
		}

		try {
			setIsAddingToCart(true);
			setAddToCartError(null);
			const variationAttrs = getVariationAttributesForCart();
			await addToCart(product.id, quantity, variationAttrs);
			navigate('/cart');
		} catch (err: any) {
			console.error('Failed to add to cart:', err);
			const msg = err?.data?.message || (err instanceof Error ? err.message : 'Ошибка добавления в корзину');
			setAddToCartError(typeof msg === 'string' ? msg : 'Ошибка добавления в корзину');
		} finally {
			setIsAddingToCart(false);
		}
	};

	const handleAddToCart = async () => {
		if (!product || !slug || product.slug !== slug) return;

		if (product.is_variable && !product.is_variant && product.variation_attributes?.length) {
			const variationAttrs = getVariationAttributesForCart();
			if (!variationAttrs || variationAttrs.length === 0) {
				setAddToCartError('Выберите доступную комбинацию параметров');
				setTimeout(() => setAddToCartError(null), 5000);
				return;
			}

			// Дополнительная проверка: убеждаемся, что вариация найдена
			const variant = getSelectedVariant();
			if (!variant) {
				setAddToCartError('Выберите доступную комбинацию параметров');
				setTimeout(() => setAddToCartError(null), 5000);
				return;
			}
		}

		let isAvailable = product.in_stock === true;
		if (product.is_variable && !product.is_variant && product.variants) {
			const selectedVariant = getSelectedVariant();
			if (selectedVariant) {
				isAvailable =
					selectedVariant.in_stock === true && (selectedVariant.stock ?? 0) > 0;
			} else {
				isAvailable = false;
			}
		}

		if (!isAvailable) {
			setAddToCartError('Товар недоступен для заказа');
			setTimeout(() => {
				setAddToCartError(null);
			}, 5000);
			return;
		}

		try {
			setIsAddingToCart(true);
			setAddToCartError(null);
			const variationAttrs = getVariationAttributesForCart();
			const result = await addToCart(product.id, quantity, variationAttrs);

			// Показываем сообщение о корректировке количества, если было
			if (result.was_adjusted) {
				alert(result.message);
			}
		} catch (err: any) {
			console.error('Failed to add to cart:', err);
			const apiMessage = err?.data?.message;
			if (typeof apiMessage === 'string') {
				setAddToCartError(apiMessage);
			} else if (err?.status === 404 || (err?.message && String(err.message).includes('404'))) {
				setAddToCartError('Выбранная комбинация недоступна. Проверьте параметры.');
			} else if (err?.message && String(err.message).includes('422')) {
				setAddToCartError(err?.data?.message || 'Товар недоступен для заказа');
			} else {
				setAddToCartError(err instanceof Error ? err.message : 'Ошибка добавления в корзину');
			}
			setTimeout(() => setAddToCartError(null), 5000);
		} finally {
			setIsAddingToCart(false);
		}
	};

	const scrollToProductDetails = (tab?: string) => {
		if (tab) {
			setActiveTab(tab);
		}
		const element = document.getElementById('product-details');
		if (element) {
			element.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	const scrollToProductBundle = () => {
		const element = document.getElementById('product-bundle');
		if (element) {
			element.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	const handleShare = (platform: string) => {
		if (!product) return;

		const url = window.location.href;
		const text = `${product.name} - ${formatPrice(product.price)}`;

		switch (platform) {
			case 'vk':
				window.open(`https://vk.com/share.php?url=${encodeURIComponent(url)}&title=${encodeURIComponent(text)}`, '_blank');
				break;
			case 'telegram':
				window.open(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`, '_blank');
				break;
			case 'whatsapp':
				window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`, '_blank');
				break;
			case 'copy':
				navigator.clipboard.writeText(url);
				alert('Ссылка скопирована!');
				break;
		}
		setShowShareMenu(false);
	};

	const formatPrice = (price: number) => {
		return new Intl.NumberFormat('ru-RU').format(price) + ' ₽';
	};

	// Вспомогательная функция для определения ID товара для избранного
	const getProductIdForWishlist = (product: Product): number => {
		return product.id;
	};

	// Вспомогательная функция для определения ID товара для сравнения
	const getProductIdForCompare = (product: Product): number => {
		if (product.is_variable && !product.is_variant && product.first_available_variant_id) {
			return product.first_available_variant_id;
		}
		return product.id;
	};

	const toggleFavorite = async (product: Product) => {
		try {
			const productIdToAdd = getProductIdForWishlist(product);
			if (!productIdToAdd || productIdToAdd === 0) {
				console.error('Invalid product ID for wishlist:', product);
				return;
			}
			await api.wishlist.toggle(productIdToAdd);
			await mutateWishlist();
			await refreshWishlistCount();
		} catch (error) {
			console.error('Failed to toggle favorite:', error);
		}
	};

	const toggleCompare = async (product: Product) => {
		try {
			const productIdToAdd = getProductIdForCompare(product);
			const isInCompare = compareList.includes(productIdToAdd);
			if (isInCompare) {
				await api.compare.remove(productIdToAdd);
			} else {
				await api.compare.add(productIdToAdd);
			}
			await mutateCompare();
			await refreshCompareCount();
		} catch (error: any) {
			console.error('Failed to toggle compare:', error);
			if (error.status === 422) {
				alert(error.data?.message || 'Не удалось добавить товар в сравнение.');
			}
		}
	};

	const handleAddRelatedToCart = async (productId: number) => {
		try {
			await addToCart(productId, 1);
		} catch (error) {
			console.error('Failed to add to cart:', error);
		}
	};

	const relatedCartQuantityByProductId = useMemo(() => {
		const quantities: Record<number, number> = {};
		for (const item of cart?.items ?? []) {
			quantities[item.product_id] = (quantities[item.product_id] ?? 0) + item.quantity;
		}
		return quantities;
	}, [cart?.items]);

	const updateRelatedCartQuantityByProduct = async (productId: number, delta: number) => {
		const item = relatedProducts.find((p) => p.id === productId) ?? bundleProducts.find((p) => p.id === productId);
		if (!item) return;
		try {
			await changeProductQuantity(item, delta);
		} catch {
			// toast в useCartActions
		}
	};

	/** Выбор значения одного атрибута вариации. При клике по недоступному (opacity) — переключаем на первую доступную вариацию с этим значением. */
	const handleSelectVariationAttribute = (attributeSlug: string, valueSlug: string, isDisabledClick = false) => {
		if (!valueSlug || !product?.variants?.length) return;

		if (isDisabledClick) {
			const firstAvailable = product.variants.find(
				(v: ProductVariant) =>
					(v.in_stock ?? true) &&
					(v.stock ?? 0) > 0 &&
					v.variation_attributes?.some((a: SelectedVariationItem) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug)
			);
			if (firstAvailable?.variation_attributes?.length) {
				const next = firstAvailable.variation_attributes.reduce((acc: Record<string, string>, a: SelectedVariationItem) => {
					acc[a.attribute_slug] = a.value_slug;
					return acc;
				}, {});
				setSelectedVariation(next);
			}
			return;
		}

		setSelectedVariation((prev) => {
			const attrSlugs = product?.variation_attributes?.map((a: VariationAttributeOption) => a.attribute_slug) ?? [];
			if (!attrSlugs.length) return { ...prev, [attributeSlug]: valueSlug };

			// Только вариации в наличии — иначе можно получить несуществующую/недоступную комбинацию
			const variantsWithThisValue = (product?.variants ?? []).filter(
				(v: ProductVariant) =>
					(v.in_stock ?? true) &&
					(v.stock ?? 0) > 0 &&
					v.variation_attributes?.some((a: SelectedVariationItem) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug)
			);

			if (variantsWithThisValue.length === 0) return prev;

			const next: Record<string, string> = { ...prev, [attributeSlug]: valueSlug };
			for (const slug of attrSlugs) {
				if (slug === attributeSlug) continue;
				const currentSlug = next[slug];
				const available = !currentSlug || variantsWithThisValue.some((v: ProductVariant) =>
					v.variation_attributes?.some((a: SelectedVariationItem) => a.attribute_slug === slug && a.value_slug === currentSlug)
				);
				if (!available) {
					const first = variantsWithThisValue[0].variation_attributes?.find((a: SelectedVariationItem) => a.attribute_slug === slug);
					if (first) next[slug] = first.value_slug;
				}
			}
			return next;
		});
	};

	/** Доступно ли значение valueSlug для атрибута attributeSlug при текущем выборе остальных атрибутов */
	const isValueAvailableForAttribute = (attributeSlug: string, valueSlug: string): boolean => {
		if (!product?.is_variable || !product.variants?.length) return true;

		// Создаем временный выбор с новым значением
		const tempSelection = { ...selectedVariation, [attributeSlug]: valueSlug };

		return product.variants.some((v: { variation_attributes?: SelectedVariationItem[]; in_stock?: boolean; stock?: number }) => {
			// Проверяем наличие в наличии
			if (!(v.in_stock ?? true) || (v.stock ?? 0) <= 0) return false;

			const va = v.variation_attributes ?? [];
			if (va.length === 0) return Object.keys(tempSelection).length === 0;

			// Проверяем, что вариация имеет это значение
			const hasThis = va.some((a: SelectedVariationItem) => a.attribute_slug === attributeSlug && a.value_slug === valueSlug);
			if (!hasThis) return false;

			// Проверяем совместимость с остальными выбранными атрибутами
			// Вариация совместима, если все её атрибуты не противоречат выбору
			for (const a of va) {
				const selectedVal = tempSelection[a.attribute_slug];
				if (selectedVal !== undefined && selectedVal !== '' && a.value_slug !== selectedVal) {
					return false;
				}
			}

			return true;
		});
	};

	const getSelectedVariant = (): ProductVariant | null => {
		if (!product || !slug || product.slug !== slug) return null;
		if (product.is_variant || !product.is_variable || !product.variants?.length) return null;

		// Фильтруем только вариации в наличии
		const inStockVariants = product.variants.filter(
			(v: ProductVariant) => v.in_stock === true && (v.stock ?? 0) > 0,
		);
		if (inStockVariants.length === 0) return null;

		const matching = inStockVariants.filter((v: ProductVariant) => variantMatchesSelection(v, selectedVariation));
		if (matching.length === 0) {
			// Если ничего не выбрано и есть только одна вариация - возвращаем её
			if (Object.keys(selectedVariation).length === 0 && inStockVariants.length === 1) {
				return inStockVariants[0];
			}
			return null;
		}

		// Из подходящих берём вариацию с максимальным числом атрибутов — чтобы отображались все блоки (материал, размер и т.д.)
		return matching.reduce((best, v) =>
			(v.variation_attributes?.length ?? 0) > (best.variation_attributes?.length ?? 0) ? v : best
		);
	};

	const getVariantForImages = (): { images: string[] } | null => {
		if (!product?.is_variable || product?.is_variant || !product?.variants?.length) return null;
		const variant = getSelectedVariant();
		return variant?.images?.length ? { images: variant.images } : null;
	};

	const hasDescriptionTab = useMemo(() => {
		if (!product || !slug || product.slug !== slug) return true;
		return productHasAnyDescription(product);
	}, [product, slug]);

	const visibleTabs = useMemo(
		() => tabs.filter((tab) => tab.id !== 'description' || hasDescriptionTab),
		[hasDescriptionTab],
	);

	useEffect(() => {
		if (!hasDescriptionTab && activeTab === 'description') {
			setActiveTab('specs');
		}
	}, [hasDescriptionTab, activeTab]);

	const isProductResolved = Boolean(product && slug && product.slug === slug);

	const productPageSkeleton = (
		<div className="min-h-screen bg-white">
			<div className="max-w-[1280px] mx-auto px-4 py-12">
				<div className="animate-pulse">
					<div className="h-8 bg-gray-200 rounded w-64 mb-6" />
					<div className="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6">
						<div className="h-[600px] bg-gray-200 rounded-2xl" />
						<div className="h-96 bg-gray-200 rounded-2xl" />
					</div>
				</div>
			</div>
		</div>
	);

	if (!isProductResolved) {
		if (isProductFetching || isLoading) {
			return productPageSkeleton;
		}
		if (!product) {
			return (
				<div className="min-h-screen bg-white">
					<div className="max-w-[1280px] mx-auto px-4 py-12">
						<h1 className="text-2xl font-bold">Товар не найден</h1>
					</div>
				</div>
			);
		}
		return productPageSkeleton;
	}

	// Фото галереи: при выборе цвета/размера — фото вариации, если у неё есть фото; иначе всегда галерея основного товара
	const variantForImages = getVariantForImages();
	const hasVariantImages = variantForImages?.images && variantForImages.images.length > 0;
	const productImages = hasVariantImages
		? variantForImages!.images
		: (product?.images?.length ? product.images : product?.image_hd ? [product.image_hd] : product?.image ? [product.image] : []);

	// Вычисляем выбранную вариацию и все отображаемые данные ОДИН РАЗ перед рендером
	const selectedVariant = selectedVariantState ?? getSelectedVariant() ?? (
		product?.is_variant && product.variants?.length
			? product.variants.find((variant) => variant.id === product.id) ?? null
			: null
	);

	// Derived state: все данные для отображения вычисляются из product + selectedVariant
	const displayPrice = selectedVariant?.price ?? product?.price;
	const displayOldPrice = selectedVariant?.old_price ?? product?.old_price;
	const displaySku = selectedVariant?.sku ?? product?.sku;
	const bundleSetTotalPrice = bundleProducts.length === 0
		? null
		: (displayPrice ?? 0) + bundleProducts.reduce((sum, item) => sum + (item.price ?? 0), 0);
	const stockBadge = resolveProductStockBadge({
		product,
		selectedVariant,
		stockSettings: {
			...stockSettings,
			show_exact_above: 0,
		},
	});
	const displayDescription = selectedVariant && hasMeaningfulProductText(selectedVariant.description)
		? selectedVariant.description!
		: product?.description;
	/* const displayExcerpt = selectedVariant && hasMeaningfulProductText(selectedVariant.excerpt)
		? selectedVariant.excerpt!
		: product.excerpt; */
	const variantSpecs = selectedVariant
		? getSpecificationsArray(selectedVariant.specifications)
		: [];
	const displaySpecifications = variantSpecs.length > 0
		? variantSpecs
		: getSpecificationsArray(
			product?.specifications,
			(product as { specification_names?: Record<string, string> }).specification_names,
		);
	const cartProductId = selectedVariant?.id ?? product?.id;
	const currentCartItem = (cart?.items ?? []).find((item) => item.product_id === cartProductId);
	const currentCartQuantity = currentCartItem?.quantity ?? 0;
	const currentWishlistProductId = getProductIdForWishlist(product);
	const currentCompareProductId = getProductIdForCompare(product);
	const isInWishlist = favorites.includes(currentWishlistProductId);
	const isInCompare = compareList.includes(currentCompareProductId);

	return (
		<div className="min-h-screen bg-white">

			<div className="max-w-[1280px] mx-auto px-4 py-6">
				{/* Breadcrumbs */}
				<div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
					<Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
					<i className="ri-arrow-right-s-line text-gray-400"></i>
					<Link to="/catalog" className="text-gray-600 hover:text-red-600">Каталог</Link>
					{product?.category && (
						<>
							<i className="ri-arrow-right-s-line text-gray-400"></i>
							<Link to={`/catalog/${product.category.slug}`} className="text-gray-600 hover:text-red-600">{product.category.name}</Link>
						</>
					)}
					<i className="ri-arrow-right-s-line text-gray-400"></i>
					<span className="text-gray-900">{product?.name}</span>
				</div>

				{/* Product Main Info - New Layout */}
				<div className="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-6 mb-12">
					{/* Left: Images and Details */}
					<div className="flex flex-col md:flex-row gap-4">
						{/* Колонка: галерея + кнопки избранное/сравнить/поделиться */}
						<div className="flex flex-col flex-1 w-full md:max-w-[500px] order-1 md:order-2 min-w-0">
							<ProductGallery
								images={productImages}
								productName={product?.name}
								selectedIndex={selectedImage}
								onSelectIndex={setSelectedImage}
							/>
							{/* Share and Favorite Buttons */}
							<div className="flex items-center gap-2 mt-2">
								<button
									onClick={async () => {
										if (!product) return;
										try {
											// Определяем, какой товар добавлять в избранное
											// Если товар вариативный и выбрана конкретная вариация, добавляем вариацию
											let productIdToWishlist = product.id;

											if (product.is_variable && !product.is_variant) {
												const sel = getSelectedVariant();
												productIdToWishlist = sel?.id ?? product.variants?.[0]?.id ?? product.id;
											} else if (product.is_variant) {
												// Это уже конкретная вариация, используем её ID
												productIdToWishlist = product.id;
											}

											await api.wishlist.toggle(productIdToWishlist);
											await mutateWishlist();
											await refreshWishlistCount();
										} catch (error) {
											console.error('Failed to toggle wishlist:', error);
										}
									}}
									className={`flex-1 flex items-center justify-center gap-2 border-2 rounded-lg py-2.5 cursor-pointer transition-colors ${isInWishlist
										? 'border-red-600 bg-red-50'
										: 'border-gray-300 hover:border-red-600 hover:bg-red-50'
										}`}
								>
									<i className={`text-xl ${isInWishlist ? 'ri-heart-fill text-red-600' : 'ri-heart-line text-red-600'}`}></i>
									<span className="text-sm font-medium">{isInWishlist ? 'В избранном' : 'В избранное'}</span>
								</button>
								<button
									onClick={async () => {
										if (!product) return;
										try {
											// Определяем, какой товар добавлять в сравнение
											// Если товар вариативный и выбрана конкретная вариация, добавляем вариацию
											let productIdToCompare = product.id;

											if (product.is_variable && !product.is_variant) {
												const sel = getSelectedVariant();
												productIdToCompare = sel?.id ?? product.variants?.[0]?.id ?? product.id;
											} else if (product.is_variant) {
												// Это уже конкретная вариация, используем её ID
												productIdToCompare = product.id;
											}

											if (compareList.includes(productIdToCompare)) {
												await api.compare.remove(productIdToCompare);
											} else {
												await api.compare.add(productIdToCompare);
											}
											await mutateCompare();
											await refreshCompareCount();
										} catch (error: any) {
											console.error('Failed to toggle compare:', error);
											if (error.status === 422) {
												alert(error.data?.message || 'Не удалось добавить товар в сравнение.');
											}
										}
									}}
									className={`flex-1 flex items-center justify-center gap-2 border-2 rounded-lg py-2.5 cursor-pointer transition-colors ${isInCompare
										? 'border-red-600 bg-red-50'
										: 'border-gray-300 hover:border-red-600 hover:bg-red-50'
										}`}
								>
									<i className={`text-xl ${isInCompare ? 'ri-scales-3-fill text-red-600' : 'ri-scales-3-line text-gray-700'}`}></i>
									<span className="text-sm font-medium">{isInCompare ? 'В сравнении' : 'Сравнить'}</span>
								</button>
								<div className="relative">
									<button
										onClick={() => setShowShareMenu(!showShareMenu)}
										className="w-12 h-12 flex items-center justify-center border-2 border-gray-300 rounded-lg hover:border-red-600 hover:bg-red-50 cursor-pointer transition-colors"
									>
										<i className="ri-share-line text-lg text-gray-700"></i>
									</button>
									{showShareMenu && (
										<div className="absolute right-0 top-14 bg-white border border-gray-200 rounded-lg shadow-lg p-2 z-10 w-48">
											<button
												onClick={() => handleShare('vk')}
												className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left"
											>
												<i className="ri-vk-fill text-xl text-blue-600"></i>
												<span className="text-sm">ВКонтакте</span>
											</button>
											<button
												onClick={() => handleShare('telegram')}
												className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left"
											>
												<i className="ri-telegram-fill text-xl text-blue-500"></i>
												<span className="text-sm">Telegram</span>
											</button>
											<button
												onClick={() => handleShare('whatsapp')}
												className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left"
											>
												<i className="ri-whatsapp-fill text-xl text-green-600"></i>
												<span className="text-sm">WhatsApp</span>
											</button>
											<button
												onClick={() => handleShare('copy')}
												className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left"
											>
												<i className="ri-file-copy-line text-xl text-gray-600"></i>
												<span className="text-sm">Копировать ссылку</span>
											</button>
										</div>
									)}
								</div>
							</div>
						</div>

						{/* Center: Product Details */}
						<div className="flex-1 space-y-4 sm:space-y-6 order-3 lg:order-3">
							{/* Product Name and Status */}
							<div>
								<div className="flex items-center gap-2 mb-3 flex-wrap" key={`status-${product?.id}-${selectedVariant?.id ?? 'parent'}-${stockBadge.stock}`}>
									<span className={`px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap ${stockBadge.badgeClass}`}>
										{stockBadge.badgeLabel}
									</span>
									{(() => {
										const selectedVariant = getSelectedVariant();
										const displaySku = selectedVariant?.sku || product?.sku;
										const variantKey = selectedVariant?.id || Object.values(selectedVariation).sort().join('-') || 'main';
										return displaySku ? (
											<span className="text-xs text-gray-500" key={`sku-top-${displaySku}-${variantKey}`}>Арт: {displaySku}</span>
										) : null;
									})()}
								</div>
								<h1 className="text-xl sm:text-2xl font-bold mb-3">{product?.name}</h1>
								<div className="flex items-center gap-4 flex-wrap">
									<div className="flex items-center gap-1">
										{[...Array(5)].map((_, i) => (
											<i
												key={i}
												className={`${i < Math.floor(product?.rating || 0) ? 'ri-star-fill' : 'ri-star-line'
													} text-yellow-500 text-base sm:text-lg`}
											></i>
										))}
										<span className="ml-2 text-gray-900 font-medium text-sm sm:text-base">{product?.rating ?? 0}</span>
									</div>
									<a href="#reviews" onClick={(e) => { e.preventDefault(); scrollToProductDetails('reviews'); }} className="text-red-600 hover:underline text-xs sm:text-sm cursor-pointer">
										{product?.reviews_count} {product?.reviews_count === 1 ? 'отзыв' : product?.reviews_count < 5 ? 'отзыва' : 'отзывов'}
									</a>
								</div>
							</div>

							{/* Вариации: все атрибуты показываем полностью; значения не из текущей вариации — с opacity, по клику переключаем на вариацию с этим значением */}
							{/* ============================================
								АТРИБУТЫ ВАРИАЦИЙ (кроме цвета)
								============================================ */}
							{product?.variation_attributes
								?.filter((a) => a.attribute_slug !== 'color' && a.values?.length)
								.map((attr) => (
									<VariantAttributeSelector
										key={attr.attribute_slug}
										attribute={attr}
										selectedValueSlug={selectedVariant ? (selectedVariation[attr.attribute_slug] ?? null) : null}
										isValueAvailable={(valueSlug) => isValueAvailableForAttribute(attr.attribute_slug, valueSlug)}
										onSelect={(valueSlug, isDisabledClick) => handleSelectVariationAttribute(attr.attribute_slug, valueSlug, isDisabledClick)}
										isDisabled={isLoadingVariant}
									/>
								))}

							{/* ============================================
								ПАЛИТРА ЦВЕТОВ (выбор варианта по цветам)
								============================================ */}
							{product?.variants && product.variants.length > 0 && (
								<VariantColorSelector
									variants={product.variants}
									selectedVariantId={selectedVariant?.id ?? null}
									onSelect={(variantId) => {
										const variant = product.variants?.find(v => v.id === variantId);
										if (variant) {
											console.log('🟢 Выбран вариант:', variant.id, variant.colors);
											setSelectedVariantState(variant); // 👈 обновляем состояние
											const attrs: Record<string, string> = {};
											variant.variation_attributes?.forEach((a) => {
												attrs[a.attribute_slug] = a.value_slug;
											});
											setSelectedVariation(attrs);
										}
									}}
								/>
							)}

							{/* Features - Grid 2x2 */}
							{(() => {
								const blocks = product?.feature_blocks;
								const hasBlocks = blocks && Array.isArray(blocks) && blocks.length > 0;
								return hasBlocks;
							})() && product?.feature_blocks && product.feature_blocks.length > 0 && (
									<div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
										{product.feature_blocks.map((block) => {
											// Маппинг цветов Tailwind для использования в style
											const colorMap: Record<string, string> = {
												'red-100': '#fee2e2',
												'red-600': '#dc2626',
												'yellow-100': '#fef3c7',
												'yellow-600': '#ca8a04',
												'green-100': '#dcfce7',
												'green-600': '#16a34a',
												'blue-100': '#dbeafe',
												'blue-600': '#2563eb',
												'gray-100': '#f3f4f6',
												'gray-600': '#4b5563',
											};

											const bgColor = colorMap[block.bg_color] || colorMap['gray-100'];
											const iconColor = colorMap[block.icon_color] || colorMap['gray-600'];

											return (
												<div key={block.id} className="flex items-center gap-2 sm:gap-3 bg-gray-50 p-2 sm:p-3 rounded-lg">
													<div
														className="w-8 h-8 sm:w-10 sm:h-10 rounded-lg flex items-center justify-center flex-shrink-0 overflow-hidden"
														style={{ backgroundColor: bgColor }}
													>
														{block.icon_image ? (
															<img
																src={block.icon_image}
																alt={block.title || ''}
																className="w-full h-full object-contain"
															/>
														) : block.icon ? (
															<i
																className={`${block.icon} text-base sm:text-lg`}
																style={{ color: iconColor }}
															></i>
														) : null}
													</div>
													<div>
														<p className="font-semibold text-xs sm:text-sm">{block.title}</p>
														{block.subtitle && (
															<p className="text-xs text-gray-600">{block.subtitle}</p>
														)}
													</div>
												</div>
											);
										})}
									</div>
								)}

							{/* Scroll to product details */}
							<button
								onClick={() => scrollToProductDetails(hasDescriptionTab ? 'description' : 'specs')}
								className="w-full border-2 border-red-600 text-red-600 py-3 rounded-lg font-medium hover:bg-red-50 transition-colors whitespace-nowrap flex items-center justify-center gap-2"
							>
								<i className={`${hasDescriptionTab ? 'ri-file-text-line' : 'ri-list-check'} text-lg`}></i>
								{hasDescriptionTab ? 'Перейти к описанию' : 'Перейти к характеристикам'}
							</button>

						</div>
					</div>

					{/* Right: Action Container */}
					<div className="bg-gray-50 rounded-2xl p-4 sm:p-6 h-fit lg:sticky lg:top-20 order-3 lg:order-3">
						{/* Stock Status */}
						<div className="mb-4" key={`stock-${product?.id}-${selectedVariant?.id ?? 'parent'}-${stockBadge.stock}-${stockBadge.stockText}`}>
							<div className="flex items-center gap-2 justify-center flex-wrap">
								<span className={`px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap ${stockBadge.badgeClass}`}>
									{stockBadge.badgeLabel}
								</span>
								{(() => {
									const selectedVariant = getSelectedVariant();
									const displaySku = selectedVariant?.sku || product?.sku;
									const variantKey = selectedVariant?.id || Object.values(selectedVariation).sort().join('-') || 'main';
									return displaySku ? (
										<span className="text-xs text-gray-500" key={`sku-${displaySku}-${variantKey}`}>Арт: {displaySku}</span>
									) : null;
								})()}
							</div>
						</div>

						{/* Price */}
						<div
							className="mb-6 text-center"
							key={`price-${product?.id}-${displayPrice}-${selectedVariant?.id || 'main'}-${bundleSetTotalPrice ?? 0}`}
						>
							<div className="flex items-center justify-center gap-2 mb-2 flex-wrap">
								<span className="text-2xl sm:text-3xl font-bold text-red-600">
									{formatPrice(displayPrice)}
								</span>
								{displayOldPrice && (
									<span className="bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap">
										-{Math.round((1 - displayPrice / displayOldPrice) * 100)}%
									</span>
								)}
							</div>
							{displayOldPrice && (
								<span className="text-sm text-gray-400 line-through">{formatPrice(displayOldPrice)}</span>
							)}
							{bundleSetTotalPrice !== null && (
								<div className="mt-4 pt-4 border-t border-gray-200">
									<p className="text-sm text-gray-600">
										Готовый комплект:{' '}
										<span className="font-semibold text-red-600">{formatPrice(bundleSetTotalPrice)}</span>
									</p>
									<p className="text-xs text-gray-400 mt-1">
										Этот товар и {bundleProducts.length}{' '}
										{bundleProducts.length === 1 ? 'позиция' : bundleProducts.length < 5 ? 'позиции' : 'позиций'}{' '}
										<button
											type="button"
											onClick={scrollToProductBundle}
											className="text-red-600 hover:text-red-700 hover:underline font-medium cursor-pointer"
										>
											ниже
										</button>
									</p>
								</div>
							)}
						</div>

						{/* Action Buttons */}
						<div className="space-y-3">
							{addToCartError && (
								<div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
									{addToCartError}
								</div>
							)}
							<div className="flex gap-2">
								{(() => {
									// Для вариативных товаров проверяем доступность выбранной вариации (в т.ч. одной вариации / по цвету или размеру)
									let isProductAvailable = product?.in_stock === true;

									if (product?.is_variable && !product.is_variant && product.variants) {
										const selectedVariant = getSelectedVariant();
										if (selectedVariant) {
											isProductAvailable =
												selectedVariant.in_stock === true &&
												(selectedVariant.stock ?? 0) > 0;
										} else {
											// Если вариация не найдена по точному совпадению, проверяем есть ли хотя бы одна совместимая вариация в наличии
											const inStockVariants = product.variants.filter(
												(v: ProductVariant) => v.in_stock === true && (v.stock ?? 0) > 0,
											);
											if (inStockVariants.length > 0) {
												// Проверяем совместимость: есть ли вариация, у которой все атрибуты не противоречат выбору
												const compatible = inStockVariants.some((v: ProductVariant) => {
													const va = v.variation_attributes ?? [];
													if (va.length === 0) return Object.keys(selectedVariation).length === 0;
													for (const a of va) {
														const sel = selectedVariation[a.attribute_slug];
														if (sel !== undefined && sel !== '' && sel !== a.value_slug) return false;
													}
													return true;
												});
												isProductAvailable = compatible;
											} else {
												isProductAvailable = false;
											}
										}
									}

									if (currentCartQuantity > 0) {
										return (
											<>
												<button
													disabled
													className="py-3 rounded-lg font-semibold transition-all whitespace-nowrap shadow-lg bg-green-600 text-white hover:bg-green-700 shadow-green-600/30 flex-shrink-0 disabled:opacity-100"
													style={{ width: '120px' }}
												>
													Добавлено
												</button>
												<div className="flex items-center gap-3 flex-1 rounded-lg px-4 animate-[fadeIn_0.3s_ease-in-out]">
													<button
														onClick={async (e) => {
															e.stopPropagation();
															if (!currentCartItem) return;
															try {
																setIsAddingToCart(true);
																const nextQuantity = currentCartItem.quantity - 1;
																if (nextQuantity <= 0) {
																	await removeFromCart(currentCartItem.id);
																	return;
																}
																await updateQuantity(currentCartItem.id, nextQuantity);
															} catch (err: any) {
																console.error('Failed to decrease cart quantity:', err);
																const msg = err?.data?.message || (err instanceof Error ? err.message : 'Ошибка изменения количества');
																setAddToCartError(typeof msg === 'string' ? msg : 'Ошибка изменения количества');
																setTimeout(() => setAddToCartError(null), 5000);
															} finally {
																setIsAddingToCart(false);
															}
														}}
														disabled={isAddingToCart}
														className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50"
													>
														<i className="ri-subtract-line"></i>
													</button>
													<span className="text-lg font-bold flex-1 text-center">{currentCartQuantity}</span>
													<button
														onClick={async (e) => {
															e.stopPropagation();
															try {
																setIsAddingToCart(true);
																if (!currentCartItem) {
																	await handleAddToCart();
																	return;
																}
																await updateQuantity(currentCartItem.id, currentCartItem.quantity + 1);
															} catch (err: any) {
																console.error('Failed to increase cart quantity:', err);
																const msg = err?.data?.message || (err instanceof Error ? err.message : 'Ошибка изменения количества');
																setAddToCartError(typeof msg === 'string' ? msg : 'Ошибка изменения количества');
																setTimeout(() => setAddToCartError(null), 5000);
															} finally {
																setIsAddingToCart(false);
															}
														}}
														disabled={isAddingToCart || !isProductAvailable}
														className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors disabled:opacity-50"
													>
														<i className="ri-add-line"></i>
													</button>
												</div>
											</>
										);
									}

									return (
										<button
											onClick={handleAddToCart}
											disabled={isAddingToCart || !isProductAvailable}
											className={`py-3 rounded-lg font-semibold transition-all whitespace-nowrap shadow-lg disabled:opacity-50 disabled:cursor-not-allowed ${!isProductAvailable
												? 'bg-gray-400 text-white cursor-not-allowed w-full'
												: 'bg-red-600 text-white hover:bg-red-700 shadow-red-600/30 w-full'
												}`}
										>
											{isAddingToCart ? (
												<span className="flex items-center justify-center gap-2">
													<div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
													Добавление...
												</span>
											) : !isProductAvailable ? (
												'Недоступно'
											) : (
												'Добавить в корзину'
											)}
										</button>
									);
								})()}
							</div>

							<button
								onClick={handleBuyNow}
								className="w-full bg-red-200 text-red-700 py-3 rounded-lg font-semibold hover:bg-red-300 transition-colors whitespace-nowrap"
							>
								Купить сейчас
							</button>

							<button
								onClick={() => navigate('/checkout')}
								className="w-full bg-white border-2 border-gray-300 text-gray-900 py-3 rounded-lg font-semibold hover:border-red-600 hover:bg-red-50 transition-colors whitespace-nowrap flex items-center justify-center gap-2"
							>
								<i className="ri-calendar-line text-lg"></i>
								Оплата частями
							</button>
						</div>
					</div>
				</div>

				{/* Tabs */}
				<div id="product-details" className="mb-12 scroll-mt-20">
					<div className="border-b border-gray-200 mb-8">
						<div className="flex gap-8 overflow-x-auto">
							{visibleTabs.map((tab) => (
								<button
									key={tab.id}
									onClick={() => setActiveTab(tab.id)}
									className={`flex items-center gap-2 pb-4 border-b-2 font-semibold cursor-pointer whitespace-nowrap transition-colors ${activeTab === tab.id
										? 'border-red-600 text-red-600'
										: 'border-transparent text-gray-600 hover:text-red-600'
										}`}
								>
									<i className={`${tab.icon} text-xl`}></i>
									<span>{tab.label}</span>
								</button>
							))}
						</div>
					</div>

					{/* Tab Content */}
					<div className="prose max-w-none">
						{activeTab === 'description' && (
							<div>
								<h3 className="text-2xl font-bold mb-4">Описание товара</h3>
								{hasMeaningfulProductText(displayDescription) ? (
									<div className="text-gray-700 leading-relaxed" dangerouslySetInnerHTML={{ __html: displayDescription! }} />
								) : (
									<p className="text-gray-700 mb-4 leading-relaxed">
										{'Описание товара отсутствует.'}
									</p>
								)}
							</div>
						)}

						{activeTab === 'specs' && (
							<div>
								<h3 className="text-2xl font-bold mb-6">Характеристики</h3>
								{(() => {
									const specs = displaySpecifications;
									return specs.length > 0 ? (
										<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
											{specs.map((spec) => (
												<div key={spec.slug} className="flex justify-between p-4 bg-gray-50 rounded-lg">
													<span className="font-semibold text-gray-900">{spec.name}</span>
													<span className="text-gray-700">{spec.value}</span>
												</div>
											))}
										</div>
									) : (
										<p className="text-gray-500">Характеристики не указаны</p>
									);
								})()}
							</div>
						)}

						{activeTab === 'delivery' && (
							<div>
								<h3 className="text-2xl font-bold mb-6">Доставка и сборка</h3>
								{(() => {
									const blocks = product?.delivery_blocks;
									const hasBlocks = blocks && Array.isArray(blocks) && blocks.length > 0;
									return hasBlocks && blocks;
								})() && product?.delivery_blocks && Array.isArray(product.delivery_blocks) ? (
									<div className="space-y-6">
										{product.delivery_blocks.map((block) => {
											// Маппинг цветов Tailwind для использования в style
											const colorMap: Record<string, string> = {
												'red-100': '#fee2e2',
												'red-600': '#dc2626',
												'yellow-100': '#fef3c7',
												'yellow-600': '#ca8a04',
												'green-100': '#dcfce7',
												'green-600': '#16a34a',
												'blue-100': '#dbeafe',
												'blue-600': '#2563eb',
												'gray-100': '#f3f4f6',
												'gray-600': '#4b5563',
											};

											const bgColor = colorMap[block.bg_color] || colorMap['gray-100'];
											const iconColor = colorMap[block.icon_color] || colorMap['gray-600'];

											return (
												<div key={block.id} className="flex gap-4">
													<div
														className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 overflow-hidden"
														style={{ backgroundColor: bgColor }}
													>
														{block.icon_image ? (
															<img
																src={block.icon_image}
																alt={block.title}
																className="w-full h-full object-contain"
															/>
														) : block.icon ? (
															<i
																className={`${block.icon} text-2xl`}
																style={{ color: iconColor }}
															></i>
														) : null}
													</div>
													<div>
														<h4 className="font-bold mb-2 text-lg">{block.title}</h4>
														{block.description && (
															<p className="text-gray-700 leading-relaxed">{block.description}</p>
														)}
													</div>
												</div>
											);
										})}
									</div>
								) : (
									<p className="text-gray-500">Информация о доставке временно недоступна</p>
								)}
							</div>
						)}

						{activeTab === 'reviews' && (
							<div>
								<div className="flex items-center justify-between mb-8">
									<h3 className="text-2xl font-bold">Отзывы покупателей</h3>
									<button
										onClick={() => setIsReviewModalOpen(true)}
										className="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors whitespace-nowrap"
									>
										Написать отзыв
									</button>
								</div>

								{isLoadingReviews ? (
									<div className="text-center py-12">
										<div className="animate-pulse">
											<div className="h-4 bg-gray-200 rounded w-64 mx-auto mb-4" />
											<div className="h-4 bg-gray-200 rounded w-48 mx-auto" />
										</div>
									</div>
								) : reviews.length === 0 ? (
									<div className="text-center py-12">
										<div className="mb-4">
											<i className="ri-chat-3-line text-6xl text-gray-300"></i>
										</div>
										<p className="text-gray-500 text-lg mb-2">Пока нет отзывов</p>
										<p className="text-gray-400 text-sm mb-6">Будьте первым, кто оставит отзыв об этом товаре!</p>
										<button
											onClick={() => setIsReviewModalOpen(true)}
											className="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors"
										>
											Написать отзыв
										</button>
									</div>
								) : (
									<div className="space-y-6">
										{reviews.map((review) => (
											<div key={review.id} className="border-b border-gray-200 pb-6">
												<div className="flex items-center justify-between mb-3">
													<div>
														<p className="font-bold text-lg">{review.name}</p>
														<p className="text-sm text-gray-500">{review.created_at}</p>
													</div>
													<div className="flex gap-1">
														{[...Array(5)].map((_, i) => (
															<i
																key={i}
																className={`${i < review.rating ? 'ri-star-fill' : 'ri-star-line'
																	} text-yellow-500 text-lg`}
															></i>
														))}
													</div>
												</div>
												<p className="text-gray-700 leading-relaxed">{review.comment}</p>
											</div>
										))}
									</div>
								)}
							</div>
						)}
					</div>
				</div>

				<div className="space-y-16">
					{/* Набор / комплект (/bundle) — выше сопутствующих */}
					{bundleProducts.length > 0 && (
						<div id="product-bundle" className="scroll-mt-20">
							<ProductBundleSection
								products={bundleProducts}
								cartQuantityByProductId={relatedCartQuantityByProductId}
								onAddToCart={handleAddRelatedToCart}
								onIncreaseCart={(productId) => updateRelatedCartQuantityByProduct(productId, 1)}
								onDecreaseCart={(productId) => updateRelatedCartQuantityByProduct(productId, -1)}
								onToggleFavorite={toggleFavorite}
								onToggleCompare={toggleCompare}
								isFavorite={(p) => favorites.includes(getProductIdForWishlist(p))}
								isInCompare={(p) => compareList.includes(getProductIdForCompare(p))}
								onPrefetch={prefetchProduct}
							/>
						</div>
					)}

					{relatedProducts.length > 0 && (
						<div>
							<h2 className="text-2xl font-bold mb-6">Сопутствующие товары</h2>
							<div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-5" data-product-shop>
								{relatedProducts.map((item, index) => (
									<ProductCard
										key={item.id}
										product={item}
										onMouseEnter={() => prefetchProduct(item.slug)}
										onAddToCart={handleAddRelatedToCart}
										onIncreaseCart={(productId) => updateRelatedCartQuantityByProduct(productId, 1)}
										onDecreaseCart={(productId) => updateRelatedCartQuantityByProduct(productId, -1)}
										onToggleFavorite={toggleFavorite}
										onToggleCompare={toggleCompare}
										cartQuantity={relatedCartQuantityByProductId[item.id] ?? 0}
										isFavorite={favorites.includes(getProductIdForWishlist(item))}
										isInCompare={compareList.includes(getProductIdForCompare(item))}
										priority={index < 4}
									/>
								))}
							</div>
						</div>
					)}
				</div>
			</div>


			{/* Review Modal */}
			{product && (
				<ReviewModal
					productId={getReviewProductId() || product.id}
					productName={product.name}
					isOpen={isReviewModalOpen}
					onClose={() => setIsReviewModalOpen(false)}
					onSuccess={() => {
						// Перезагружаем отзывы после успешной отправки
						mutateReviews();
					}}
				/>
			)}
		</div>
	);
}
