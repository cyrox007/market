import { PromoBoardConnected } from './components/PromoBoard';
import { CategoryCarouselConnected } from './components/CategoryCarousel';
import { PromoBannerConnected } from './components/PromoBanner';
import FeaturedProducts from './components/FeaturedProducts';
// В макете главной этих разделов нет. Сами файлы оставлены — вёрстка Readdy, пойдёт под замену.
// import InteriorIdeas from './components/InteriorIdeas';
// import WhyChooseUs from './components/WhyChooseUs';
// import Newsletter from './components/Newsletter';

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
      {/* <InteriorIdeas /> */}
      {/* <WhyChooseUs /> */}
      {/* <Newsletter /> */}
    </div>
  );
}
