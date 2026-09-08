import { useState } from 'react';
import { Link } from 'react-router-dom';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import {
  ArrowLeftRight,
  ChevronLeft,
  ChevronRight,
  Heart,
  ImageIcon,
  Layers,
  ListFilter,
  Star,
  Tag,
  Truck,
  X,
} from 'lucide-react';

const sets = [
  {
    id: 1,
    name: 'Спальня "Классик Премиум"',
    category: 'Спальни',
    price: 189990,
    oldPrice: 229990,
    image:
      'https://readdy.ai/api/search-image?query=elegant%20complete%20bedroom%20set%20with%20bed%20dresser%20nightstands%20wardrobe%20in%20classic%20style%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set1&orientation=landscape',
    rating: 4.9,
    reviews: 89,
    discount: 17,
    itemsCount: 5,
    description: 'Полный комплект мебели для спальни в классическом стиле',
  },
  {
    id: 2,
    name: 'Гостиная "Модерн Люкс"',
    category: 'Гостиные',
    price: 234990,
    oldPrice: 279990,
    image:
      'https://readdy.ai/api/search-image?query=modern%20complete%20living%20room%20set%20with%20sofa%20tv%20stand%20coffee%20table%20shelves%20in%20minimalist%20style%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set2&orientation=landscape',
    rating: 4.8,
    reviews: 127,
    discount: 16,
    itemsCount: 6,
    description: 'Современный комплект мебели для гостиной',
  },
  {
    id: 3,
    name: 'Кухня "Скандинавия"',
    category: 'Кухни',
    price: 299990,
    oldPrice: 349990,
    image:
      'https://readdy.ai/api/search-image?query=scandinavian%20complete%20kitchen%20set%20with%20cabinets%20dining%20table%20chairs%20in%20light%20wood%20style%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set3&orientation=landscape',
    rating: 4.7,
    reviews: 156,
    discount: 14,
    itemsCount: 8,
    description: 'Полный кухонный гарнитур в скандинавском стиле',
  },
  {
    id: 4,
    name: 'Детская "Радуга"',
    category: 'Детские',
    price: 149990,
    oldPrice: 179990,
    image:
      'https://readdy.ai/api/search-image?query=colorful%20complete%20kids%20bedroom%20set%20with%20bed%20desk%20wardrobe%20shelves%20in%20bright%20cheerful%20style%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set4&orientation=landscape',
    rating: 4.9,
    reviews: 73,
    discount: 17,
    itemsCount: 6,
    description: 'Яркий и функциональный комплект для детской комнаты',
  },
  {
    id: 5,
    name: 'Спальня "Минимализм"',
    category: 'Спальни',
    price: 159990,
    oldPrice: 189990,
    image:
      'https://readdy.ai/api/search-image?query=minimalist%20complete%20bedroom%20set%20with%20platform%20bed%20floating%20nightstands%20simple%20wardrobe%20in%20white%20and%20wood%20tones%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set5&orientation=landscape',
    rating: 4.8,
    reviews: 94,
    discount: 16,
    itemsCount: 4,
    description: 'Стильный минималистичный комплект для спальни',
  },
  {
    id: 6,
    name: 'Гостиная "Лофт"',
    category: 'Гостиные',
    price: 199990,
    oldPrice: 239990,
    image:
      'https://readdy.ai/api/search-image?query=industrial%20loft%20style%20complete%20living%20room%20set%20with%20leather%20sofa%20metal%20coffee%20table%20wooden%20shelves%20in%20dark%20tones%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set6&orientation=landscape',
    rating: 4.6,
    reviews: 112,
    discount: 17,
    itemsCount: 5,
    description: 'Брутальный комплект мебели в стиле лофт',
  },
  {
    id: 7,
    name: 'Офис "Бизнес"',
    category: 'Офисная мебель',
    price: 179990,
    oldPrice: 209990,
    image:
      'https://readdy.ai/api/search-image?query=complete%20business%20office%20furniture%20set%20with%20executive%20desk%20office%20chair%20bookshelf%20filing%20cabinets%20in%20dark%20wood%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set7&orientation=landscape',
    rating: 4.7,
    reviews: 67,
    discount: 14,
    itemsCount: 5,
    description: 'Представительный офисный комплект для руководителя',
  },
  {
    id: 8,
    name: 'Прихожая "Элегант"',
    category: 'Прихожие',
    price: 89990,
    oldPrice: 109990,
    image:
      'https://readdy.ai/api/search-image?query=elegant%20complete%20hallway%20furniture%20set%20with%20wardrobe%20bench%20shoe%20storage%20mirror%20hooks%20in%20classic%20style%20professional%20furniture%20photography%20clean%20simple%20background&width=500&height=350&seq=set8&orientation=landscape',
    rating: 4.5,
    reviews: 45,
    discount: 18,
    itemsCount: 4,
    description: 'Элегантный комплект мебели для прихожей',
  },
];

export default function Sets() {
  const [priceRange, setPriceRange] = useState([0, 500000]);
  const [sortBy, setSortBy] = useState('popular');
  const [addedToCart, setAddedToCart] = useState<number[]>([]);
  const [isMobileFilterOpen, setIsMobileFilterOpen] = useState(false);
  const [favorites, setFavorites] = useState<number[]>([]);

  const handleAddToCart = (setId: number) => {
    setAddedToCart([...addedToCart, setId]);
    setTimeout(() => {
      setAddedToCart(addedToCart.filter((id) => id !== setId));
    }, 2000);
  };

  const toggleFavorite = (setId: number) => {
    setFavorites((prev) =>
      prev.includes(setId) ? prev.filter((id) => id !== setId) : [...prev, setId],
    );
  };

  usePageSeo({
    title: buildTitle('Готовые комплекты мебели'),
    description: 'Готовые комплекты мебели в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    robots: 'index, follow',
    open_graph_title: buildTitle('Готовые комплекты мебели'),
    locale: 'ru_RU',
  });

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-[1280px] mx-auto px-4 py-4 md:py-6">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-sm mb-2">
          <Link to="/" className="text-gray-600 hover:text-red-600">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <Link to="/catalog" className="text-gray-600 hover:text-red-600">
            Каталог
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Готовые комплекты</span>
        </div>

        <div className="flex items-start justify-between gap-6 mb-4 md:mb-6">
          <div className="flex-1">
            <h1 className="text-2xl md:text-3xl font-bold">Готовые комплекты мебели</h1>
            <p className="text-gray-600 mt-2">
              Полностью укомплектованные наборы мебели для разных комнат
            </p>
          </div>

          {/* Quick Stats */}
          <div className="hidden lg:flex items-center gap-4 self-center">
            <div className="flex items-center gap-2 px-4 py-2 bg-red-50 rounded-lg">
              <Tag className="size-[1em] text-red-600 text-xl" />
              <div>
                <p className="text-xs text-gray-600">Скидки до</p>
                <p className="font-bold text-red-600">30%</p>
              </div>
            </div>
            <div className="flex items-center gap-2 px-4 py-2 bg-green-50 rounded-lg">
              <Truck className="size-[1em] text-green-600 text-xl" />
              <div>
                <p className="text-xs text-gray-600">Доставка от</p>
                <p className="font-bold text-green-600">0 ₽</p>
              </div>
            </div>
            <div className="flex items-center gap-2 px-4 py-2 bg-yellow-50 rounded-lg">
              <Layers className="size-[1em] text-yellow-600 text-xl" />
              <div>
                <p className="text-xs text-gray-600">Готовые</p>
                <p className="font-bold text-yellow-600">комплекты</p>
              </div>
            </div>
          </div>
        </div>

        {/* Categories Filter */}
        <div className="flex flex-wrap gap-2 md:gap-3 mb-6 md:mb-8">
          <button className="px-4 py-2 rounded-lg text-sm md:text-base transition-colors cursor-pointer whitespace-nowrap bg-red-600 text-white">
            Все комплекты
          </button>
          {['Спальни', 'Гостиные', 'Кухни', 'Детские', 'Офисная мебель', 'Прихожие'].map(
            (category) => (
              <button
                key={category}
                className="px-4 py-2 rounded-lg text-sm md:text-base transition-colors cursor-pointer whitespace-nowrap bg-gray-100 text-gray-700 hover:bg-red-600 hover:text-white"
              >
                {category}
              </button>
            ),
          )}
        </div>

        {/* Mobile Filter Button */}
        <button
          onClick={() => setIsMobileFilterOpen(!isMobileFilterOpen)}
          className="lg:hidden w-full mb-4 bg-red-600 text-white py-3 rounded-lg font-medium flex items-center justify-center gap-2 whitespace-nowrap"
        >
          <ListFilter className="size-[1em]" />
          Фильтры
        </button>

        {/* Filters and Sets */}
        <div className="flex gap-6">
          {/* Sidebar Filters */}
          <div
            className={`${isMobileFilterOpen ? 'fixed inset-0 z-50 bg-white overflow-y-auto' : 'hidden'} lg:block lg:w-52 lg:flex-shrink-0`}
          >
            {/* Mobile Close Button */}
            <div className="lg:hidden flex items-center justify-between p-4 border-b">
              <h3 className="font-semibold text-lg">Фильтры</h3>
              <button
                onClick={() => setIsMobileFilterOpen(false)}
                className="w-8 h-8 flex items-center justify-center"
              >
                <X className="size-[1em] text-2xl" />
              </button>
            </div>

            <div className="bg-white border-0 lg:border lg:border-gray-200 rounded-none lg:rounded-2xl p-4 lg:p-5 lg:sticky lg:top-4">
              <h3 className="font-semibold text-base mb-4 hidden lg:block">Фильтры</h3>

              {/* Price Range */}
              <div className="mb-5">
                <label className="block text-sm font-medium mb-2">Цена комплекта</label>
                <div className="flex gap-2 mb-2">
                  <input
                    type="number"
                    value={priceRange[0]}
                    onChange={(e) => setPriceRange([+e.target.value, priceRange[1]])}
                    className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm"
                    placeholder="От"
                  />
                  <input
                    type="number"
                    value={priceRange[1]}
                    onChange={(e) => setPriceRange([priceRange[0], +e.target.value])}
                    className="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm"
                    placeholder="До"
                  />
                </div>
              </div>

              {/* Room Type */}
              <div className="mb-5">
                <label className="block text-sm font-medium mb-2">Тип комнаты</label>
                <div className="space-y-2">
                  {['Спальни', 'Гостиные', 'Кухни', 'Детские', 'Офисы'].map((room) => (
                    <label key={room} className="flex items-center gap-2 cursor-pointer">
                      <input type="checkbox" className="w-4 h-4 text-red-600 rounded" />
                      <span className="text-sm">{room}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Style */}
              <div className="mb-5">
                <label className="block text-sm font-medium mb-2">Стиль</label>
                <div className="space-y-2">
                  {['Классический', 'Современный', 'Скандинавский', 'Лофт', 'Минимализм'].map(
                    (style) => (
                      <label key={style} className="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" className="w-4 h-4 text-red-600 rounded" />
                        <span className="text-sm">{style}</span>
                      </label>
                    ),
                  )}
                </div>
              </div>

              <button
                onClick={() => setIsMobileFilterOpen(false)}
                className="w-full bg-red-600 text-white py-2.5 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap"
              >
                Применить фильтры
              </button>
            </div>
          </div>

          {/* Sets Grid */}
          <div className="flex-1">
            {/* Sort */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
              <p className="text-gray-600 text-sm">Найдено комплектов: {sets.length}</p>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="w-full sm:w-auto px-4 py-2 border border-gray-300 rounded-lg text-sm pr-8"
              >
                <option value="popular">Популярные</option>
                <option value="price-asc">Цена: по возрастанию</option>
                <option value="price-desc">Цена: по убыванию</option>
                <option value="new">Новинки</option>
                <option value="discount">По размеру скидки</option>
              </select>
            </div>

            {/* Sets */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" data-product-shop>
              {sets.map((set) => (
                <div
                  key={set.id}
                  className="bg-white border border-gray-200 rounded-2xl overflow-hidden hover:shadow-lg transition-shadow"
                >
                  <Link to={`/set/${set.id}`} className="block">
                    <div className="relative h-48 overflow-hidden">
                      {set.image ? (
                        <img
                          src={set.image}
                          alt={set.name}
                          className="w-full h-full object-cover transition-all duration-300 hover:scale-105"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center">
                          <ImageIcon className="size-[1em] text-3xl text-gray-400" />
                        </div>
                      )}
                      <div className="absolute top-3 left-3">
                        <span className="bg-red-600 text-white px-2 py-1 rounded-full text-xs font-medium">
                          -{set.discount}%
                        </span>
                      </div>
                      <div className="absolute top-3 right-3 flex gap-2">
                        <button
                          onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                          }}
                          className="w-8 h-8 md:w-9 md:h-9 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 cursor-pointer"
                        >
                          <ArrowLeftRight className="size-[1em] text-base md:text-lg text-gray-600" />
                        </button>
                        <button
                          onClick={(e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            toggleFavorite(set.id);
                          }}
                          className="w-8 h-8 md:w-9 md:h-9 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 cursor-pointer"
                        >
                          <Heart
                            className={`size-[1em] text-base md:text-lg ${favorites.includes(set.id) ? 'text-red-500' : 'text-red-600'}`}
                            fill={favorites.includes(set.id) ? 'currentColor' : 'none'}
                          />
                        </button>
                      </div>
                      <div className="absolute bottom-3 left-3">
                        <span className="bg-black/70 text-white px-2 py-1 rounded-full text-xs">
                          {set.itemsCount} предметов
                        </span>
                      </div>
                    </div>
                    <div className="p-4">
                      <p className="text-xs text-gray-500 mb-1">{set.category}</p>
                      <h3 className="font-semibold text-base mb-2 line-clamp-2">{set.name}</h3>
                      <p className="text-sm text-gray-600 mb-3 line-clamp-2">{set.description}</p>

                      <div className="flex items-center gap-2 mb-3">
                        <div className="flex items-center gap-1">
                          {[...Array(5)].map((_, i) => (
                            <Star
                              className="size-[1em] text-yellow-500 text-sm"
                              fill={i < Math.floor(set.rating) ? 'currentColor' : 'none'}
                              key={i}
                            />
                          ))}
                          <span className="ml-1 text-sm text-gray-600">{set.rating}</span>
                        </div>
                        <span className="text-xs text-gray-400">({set.reviews} отзывов)</span>
                      </div>

                      <div className="flex items-center gap-2 mb-4">
                        <span className="text-xl font-bold text-red-600">
                          {set.price.toLocaleString()} ₽
                        </span>
                        <span className="text-sm text-gray-400 line-through">
                          {set.oldPrice.toLocaleString()} ₽
                        </span>
                      </div>
                    </div>
                  </Link>
                  <div className="px-4 pb-4">
                    <button
                      onClick={(e) => {
                        e.preventDefault();
                        handleAddToCart(set.id);
                      }}
                      className={`w-full py-2.5 rounded-lg font-medium transition-colors whitespace-nowrap text-sm ${
                        addedToCart.includes(set.id)
                          ? 'bg-green-600 text-white hover:bg-green-700'
                          : 'bg-red-600 text-white hover:bg-red-700'
                      }`}
                    >
                      {addedToCart.includes(set.id) ? 'Комплект добавлен' : 'Добавить комплект'}
                    </button>
                  </div>
                </div>
              ))}
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-center gap-2 mt-8 md:mt-12">
              <button className="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 cursor-pointer">
                <ChevronLeft className="size-[1em] text-sm md:text-base" />
              </button>
              {[1, 2, 3].map((page) => (
                <button
                  key={page}
                  className={`w-8 h-8 md:w-10 md:h-10 flex items-center justify-center rounded-lg font-medium cursor-pointer text-sm md:text-base ${
                    page === 1
                      ? 'bg-red-600 text-white'
                      : 'border border-gray-300 hover:border-red-600'
                  }`}
                >
                  {page}
                </button>
              ))}
              <button className="w-8 h-8 md:w-10 md:h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 cursor-pointer">
                <ChevronRight className="size-[1em] text-sm md:text-base" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
