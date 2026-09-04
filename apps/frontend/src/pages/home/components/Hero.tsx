import { useEffect, useState } from 'react';
import { getSliders } from '../../../lib/api-server';
import { useSSR } from '../../../contexts/SSRContext';
import type { Slider } from '../../../lib/api';
import { ArrowRight, Zap } from 'lucide-react';
import Icon from '../../../components/ui/icons/Icon';

export default function Hero() {
  const ssrData = useSSR();
  const initialSliders = ssrData?.home?.sliders || [];
  const initialSlider = initialSliders.length > 0 ? (initialSliders.find((s) => s) || initialSliders[0] || null) : null;

  const [slider, setSlider] = useState<Slider | null>(initialSlider);
  const [isLoading, setIsLoading] = useState(!initialSlider);

  useEffect(() => {
    // Если есть SSR данные, не загружаем повторно
    if (initialSlider) {
      return;
    }

    let isMounted = true;

    // Загружаем данные слайдера с кэшированием (revalidate: 120 секунд)
    getSliders({ revalidate: 120 })
      .then((sliders) => {
        if (!isMounted) return;

        // Берем первый активный слайдер
        const activeSlider = sliders.find((s) => s) || sliders[0] || null;
        setSlider(activeSlider);
      })
      .catch((err) => {
        if (!isMounted) return;

        console.error('Failed to load slider:', err);
        // При ошибке используем fallback данные
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [initialSlider]);

  // Статичный fallback только когда нет данных из API/кэша (SSR кэш при ошибке отдаёт последние из API)
  const displaySlider: Slider = slider || {
    id: 0,
    title: 'Светофор мебель',
    description: 'Преобразите ваше жилое пространство с нашей коллекцией современной и стильной мебели',
    link: '/catalog',
    button_text: 'Перейти в каталог',
    badge_text: null,
    badge_link: null,
    badge_icon: null,
    image: '/hero-bg.jpg',
    image_thumb: null,
    image_hd: null,
    image_fullhd: null,
    slug: 'default',
    full_path: null,
    seo: null,
  };

  const badgeText = displaySlider.badge_text ?? 'Помощь онлайн';
  const badgeIcon = displaySlider.badge_icon ?? Zap;
  const showBadge = badgeText.length > 0;

  const imageUrl = displaySlider.image_hd || displaySlider.image || displaySlider.image_fullhd || '';

  return (
    <section className="px-6 lg:px-12 pt-6">
      <div className="relative h-[600px] rounded-3xl overflow-hidden">
        {/* Background Image */}
        {imageUrl && (
          <img
            src={imageUrl}
            alt={displaySlider.title}
            className="absolute inset-0 w-full h-full object-cover object-top"
            loading="eager"
          />
        )}

        {/* Loading Overlay */}
        {isLoading && (
          <div className="absolute inset-0 bg-gray-200 animate-pulse" />
        )}

        {/* Gradient Overlay */}
        <div className="absolute inset-0 bg-gradient-to-r from-black/70 via-black/40 to-transparent"></div>

        {/* Content */}
        <div className="relative h-full flex items-center">
          <div className="w-full max-w-7xl mx-auto px-8 lg:px-12">
            <div className="max-w-xl">
              {/* Badge */}
              {showBadge && (displaySlider.badge_link ? (
                <a
                  href={displaySlider.badge_link}
                  className="inline-flex items-center gap-2 bg-yellow-400 text-gray-900 px-4 py-2 rounded-full mb-6 hover:bg-yellow-300 transition-colors"
                >
                  <Icon name={badgeIcon} className="size-[1em]" />
                  <span className="font-semibold text-sm whitespace-nowrap">{badgeText}</span>
                </a>
              ) : (
                <div className="inline-flex items-center gap-2 bg-yellow-400 text-gray-900 px-4 py-2 rounded-full mb-6">
                  <Icon name={badgeIcon} className="size-[1em]" />
                  <span className="font-semibold text-sm whitespace-nowrap">{badgeText}</span>
                </div>
              ))}

              {/* Title */}
              <h1 className="text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                {displaySlider.title}
              </h1>

              {/* Description */}
              {displaySlider.description && (
                <p
                  className="text-xl text-white/90 mb-8 leading-relaxed"
                  dangerouslySetInnerHTML={{ __html: displaySlider.description }}
                />
              )}

              {/* CTA Button */}
              {displaySlider.link && (
                <a
                  href={displaySlider.link}
                  className="inline-flex items-center gap-3 bg-white text-gray-900 px-8 py-4 rounded-full font-semibold hover:bg-gray-100 transition-all cursor-pointer whitespace-nowrap"
                >
                  <span>{displaySlider.button_text || 'Перейти в каталог'}</span>
                  <ArrowRight className="size-[1em] text-xl" />
                </a>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
