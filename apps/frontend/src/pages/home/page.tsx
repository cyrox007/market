import { PromoBoardConnected } from './components/PromoBoard';
import { CategoryCarouselConnected } from './components/CategoryCarousel';
import { PromoBannerConnected } from './components/PromoBanner';
import FeaturedProducts from './components/FeaturedProducts';
// Прежние блоки «Идеи для интерьера» и подписки не входят в действующий макет главной.

import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';

export default function Home() {
  usePageSeo({
    title: buildTitle('Главная'),
    description:
      'Интернет-магазин качественной мебели. Диваны, кровати, шкафы, кухни и многое другое. Доставка по всей России.',
    image: '/logo.png',
    robots: 'index, follow',
    open_graph_title: buildTitle('Главная'),
    locale: 'ru_RU',
  });

  return (
    <div className="min-h-screen bg-white pb-[54px]">
      <PromoBoardConnected />
      <CategoryCarouselConnected />
      <FeaturedProducts />
      <PromoBannerConnected />
    </div>
  );
}
