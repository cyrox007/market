/** Скелетоны фильтров, пока meta не загружена */
export default function FiltersSkeleton() {
  return (
    <div className="animate-pulse space-y-5">
      <div>
        <div className="h-4 bg-gray-200 rounded w-12 mb-2" />
        <div className="flex gap-2 mb-2">
          <div className="flex-1 h-9 bg-gray-200 rounded-lg" />
          <div className="flex-1 h-9 bg-gray-200 rounded-lg" />
        </div>
        <div className="h-9 bg-gray-200 rounded-lg" />
      </div>
      <div>
        <div className="h-4 bg-gray-200 rounded w-14 mb-2" />
        <div className="flex flex-wrap gap-2">
          {[...Array(6)].map((_, i) => (
            <div key={i} className="w-8 h-8 rounded-full bg-gray-200" />
          ))}
        </div>
      </div>
      <div>
        <div className="h-4 bg-gray-200 rounded w-16 mb-2" />
        <div className="flex flex-wrap gap-2">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-9 w-16 bg-gray-200 rounded-lg" />
          ))}
        </div>
      </div>
      {[...Array(2)].map((_, i) => (
        <div key={i}>
          <div className="h-4 bg-gray-200 rounded w-24 mb-2" />
          <div className="space-y-2">
            {[...Array(3)].map((_, j) => (
              <div key={j} className="flex items-center gap-2">
                <div className="h-4 w-4 rounded bg-gray-200" />
                <div className="h-4 bg-gray-200 rounded flex-1 max-w-[80%]" />
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
