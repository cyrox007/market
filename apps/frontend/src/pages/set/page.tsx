import { useState } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';

const setData = {
  1: {
    name: 'Спальня "Классик Премиум"',
    category: 'Спальни',
    price: 189990,
    oldPrice: 229990,
    rating: 4.9,
    reviews: 89,
    inStock: true,
    article: 'SET-2024-001',
    mainImage: 'https://readdy.ai/api/search-image?query=elegant%20complete%20bedroom%20set%20with%20bed%20dresser%20nightstands%20wardrobe%20in%20classic%20style%20luxurious%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=800&height=600&seq=setmain1&orientation=landscape',
    galleryImages: [
      'https://readdy.ai/api/search-image?query=elegant%20complete%20bedroom%20set%20with%20bed%20dresser%20nightstands%20wardrobe%20in%20classic%20style%20luxurious%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=800&height=600&seq=setgal1-1&orientation=landscape',
      'https://readdy.ai/api/search-image?query=elegant%20bedroom%20set%20detail%20view%20with%20nightstand%20and%20dresser%20in%20classic%20style%20luxurious%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=800&height=600&seq=setgal1-2&orientation=landscape',
      'https://readdy.ai/api/search-image?query=elegant%20bedroom%20set%20wardrobe%20and%20bed%20angle%20view%20in%20classic%20style%20luxurious%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=800&height=600&seq=setgal1-3&orientation=landscape'
    ],
    description: 'Роскошный комплект мебели для спальни в классическом стиле. Включает двуспальную кровать, комод, две тумбочки и шкаф.',
    items: [
      {
        id: 101,
        name: 'Кровать двуспальная "Классик"',
        price: 45990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20double%20bed%20with%20ornate%20headboard%20in%20luxurious%20bedroom%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-1&orientation=landscape',
        inSet: true,
        position: { top: '60%', left: '30%' }
      },
      {
        id: 102,
        name: 'Комод "Классик"',
        price: 32990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20bedroom%20dresser%20with%20mirror%20in%20luxurious%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-2&orientation=landscape',
        inSet: true,
        position: { top: '30%', left: '70%' }
      },
      {
        id: 103,
        name: 'Тумбочка "Классик" (2 шт)',
        price: 24990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20bedside%20table%20nightstand%20in%20luxurious%20bedroom%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-3&orientation=landscape',
        inSet: true,
        position: { top: '65%', left: '15%' }
      },
      {
        id: 104,
        name: 'Шкаф "Классик"',
        price: 65990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20wardrobe%20with%20ornate%20details%20in%20luxurious%20bedroom%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-4&orientation=landscape',
        inSet: true,
        position: { top: '20%', left: '10%' }
      },
      {
        id: 105,
        name: 'Туалетный столик "Классик"',
        price: 28990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20vanity%20table%20with%20mirror%20in%20luxurious%20bedroom%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-5&orientation=landscape',
        inSet: true,
        position: { top: '40%', left: '85%' }
      },
      {
        id: 106,
        name: 'Банкетка "Классик"',
        price: 18990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20bedroom%20bench%20ottoman%20in%20luxurious%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-6&orientation=landscape',
        inSet: false
      },
      {
        id: 107,
        name: 'Зеркало настенное "Классик"',
        price: 15990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20wall%20mirror%20with%20ornate%20frame%20in%20luxurious%20bedroom%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-7&orientation=landscape',
        inSet: false
      },
      {
        id: 108,
        name: 'Кресло "Классик"',
        price: 35990,
        image: 'https://readdy.ai/api/search-image?query=elegant%20classic%20armchair%20in%20luxurious%20bedroom%20interior%20white%20walls%20professional%20furniture%20photography%20clean%20simple%20background&width=400&height=300&seq=item1-8&orientation=landscape',
        inSet: false
      }
    ]
  }
};

export default function SetPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [selectedImage, setSelectedImage] = useState(0);
  const [cartItems, setCartItems] = useState<number[]>([]);
  const [selectedItems, setSelectedItems] = useState<number[]>([]);
  const [showShareMenu, setShowShareMenu] = useState(false);
  const [hoveredItem, setHoveredItem] = useState<number | null>(null);
  const [favorites, setFavorites] = useState<number[]>([]);
  const [removedFromSet, setRemovedFromSet] = useState<number[]>([]);
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [addedToCart, setAddedToCart] = useState(false);
  const [quantity, setQuantity] = useState(1);

  const set = id ? setData[parseInt(id) as keyof typeof setData] : null;

  if (!set) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-[1280px] mx-auto px-4 py-12">
          <h1 className="text-2xl font-bold">Комплект не найден</h1>
        </div>
      </div>
    );
  }

  const handleAddToCart = (itemId: number) => {
    if (cartItems.includes(itemId)) return;
    setCartItems([...cartItems, itemId]);
    setQuantities(prev => ({ ...prev, [itemId]: 1 }));
  };

  const handleAddSetToCart = () => {
    setAddedToCart(true);
  };

  const handleBuyNow = () => {
    const setItems = set.items
      .filter(item => item.inSet && !removedFromSet.includes(item.id))
      .map(item => item.id);
    setCartItems([...cartItems, ...setItems.filter(id => !cartItems.includes(id))]);
    navigate('/cart');
  };

  const toggleItemSelection = (itemId: number) => {
    setSelectedItems(prev => 
      prev.includes(itemId) 
        ? prev.filter(id => id !== itemId)
        : [...prev, itemId]
    );
  };

  const toggleFavorite = (itemId: number) => {
    setFavorites(prev => 
      prev.includes(itemId) 
        ? prev.filter(id => id !== itemId)
        : [...prev, itemId]
    );
  };

  const toggleRemoveFromSet = (itemId: number) => {
    setRemovedFromSet(prev => 
      prev.includes(itemId) 
        ? prev.filter(id => id !== itemId)
        : [...prev, itemId]
    );
  };

  const updateQuantity = (productId: number, delta: number) => {
    setQuantities(prev => ({
      ...prev,
      [productId]: Math.max(1, (prev[productId] || 1) + delta)
    }));
  };

  const handleShare = (platform: string) => {
    const url = window.location.href;
    const text = `${set.name} - ${set.price.toLocaleString()} ₽`;
    
    switch(platform) {
      case 'vk':
        window.open(`https://vk.com/share.php?url=${encodeURIComponent(url)}&title=${encodeURIComponent(text)}`, '_blank');
        break;
      case 'telegram':
        window.open(`https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`, '_blank');
        break;
      case 'whatsapp':
        window.open(`https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`, '_blank');
        break;
      case 'copy':
        navigator.clipboard.writeText(url);
        alert('Ссылка скопирована!');
        break;
    }
    setShowShareMenu(false);
  };

  const allImages = [set.mainImage, ...set.galleryImages];
  const activeSetItems = set.items.filter(item => item.inSet && !removedFromSet.includes(item.id));
  const setItemsTotal = activeSetItems.reduce((sum, item) => sum + item.price, 0);
  const selectedItemsTotal = set.items.filter(item => selectedItems.includes(item.id)).reduce((sum, item) => sum + item.price, 0);

  return (
    <div className="min-h-screen bg-white">

      <div className="max-w-[1280px] mx-auto px-4 py-6">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
          <Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
          <i className="ri-arrow-right-s-line text-gray-400"></i>
          <Link to="/catalog" className="text-gray-600 hover:text-red-600">Каталог</Link>
          <i className="ri-arrow-right-s-line text-gray-400"></i>
          <Link to="/sets" className="text-gray-600 hover:text-red-600">Готовые комплекты</Link>
          <i className="ri-arrow-right-s-line text-gray-400"></i>
          <span className="text-gray-900">{set.name}</span>
        </div>

        {/* Set Main Info */}
        <div className="grid grid-cols-[1fr_300px] gap-8 mb-12">
          {/* Left: Image Gallery and Info */}
          <div>
            {/* Main Image */}
            <div className="mb-6">
              <div className="bg-gray-50 rounded-2xl overflow-hidden mb-4 relative">
                {allImages[selectedImage] ? (
                  <img
                    src={allImages[selectedImage]}
                    alt={set.name}
                    className="w-full h-[500px] object-cover"
                  />
                ) : (
                  <div className="w-full h-[500px] flex items-center justify-center">
                    <i className="ri-image-line text-3xl text-gray-400"></i>
                  </div>
                )}
                
                {/* Image Navigation */}
                {allImages.length > 0 && (
                  <div className="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
                    {allImages.map((_, idx) => (
                      <button
                        key={idx}
                        onClick={() => setSelectedImage(idx)}
                        className={`w-3 h-3 rounded-full transition-all ${
                          selectedImage === idx ? 'bg-red-600 w-8' : 'bg-white/60'
                        }`}
                      />
                    ))}
                  </div>
                )}

                {/* Interactive Item Markers */}
                <div className="absolute inset-0">
                  {set.items
                    .filter(item => item.inSet && item.position)
                    .map((item) => (
                      <div
                        key={item.id}
                        className="absolute"
                        style={{ top: item.position!.top, left: item.position!.left }}
                        onClick={(e) => {
                          e.stopPropagation();
                          setHoveredItem(hoveredItem === item.id ? null : item.id);
                        }}
                      >
                        <div className="relative">
                          <div className="w-10 h-10 bg-red-500/90 rounded-full flex items-center justify-center animate-pulse-slow cursor-pointer">
                            <i className="ri-add-line text-white text-xl"></i>
                          </div>
                          <div className="absolute inset-0 w-10 h-10 bg-red-500/70 rounded-full animate-ping-slow"></div>
                        </div>
                        
                        {/* Item Card Popup */}
                        {hoveredItem === item.id && (
                          <div 
                            className="absolute bg-white rounded-xl shadow-2xl p-4 w-64"
                            style={{
                              left: item.position!.left.includes('85') || item.position!.left.includes('70') ? 'auto' : '50%',
                              right: item.position!.left.includes('85') || item.position!.left.includes('70') ? '50%' : 'auto',
                              top: '50%',
                              transform: item.position!.left.includes('85') || item.position!.left.includes('70') ? 'translate(50%, -50%)' : 'translate(-50%, -50%)',
                              zIndex: 100
                            }}
                            onClick={(e) => e.stopPropagation()}
                          >
                            <div className="relative mb-3">
                              {item.image ? (
                                <img 
                                  src={item.image} 
                                  alt={item.name}
                                  className="w-full h-48 object-cover object-top rounded-lg cursor-pointer"
                                />
                              ) : (
                                <div className="w-full h-48 flex items-center justify-center rounded-lg cursor-pointer">
                                  <i className="ri-image-line text-3xl text-gray-400"></i>
                                </div>
                              )}
                              <div className="absolute top-2 right-2 flex gap-2">
                                <button
                                  onClick={(e) => {
                                    e.stopPropagation();
                                  }}
                                  className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 transition-colors cursor-pointer"
                                >
                                  <i className="ri-scales-3-line text-gray-600"></i>
                                </button>
                                <button
                                  onClick={(e) => {
                                    e.stopPropagation();
                                    toggleFavorite(item.id);
                                  }}
                                  className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 transition-colors cursor-pointer"
                                >
                                  <i className={`${favorites.includes(item.id) ? 'ri-heart-fill text-red-500' : 'ri-heart-line text-gray-600'}`}></i>
                                </button>
                              </div>
                            </div>
                            <h3 className="font-bold text-lg mb-2">{item.name}</h3>
                            <p className="text-red-500 font-bold text-xl mb-3">{item.price.toLocaleString()} ₽</p>
                            
                            <div className="flex gap-2">
                              <button
                                onClick={() => handleAddToCart(item.id)}
                                className={`py-2 rounded-lg font-medium transition-all whitespace-nowrap cursor-pointer ${
                                  cartItems.includes(item.id)
                                    ? 'bg-green-600 text-white hover:bg-green-700 flex-shrink-0'
                                    : 'bg-red-500 text-white hover:bg-red-600 w-full'
                                }`}
                                style={cartItems.includes(item.id) ? { width: '120px' } : {}}
                              >
                                {cartItems.includes(item.id) ? 'Добавлено' : 'Добавить в корзину'}
                              </button>
                              
                              {cartItems.includes(item.id) && (
                                <div className="flex items-center gap-3 flex-1 rounded-lg px-2 animate-[fadeIn_0.3s_ease-in-out]">
                                  <button
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      updateQuantity(item.id, -1);
                                    }}
                                    className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors"
                                  >
                                    <i className="ri-subtract-line"></i>
                                  </button>
                                  <span className="text-lg font-bold flex-1 text-center">{quantities[item.id] || 1}</span>
                                  <button
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      updateQuantity(item.id, 1);
                                    }}
                                    className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors"
                                  >
                                    <i className="ri-add-line"></i>
                                  </button>
                                </div>
                              )}
                            </div>
                          </div>
                        )}
                      </div>
                    ))}
                </div>
              </div>

              {/* Thumbnails */}
              {allImages.length > 0 && (
                <div className="flex gap-3 overflow-x-auto pb-2">
                  {allImages.map((img, idx) => (
                    <button
                      key={idx}
                      onClick={() => setSelectedImage(idx)}
                      className={`flex-shrink-0 w-20 h-16 rounded-lg overflow-hidden border-2 transition-all cursor-pointer ${
                        selectedImage === idx ? 'border-red-600 scale-105' : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      {img ? (
                        <img
                          src={img}
                          alt={`${set.name} ${idx + 1}`}
                          className="w-full h-full object-cover"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center">
                          <i className="ri-image-line text-3xl text-gray-400"></i>
                        </div>
                      )}
                    </button>
                  ))}
                </div>
              )}
            </div>

            {/* Set Description */}
            <div className="bg-gray-50 rounded-2xl p-6">
              <h2 className="text-xl font-bold mb-4">Описание комплекта</h2>
              <p className="text-gray-700 leading-relaxed mb-4">{set.description}</p>
              
              <div className="grid grid-cols-2 gap-4">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i className="ri-truck-line text-red-600 text-lg"></i>
                  </div>
                  <div>
                    <p className="font-semibold text-sm">Доставка и сборка</p>
                    <p className="text-xs text-gray-600">Бесплатно при заказе комплекта</p>
                  </div>
                </div>
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i className="ri-shield-check-line text-green-600 text-lg"></i>
                  </div>
                  <div>
                    <p className="font-semibold text-sm">Гарантия 3 года</p>
                    <p className="text-xs text-gray-600">На весь комплект</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* Right: Purchase Section */}
          <div className="bg-gray-50 rounded-2xl p-6 h-fit sticky top-20">
            <div className="flex items-center gap-2 mb-3">
              <span className={`px-3 py-1 rounded-full text-xs font-medium whitespace-nowrap ${
                set.inStock ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
              }`}>
                {set.inStock ? 'В наличии' : 'Под заказ'}
              </span>
              <span className="text-xs text-gray-500">Арт: {set.article}</span>
            </div>

            <h1 className="text-xl font-bold mb-3">{set.name}</h1>
            
            <div className="flex items-center gap-3 mb-6">
              <div className="flex items-center gap-1">
                {[...Array(5)].map((_, i) => (
                  <i
                    key={i}
                    className={`${
                      i < Math.floor(set.rating) ? 'ri-star-fill' : 'ri-star-line'
                    } text-yellow-500 text-base`}
                  ></i>
                ))}
                <span className="ml-2 text-gray-900 font-medium">{set.rating}</span>
              </div>
              <span className="text-red-600 text-sm">({set.reviews} отзывов)</span>
            </div>

            {/* Price */}
            <div className="mb-6 text-center">
              <div className="flex items-center justify-center gap-2 mb-2">
                <span className="text-2xl font-bold text-red-600">{setItemsTotal.toLocaleString()} ₽</span>
                {removedFromSet.length === 0 && (
                  <span className="bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap">
                    -{Math.round((1 - set.price / set.oldPrice) * 100)}%
                  </span>
                )}
              </div>
              {removedFromSet.length === 0 && (
                <>
                  <span className="text-sm text-gray-400 line-through">{set.oldPrice.toLocaleString()} ₽</span>
                  <p className="text-xs text-gray-600 mt-1">Экономия: {(set.oldPrice - set.price).toLocaleString()} ₽</p>
                </>
              )}
              {removedFromSet.length > 0 && (
                <p className="text-xs text-gray-600 mt-1">Товаров в комплекте: {activeSetItems.length}</p>
              )}
            </div>

            {/* Action Buttons */}
            <div className="space-y-3 mb-6">
              <div className="flex gap-2">
                <button 
                  onClick={handleAddSetToCart}
                  className={`py-3 rounded-lg font-semibold transition-all whitespace-nowrap shadow-lg ${
                    addedToCart 
                      ? 'bg-green-600 text-white hover:bg-green-700 shadow-green-600/30 flex-shrink-0' 
                      : 'bg-red-600 text-white hover:bg-red-700 shadow-red-600/30 w-full'
                  }`}
                  style={addedToCart ? { width: '120px' } : {}}
                >
                  {addedToCart ? 'Добавлено' : 'Добавить в корзину'}
                </button>
                
                {addedToCart && (
                  <div className="flex items-center gap-3 flex-1 rounded-lg px-4 animate-[fadeIn_0.3s_ease-in-out]">
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        setQuantity(Math.max(1, quantity - 1));
                      }}
                      className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors"
                    >
                      <i className="ri-subtract-line"></i>
                    </button>
                    <span className="text-lg font-bold flex-1 text-center">{quantity}</span>
                    <button
                      onClick={(e) => {
                        e.stopPropagation();
                        setQuantity(quantity + 1);
                      }}
                      className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-red-50 cursor-pointer transition-colors"
                    >
                      <i className="ri-add-line"></i>
                    </button>
                  </div>
                )}
              </div>
              
              <button 
                onClick={handleBuyNow}
                className="w-full bg-red-200 text-red-700 py-3 rounded-lg font-semibold hover:bg-red-300 transition-colors whitespace-nowrap"
              >
                Купить сейчас
              </button>

              <button 
                onClick={() => navigate('/checkout')}
                className="w-full bg-white border-2 border-gray-300 text-gray-900 py-3 rounded-lg font-semibold hover:border-red-600 hover:bg-red-50 transition-colors whitespace-nowrap flex items-center justify-center gap-2"
              >
                <i className="ri-calendar-line text-lg"></i>
                Оплата частями
              </button>
              
              <div className="flex gap-2">
                <button className="flex-1 flex items-center justify-center gap-2 border-2 border-gray-300 rounded-lg py-2.5 hover:border-red-600 hover:bg-red-50 cursor-pointer transition-colors">
                  <i className="ri-heart-line text-lg text-red-600"></i>
                  <span className="text-sm font-medium">В избранное</span>
                </button>
                <button className="flex-1 flex items-center justify-center gap-2 border-2 border-gray-300 rounded-lg py-2.5 hover:border-red-600 hover:bg-red-50 cursor-pointer transition-colors">
                  <i className="ri-scales-3-line text-lg text-gray-700"></i>
                  <span className="text-sm font-medium">Сравнить</span>
                </button>
              </div>
            </div>

            {/* Share */}
            <div className="relative">
              <button 
                onClick={() => setShowShareMenu(!showShareMenu)}
                className="w-full flex items-center justify-center gap-2 border border-gray-300 rounded-lg py-2.5 hover:border-red-600 hover:bg-red-50 cursor-pointer transition-colors"
              >
                <i className="ri-share-line text-lg text-gray-700"></i>
                <span className="text-sm font-medium">Поделиться</span>
              </button>
              
              {showShareMenu && (
                <div className="absolute top-12 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg p-2 z-10">
                  <button onClick={() => handleShare('vk')} className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left">
                    <i className="ri-vk-fill text-xl text-blue-600"></i>
                    <span className="text-sm">ВКонтакте</span>
                  </button>
                  <button onClick={() => handleShare('telegram')} className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left">
                    <i className="ri-telegram-fill text-xl text-blue-500"></i>
                    <span className="text-sm">Telegram</span>
                  </button>
                  <button onClick={() => handleShare('whatsapp')} className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left">
                    <i className="ri-whatsapp-fill text-xl text-green-600"></i>
                    <span className="text-sm">WhatsApp</span>
                  </button>
                  <button onClick={() => handleShare('copy')} className="w-full flex items-center gap-3 px-3 py-2 hover:bg-gray-50 rounded-lg cursor-pointer text-left">
                    <i className="ri-file-copy-line text-xl text-gray-600"></i>
                    <span className="text-sm">Копировать ссылку</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Items in Set */}
        <div className="mb-12">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-2xl font-bold">Собери свой комплект</h2>
            {selectedItems.length > 0 && (
              <div className="bg-red-50 px-4 py-2 rounded-lg">
                <span className="text-red-600 font-semibold">
                  Выбрано: {selectedItemsTotal.toLocaleString()} ₽
                </span>
              </div>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5" data-product-shop>
            {set.items.map((item) => {
              const isInActiveSet = item.inSet && !removedFromSet.includes(item.id);
              const isRemovedFromSet = item.inSet && removedFromSet.includes(item.id);
              
              return (
                <div 
                  key={item.id} 
                  className={`bg-white border-2 rounded-2xl overflow-hidden transition-all ${
                    isInActiveSet
                      ? 'border-red-200 bg-red-50' 
                      : selectedItems.includes(item.id)
                      ? 'border-red-600 shadow-lg'
                      : 'border-gray-200 hover:shadow-lg'
                  }`}
                >
                  <div className="relative h-48">
                    {item.image ? (
                      <img 
                        src={item.image} 
                        alt={item.name} 
                        className="w-full h-full object-cover" 
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center">
                        <i className="ri-image-line text-3xl text-gray-400"></i>
                      </div>
                    )}
                    {isInActiveSet && (
                      <div className="absolute top-3 left-3">
                        <span className="bg-red-600 text-white px-2 py-1 rounded-full text-xs font-medium">
                          В комплекте
                        </span>
                      </div>
                    )}
                    {isRemovedFromSet && (
                      <div className="absolute top-3 left-3">
                        <span className="bg-gray-600 text-white px-2 py-1 rounded-full text-xs font-medium">
                          Убрано
                        </span>
                      </div>
                    )}
                    {selectedItems.includes(item.id) && (
                      <div className="absolute top-3 left-3">
                        <span className="bg-green-600 text-white px-2 py-1 rounded-full text-xs font-medium">
                          Выбрано
                        </span>
                      </div>
                    )}
                    
                    {/* Icons */}
                    <div className="absolute top-3 right-3 flex gap-2">
                      <button 
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                        }}
                        className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 cursor-pointer"
                      >
                        <i className="ri-scales-3-line text-base text-gray-600"></i>
                      </button>
                      <button 
                        onClick={(e) => {
                          e.preventDefault();
                          e.stopPropagation();
                          toggleFavorite(item.id);
                        }}
                        className="w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-md hover:bg-red-50 cursor-pointer"
                      >
                        <i className={`text-base ${favorites.includes(item.id) ? 'ri-heart-fill text-red-500' : 'ri-heart-line text-red-600'}`}></i>
                      </button>
                    </div>
                  </div>
                  
                  <div className="p-4">
                    <h3 className="font-semibold text-sm mb-3 line-clamp-2">{item.name}</h3>
                    <div className="flex items-center justify-between mb-3">
                      <span className="text-lg font-bold text-red-600">{item.price.toLocaleString()} ₽</span>
                    </div>
                    
                    {isInActiveSet ? (
                      <button 
                        onClick={(e) => {
                          e.preventDefault();
                          toggleRemoveFromSet(item.id);
                        }}
                        className="w-full bg-red-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors whitespace-nowrap"
                      >
                        Убрать из комплекта
                      </button>
                    ) : isRemovedFromSet ? (
                      <button 
                        onClick={(e) => {
                          e.preventDefault();
                          toggleRemoveFromSet(item.id);
                        }}
                        className="w-full bg-green-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors whitespace-nowrap"
                      >
                        Вернуть в комплект
                      </button>
                    ) : (
                      <button 
                        onClick={(e) => {
                          e.preventDefault();
                          if (!item.inSet) {
                            toggleItemSelection(item.id);
                          }
                        }}
                        className={`w-full py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap ${
                          cartItems.includes(item.id)
                            ? 'bg-green-600 text-white hover:bg-green-700'
                            : selectedItems.includes(item.id)
                            ? 'bg-red-200 text-red-700 hover:bg-red-300'
                            : 'bg-gray-100 text-gray-700 hover:bg-red-600 hover:text-white'
                        }`}
                      >
                        {cartItems.includes(item.id) 
                          ? 'В корзине' 
                          : selectedItems.includes(item.id)
                          ? 'Убрать из выбора'
                          : 'Добавить отдельно'
                        }
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>

          {selectedItems.length > 0 && (
            <div className="mt-6 bg-red-50 border border-red-200 rounded-2xl p-6">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-bold text-lg">Ваш индивидуальный комплект</h3>
                  <p className="text-gray-600">Выбрано {selectedItems.length} товаров (+ {activeSetItems.length} в комплекте)</p>
                </div>
                <div className="text-right">
                  <div className="text-2xl font-bold text-red-600">{(selectedItemsTotal + setItemsTotal).toLocaleString()} ₽</div>
                  <button 
                    onClick={() => {
                      selectedItems.forEach(itemId => handleAddToCart(itemId));
                      activeSetItems.forEach(item => handleAddToCart(item.id));
                      setSelectedItems([]);
                    }}
                    className="mt-2 bg-red-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-red-700 transition-colors whitespace-nowrap"
                  >
                    Добавить в корзину
                  </button>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Overlay to close cards */}
      {hoveredItem && (
        <div 
          className="fixed inset-0 z-40"
          onClick={() => setHoveredItem(null)}
        />
      )}

    </div>
  );
}
