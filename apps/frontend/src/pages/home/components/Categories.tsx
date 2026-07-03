import { useState } from 'react';
import useSWR from 'swr';
import { api } from '../../../lib/api';
import { useSSR } from '../../../contexts/SSRContext';
import { usePrefetchCategory } from '../../../hooks/usePrefetchCategory';
import type { Category } from '../../../lib/api';
import { Link } from 'react-router-dom';

const bgColors = [
	'bg-red-50', 'bg-yellow-50',
	'bg-green-50', 'bg-blue-50',
	'bg-purple-50', 'bg-pink-50'
];

// Цвета для иконок (кружков) в новом дизайне
const iconColors = [
	'bg-blue-100 text-blue-600',
	'bg-red-100 text-red-600',
	'bg-yellow-100 text-yellow-600',
	'bg-green-100 text-green-600',
	'bg-purple-100 text-purple-600',
	'bg-pink-100 text-pink-600',
];

// Иконки для категорий (remixicon)
const categoryIcons: Record<string, string> = {
	'Диваны и кресла': 'ri-sofa-line',
	'Спальни': 'ri-bed-line',
	'Кухни': 'ri-knife-line',
	'Столы и стулья': 'ri-restaurant-line',
	'Хранение': 'ri-archive-line',
	'Матрасы': 'ri-bed-line',
};

// fallback иконки по умолчанию
const getIconForCategory = (name: string): string => {
	for (const [key, icon] of Object.entries(categoryIcons)) {
		if (name.includes(key) || key.includes(name)) {
			return icon;
		}
	}
	return 'ri-folder-line';
};

export default function Categories() {
	const ssrData = useSSR();
	const initialCategories = ssrData?.home?.categories || [];

	// Используем SWR для загрузки категорий (SSR fallback убирает мигание скелета)
	const { data: categoriesData, isLoading } = useSWR(
		'/api/categories',
		() => api.categories.list(),
		{
			fallbackData: initialCategories.length > 0 ? { data: initialCategories } : undefined,
			revalidateOnMount: initialCategories.length === 0,
			revalidateIfStale: true,
			revalidateOnFocus: false,
			dedupingInterval: 30_000,
		}
	);

	const categories = categoriesData?.data || [];
	const prefetchCategory = usePrefetchCategory();

	if (isLoading && categories.length === 0) {
		return (
			<section className="px-6 lg:px-12 py-16">
				<div className="max-w-7xl mx-auto">
					<h2 className="text-3xl font-bold text-gray-900 mb-8">Категории</h2>
					<div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
						{[...Array(6)].map((_, i) => (
							<div key={i} className="bg-gray-200 rounded-2xl p-6 animate-pulse">
								<div className="aspect-square mb-3 bg-gray-300 rounded-xl" />
								<div className="h-4 bg-gray-300 rounded w-3/4 mx-auto" />
							</div>
						))}
					</div>
				</div>
			</section>
		);
	}

	if (categories.length === 0) {
		return null;
	}

	return (
		<section className="px-6 lg:px-12 py-16">
			<div className="max-w-7xl mx-auto">
				<div className="flex items-center justify-between mb-4">
					<h2 className="text-3xl font-bold text-gray-900 mb-4">Категории</h2>
					<Link
						to="/catalog"
						className="text-sm text-gray-500 hover:text-red-600 transition-colors flex items-center gap-1"
					>
						Все категории
						<i className="ri-arrow-right-line"></i>
					</Link>
				</div>

				<div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
					{categories.slice(0, 6).map((category, index) => (
						<Link
							key={category.id}
							to={`/catalog/${category.slug}`}
							className="group cursor-pointer"
							onMouseEnter={() => prefetchCategory(category.slug)}
							onFocus={() => prefetchCategory(category.slug)}
						>
							{/* <div className={`${bgColors[index % bgColors.length]} rounded-2xl p-6 transition-all duration-300 group-hover:shadow-lg`}>
								<div className="aspect-square mb-3 overflow-hidden rounded-xl">
									{category.image_thumb || category.image_hd || category.image ? (
										<img
											src={category.image_thumb || category.image_hd || category.image}
											alt={category.name}
											className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
										/>
									) : (
										<div className="w-full h-full flex items-center justify-center bg-gray-200">
											<i className="ri-image-line text-3xl text-gray-400"></i>
										</div>
									)}
								</div>
								<h3 className="text-center text-sm font-semibold text-gray-900">
									{category.name}
								</h3>
								{category.products_count > 0 && (
									<p className="text-center text-xs text-gray-500 mt-1">
										{category.products_count} товаров
									</p>
								)}
							</div> */}

							<div className='relative bg-[rgb(249,244,243)] relative rounded-lg overflow-hidden transition-all duration-300 group-hover:shadow-lg h-44 md:h-48 lg:h-72'>
								<div className="absolute top-3 left-3 z-10">
									<h3 className="text-black text-base font-semibold drop-shadow-md leading-tight max-w-[80%]">
										{category.name}
									</h3>
									{category.products_count > 0 && (
										<p className="text-black/80 text-xs mt-0.5 drop-shadow-md">
											{category.products_count} товаров
										</p>
									)}
								</div>
								<div className="absolute inset-0 w-full h-full flex items-end">
									{category.image_thumb || category.image_hd || category.image ? (
										<img
											src={category.image_thumb || category.image_hd || category.image}
											alt={category.name}
											className="w-full h-auto object-cover object-bottom transition-transform duration-300 group-hover:scale-105"
										/>
									) : (
										<div className="w-full h-full flex items-center justify-center bg-gray-200/50">
											<i className="ri-image-line text-4xl text-gray-400"></i>
										</div>
									)}
								</div>
								<div className="absolute bottom-3 right-3 z-10">
									<div className="w-9 h-9 rounded-full bg-white/80 backdrop-blur-sm flex items-center justify-center transition-all duration-300 group-hover:bg-white group-hover:scale-110 shadow-sm">
										<i className="ri-arrow-right-line text-gray-700 text-lg"></i>
									</div>
								</div>
							</div>
						</Link>
					))}
				</div>
			</div>
		</section>
	);
}
