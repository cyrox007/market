import { FileText, ListChecks } from 'lucide-react';
import Icon from '../../../components/ui/icons/Icon';
import { hasMeaningfulProductText, type ProductSpecification } from '../lib/productText';

export type ProductTabId = 'description' | 'specs';

const TABS = [
  { id: 'description', label: 'Описание', icon: FileText },
  { id: 'specs', label: 'Характеристики', icon: ListChecks },
] as const;

interface Props {
  activeTab: ProductTabId;
  onTabChange: (tab: ProductTabId) => void;
  showDescription: boolean;
  description: string | null | undefined;
  specifications: ProductSpecification[];
}

export const PRODUCT_DETAILS_ID = 'product-details';

export default function ProductTabs({
  activeTab,
  onTabChange,
  showDescription,
  description,
  specifications,
}: Props) {
  const tabs = TABS.filter((tab) => tab.id !== 'description' || showDescription);

  return (
    <div id={PRODUCT_DETAILS_ID} className="mb-12 scroll-mt-20">
      <div className="border-b border-gray-200 mb-8">
        <div className="flex gap-8 overflow-x-auto">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              onClick={() => onTabChange(tab.id)}
              className={`flex items-center gap-2 pb-4 border-b-2 font-semibold cursor-pointer whitespace-nowrap transition-colors ${
                activeTab === tab.id
                  ? 'border-red-600 text-red-600'
                  : 'border-transparent text-gray-600 hover:text-red-600'
              }`}
            >
              <Icon name={tab.icon} className="size-[1em] text-xl" />
              <span>{tab.label}</span>
            </button>
          ))}
        </div>
      </div>

      <div className="prose max-w-none">
        {activeTab === 'description' && (
          <div>
            <h3 className="text-2xl font-bold mb-4">Описание товара</h3>
            {hasMeaningfulProductText(description) ? (
              <div
                className="text-gray-700 leading-relaxed"
                dangerouslySetInnerHTML={{ __html: description! }}
              />
            ) : (
              <p className="text-gray-700 mb-4 leading-relaxed">Описание товара отсутствует.</p>
            )}
          </div>
        )}

        {activeTab === 'specs' && (
          <div>
            <h3 className="text-2xl font-bold mb-6">Характеристики</h3>
            {specifications.length > 0 ? (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {specifications.map((spec) => (
                  <div key={spec.slug} className="flex justify-between p-4 bg-gray-50 rounded-lg">
                    <span className="font-semibold text-gray-900">{spec.name}</span>
                    <span className="text-gray-700">{spec.value}</span>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-gray-500">Характеристики не указаны</p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
