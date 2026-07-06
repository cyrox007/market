import Hero from './components/Hero';
import Categories from './components/Categories';
import FeaturedProducts from './components/FeaturedProducts';
import InteriorIdeas from './components/InteriorIdeas';
import WhyChooseUs from './components/WhyChooseUs';
import Newsletter from './components/Newsletter';

export default function Home() {
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
