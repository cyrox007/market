import { Link } from 'react-router-dom';

export function ProductPageSkeleton() {
  return (
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
}

export function ProductNotFound() {
  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-[1280px] mx-auto px-4 py-12">
        <h1 className="text-2xl font-bold mb-3">Товар не найден</h1>
        <p className="text-gray-600 mb-6">Возможно, он снят с продажи или ссылка устарела.</p>
        <Link
          to="/catalog"
          className="inline-block px-5 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
        >
          Перейти в каталог
        </Link>
      </div>
    </div>
  );
}

/** Сбой связи или ошибка сервера — не «не найден» */
export function ProductLoadError({ onRetry }: { onRetry: () => void }) {
  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-[1280px] mx-auto px-4 py-12">
        <h1 className="text-2xl font-bold mb-3">Не удалось загрузить товар</h1>
        <p className="text-gray-600 mb-6">
          Проверьте соединение и попробуйте ещё раз. Если не поможет, зайдите чуть позже.
        </p>
        <div className="flex flex-wrap gap-2">
          <button
            onClick={onRetry}
            className="px-5 py-2.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
          >
            Попробовать снова
          </button>
          <Link
            to="/catalog"
            className="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Перейти в каталог
          </Link>
        </div>
      </div>
    </div>
  );
}
