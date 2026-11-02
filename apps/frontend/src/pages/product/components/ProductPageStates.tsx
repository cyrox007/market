import { Button } from '../../../components/ui/primitives';
import { PAGE_CONTAINER } from '../../../lib/layout';

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
    <div className={`${PAGE_CONTAINER} py-12 max-vsm:py-8 text-ink`}>
      <div className="rounded-card bg-surface-grey p-10 max-md:p-6 max-vsm:p-5">
        <h1 className="font-display text-32 max-vsm:text-24 font-bold mb-3">Товар не найден</h1>
        <p className="text-16 text-ink-secondary mb-6">
          Возможно, он снят с продажи или ссылка устарела.
        </p>
        <Button to="/catalog" size="lg" className="max-vsm:w-full">
          Перейти в каталог
        </Button>
      </div>
    </div>
  );
}

/** Сбой связи или ошибка сервера — не «не найден» */
export function ProductLoadError({ onRetry }: { onRetry: () => void }) {
  return (
    <div className={`${PAGE_CONTAINER} py-12 max-vsm:py-8 text-ink`}>
      <div className="rounded-card bg-surface-grey p-10 max-md:p-6 max-vsm:p-5" role="alert">
        <h1 className="font-display text-32 max-vsm:text-24 font-bold mb-3">
          Не удалось загрузить товар
        </h1>
        <p className="text-16 text-ink-secondary mb-6">
          Проверьте соединение и попробуйте ещё раз. Если не поможет, зайдите чуть позже.
        </p>
        <div className="flex flex-wrap gap-3 max-vsm:flex-col">
          <Button onClick={onRetry} size="lg" className="max-vsm:w-full">
            Попробовать снова
          </Button>
          <Button to="/catalog" variant="outline" size="lg" className="max-vsm:w-full">
            Перейти в каталог
          </Button>
        </div>
      </div>
    </div>
  );
}
