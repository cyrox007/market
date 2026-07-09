interface VariantColorSelectorProps {
	variants: Array<{
		id: number;
		colors?: Array<{
			id: number;
			value: string;
			slug: string;
			color_code: string | null;
		}>;
		price: number;
		in_stock: boolean;
	}>;
	selectedVariantId: number | null;
	onSelect: (variantId: number) => void;
}

export default function VariantColorSelector({
	variants,
	selectedVariantId,
	onSelect,
}: VariantColorSelectorProps) {
	if (!variants || variants.length === 0) return null;

	return (
		<div className="flex flex-col gap-2">
			<label className="text-sm font-medium text-gray-700">Цвет:</label>
			<div className="flex gap-3 flex-wrap">
				{variants.map((variant) => {
					const isSelected = selectedVariantId === variant.id;
					const isAvailable = variant.in_stock;

					return (
						<button
							key={variant.id}
							onClick={() => isAvailable && onSelect(variant.id)}
							disabled={!isAvailable}
							className={`
                                p-2 rounded-lg border-2 transition-all
                                ${isSelected ? 'border-red-600 bg-red-50' : 'border-gray-200'}
                                ${!isAvailable ? 'opacity-50 cursor-not-allowed' : 'hover:border-red-400 cursor-pointer'}
                            `}
						>
							{variant.colors && variant.colors.length > 0 && (
								<>
									<div className="flex gap-1">
										{variant.colors.map((color, idx) => (
											<span
												key={idx}
												className="w-8 h-8 rounded-full border border-gray-300"
												style={{ backgroundColor: color.color_code || '#ccc' }}
												title={color.value}
											/>
										))}
									</div>
									<div className="text-xs text-gray-500 mt-1">
										{variant.colors.map(c => c.value).join(' + ')}
									</div>
								</>
							)}
							{!isAvailable && (
								<div className="text-xs text-red-500 mt-1">Нет в наличии</div>
							)}
						</button>
					);
				})}
			</div>
		</div>
	);
}