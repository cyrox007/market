import Hero from './components/Hero';
import Categories from './components/Categories';
import FeaturedProducts from './components/FeaturedProducts';
import InteriorIdeas from './components/InteriorIdeas';
import WhyChooseUs from './components/WhyChooseUs';
import Newsletter from './components/Newsletter';

import { usePageSeo } from '../../hooks/usePageSeo';

export default function Home() {
	usePageSeo({
		title: 'Главная – Светофор-Мебель',
		description: 'Интернет-магазин качественной мебели. Диваны, кровати, шкафы, кухни и многое другое. Доставка по всей России.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'index, follow',
		open_graph_title: 'Главная – Светофор-Мебель',
		locale: 'ru_RU',
	});
	
	return (
		<div className="min-h-screen bg-white">
			<Hero />
			<Categories />
			<FeaturedProducts />
			<InteriorIdeas />
			<WhyChooseUs />
			<Newsletter />
		</div>
	);
}
