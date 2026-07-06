import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';
import { useCartActions } from '../../hooks/useCartActions';
import { getCartQuantityForProduct, isVariableParent } from '../../utils/cartProduct';
import { useCounters } from '../../hooks/useCounters';
import type { Product, WishlistItem } from '../../lib/api';
import { usePageSeo } from '../../hooks/usePageSeo';

export default function Favorites() {
	const [items, setItems] = useState<WishlistItem[]>([]);
	const [isLoading, setIsLoading] = useState(true);
	const { addProductToCart, cart } = useCartActions();
	const { refreshWishlistCount } = useCounters();

	useEffect(() => {
		const loadWishlist = async () => {
			try {
				setIsLoading(true);
				const response = await api.wishlist.list();
				setItems(response.data || []);
			} catch {
				// ignore
			} finally {
				setIsLoading(false);
			}
		};

		loadWishlist();
	}, []);

	usePageSeo({
		title: 'Избранное – Светофор-Мебель',
		description: 'Ваши избранные товары в интернет-магазине Светофор-Мебель.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'noindex, follow',
		open_graph_title: 'Избранное – Светофор-Мебель',
		locale: 'ru_RU',
	});

	const removeFromFavorites = async (productId: number) => {
		try {
			await api.wishlist.remove(productId);
			setItems(prev => prev.filter(item => item.product_id !== productId));
			// Небольшая задержка, чтобы дать время API обновиться
			await new Promise(resolve => setTimeout(resolve, 100));
			await refreshWishlistCount();
		} catch {
			// ignore
		}
	};

	const handleAddToCart = async (product: Product) => {
		if (isVariableParent(product)) return;
		await addProductToCart(product, 1);
	};

	return (
		<div className="min-h-screen bg-white">

			<div className="max-w-7xl mx-auto px-4 py-6">
				{/* Breadcrumbs */}
				<div className="flex items-center gap-2 text-sm mb-2">
					<Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
					<i className="ri-arrow-right-s-line text-gray-400"></i>
					<span className="text-gray-900">Избранное</span>
				</div>

				<div className="flex items-center justify-between mb-8">
					<h1 className="text-4xl font-bold">Избранное</h1>
					<span className="text-gray-600">{items.length} товаров</span>
				</div>

				{isLoading ? (
					<div className="text-center py-20">
						<div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
							<i className="ri-loader-4-line text-6xl text-gray-400 animate-spin"></i>
						</div>
						<h2 className="text-2xl font-bold mb-3">Загрузка...</h2>
					</div>
				) : items.length === 0 ? (
					<div className="text-center py-20">
						<div className="w-32 h-32 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
							<i className="ri-heart-line text-6xl text-gray-400"></i>
						</div>
						<h2 className="text-2xl font-bold mb-3">Список избранного пуст</h2>
						<p className="text-gray-600 mb-6">Добавляйте товары в избранное, чтобы не потерять их</p>
						<Link to="/catalog" className="inline-block bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap">
							Перейти в каталог
						</Link>
					</div>
				) : (
					<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" data-product-shop>
						{items.map((item) => {
							const product = item.product;
							return (
								<div key={item.id} className="bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-shadow">
									<div className="relative h-48">
										{product.thumbnail || product.image ? (
											<img src={product.thumbnail || product.image || ''} alt={product.name} className="w-full h-full object-cover object-top" />
										) : (
											<div className="w-full h-full flex items-center justify-center">
												<i className="ri-image-line text-3xl text-gray-400"></i>
											</div>
										)}
										<div className="absolute top-4 right-4 flex gap-2">
											<button
												onClick={(e) => {
													e.preventDefault();
													e.stopPropagation();
													// TODO: Add to compare from favorites page
												}}
												className="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 cursor-pointer"
											>
												<i className="ri-scales-3-line text-xl text-gray-600"></i>
											</button>
											<button
												onClick={() => removeFromFavorites(product.id)}
												className="w-10 h-10 bg-red-600 rounded-full flex items-center justify-center shadow-md hover:bg-red-700 cursor-pointer"
											>
												<i className="ri-heart-fill text-xl text-white"></i>
											</button>
										</div>
										{!isVariableParent(product) && (!product.in_stock || product.stock === 0) && (
											<div className="absolute inset-0 bg-black/50 flex items-center justify-center z-20">
												<span className="bg-white px-4 py-2 rounded-lg font-medium">Нет в наличии</span>
											</div>
										)}
									</div>
									<div className="p-4">
										<h3 className="font-semibold mb-3 min-h-[48px]">{product.name}</h3>
										<div className="flex items-center gap-2 mb-4">
											<span className="text-xl font-bold text-red-600">{product.price.toLocaleString()} ₽</span>
											{product.old_price && (
												<span className="text-sm text-gray-400 line-through">{product.old_price.toLocaleString()} ₽</span>
											)}
										</div>
										{isVariableParent(product) ? (
											<Link
												to={`/product/${product.slug}`}
												className="w-full py-3 rounded-lg font-medium transition-colors whitespace-nowrap bg-red-600 text-white hover:bg-red-700 text-center block"
											>
												Выбрать
											</Link>
										) : (
											<button
												onClick={(e) => {
													e.preventDefault();
													e.stopPropagation();
													if (product.in_stock) {
														handleAddToCart(product);
													}
												}}
												disabled={!product.in_stock}
												className={`w-full py-3 rounded-lg font-medium transition-colors whitespace-nowrap ${getCartQuantityForProduct(cart?.items, product) > 0
													? 'bg-green-600 text-white hover:bg-green-700'
													: product.in_stock
														? 'bg-red-600 text-white hover:bg-red-700'
														: 'bg-gray-300 text-gray-500 cursor-not-allowed'
													}`}
											>
												{getCartQuantityForProduct(cart?.items, product) > 0
													? `В корзине (${getCartQuantityForProduct(cart?.items, product)})`
													: product.in_stock
														? 'В корзину'
														: 'Недоступно'}
											</button>
										)}
									</div>
								</div>
							);
						})}
					</div>
				)}
			</div>

		</div>
	);
}
