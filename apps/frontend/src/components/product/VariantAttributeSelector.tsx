import type {
	VariationAttributeOption,
	VariationAttributeValue,
} from '../../lib/api';

interface VariantAttributeSelectorProps {
	attribute: VariationAttributeOption;
	selectedValues?: Set<string>;
	isValueAvailable: (valueSlug: string) => boolean;
	onSelect: (valueSlug: string) => void;
	isDisabled?: boolean;
	inactive?: boolean;
}

export default function VariantAttributeSelector({
	attribute,
	selectedValues = new Set(),
	isValueAvailable,
	onSelect,
	isDisabled = false,
	inactive = false,
}: VariantAttributeSelectorProps) {
	const { attribute_name, type, values } = attribute;

	if (!values?.length) return null;

	const isColor = type === 'color';
	const displayLabel = attribute_name;

	if (inactive) {
		return (
			<div className="opacity-60 pointer-events-none">
				<label className="block text-sm font-medium mb-2 text-gray-500">
					{displayLabel}
				</label>

				<div className="flex gap-2 flex-wrap">
					{values
						.slice(0, isColor ? 5 : 3)
						.map((v: VariationAttributeValue, index: number) => (
							<div
								key={String(v.slug ?? v.id ?? index)}
								className={
									isColor
										? 'w-10 h-10 rounded-full border-2 border-gray-200 bg-gray-100'
										: 'px-4 py-2 rounded-lg border-2 border-gray-200 bg-gray-100'
								}
							/>
						))}
				</div>
			</div>
		);
	}

	return (
		<div>
			<label className="block text-sm font-medium mb-2">
				{displayLabel}
			</label>

			<div className="flex gap-2 flex-wrap">
				{values.map((value) => {
					const slug = value.slug ?? '';

					const selected = selectedValues.has(slug);
					const available = isValueAvailable(slug);

					const commonClasses = `
						transition-all
						${selected
							? 'border-red-600 ring-2 ring-red-200'
							: available
								? 'border-gray-300 hover:border-red-400'
								: 'border-gray-200 opacity-40 cursor-not-allowed'}
						${isDisabled ? 'opacity-50 cursor-wait' : ''}
					`;

					if (isColor) {
						return (
							<button
								key={slug}
								type="button"
								onClick={() => onSelect(slug)}
								disabled={!available || isDisabled}
								title={value.name ?? slug}
								className={`w-10 h-10 rounded-full border-2 ${commonClasses}`}
								style={{
									backgroundColor: value.code ?? '#f5f5f5',
								}}
							/>
						);
					}

					return (
						<button
							key={slug}
							type="button"
							onClick={() => onSelect(slug)}
							disabled={!available || isDisabled}
							className={`px-4 py-2 rounded-lg border-2 text-sm font-medium ${commonClasses}`}
						>
							{value.name ?? slug}
						</button>
					);
				})}
			</div>
		</div>
	);
}
