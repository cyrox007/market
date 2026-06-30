/**
 * Видимый fallback при подгрузке lazy-чанка. Никогда не возвращает null —
 * иначе при гидратации SSR-контент сменяется пустым main и кажется «белый экран».
 */
export default function PageContentFallback() {
  return (
    <div className="max-w-[1280px] mx-auto px-4 py-6 animate-pulse" aria-hidden>
      <div className="h-8 bg-gray-200 rounded w-1/3 mb-6" />
      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        {[...Array(8)].map((_, i) => (
          <div key={i} className="bg-gray-200 rounded-2xl h-80" />
        ))}
      </div>
    </div>
  );
}
