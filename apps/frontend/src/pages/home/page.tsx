import { PromoBoardConnected } from './components/PromoBoard';
import { CategoryCarouselConnected } from './components/CategoryCarousel';
import FeaturedProducts from './components/FeaturedProducts';
import InteriorIdeas from './components/InteriorIdeas';
import WhyChooseUs from './components/WhyChooseUs';
import Newsletter from './components/Newsletter';

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
    <div className="min-h-screen bg-white">
      <PromoBoardConnected />
      <CategoryCarouselConnected />
      <FeaturedProducts />
      <InteriorIdeas />
      <WhyChooseUs />
      <Newsletter />
    </div>
  );
}
