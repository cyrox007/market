import { Link } from 'react-router-dom';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import Icon from '../../components/ui/icons/Icon';
import { Check, ChevronRight, MapPin, Phone, Settings, ShoppingCart, Truck, Wrench, Zap } from 'lucide-react';

export default function Delivery() {
	usePageSeo({
		title: buildTitle('Доставка'),
		description: 'Условия доставки в интернет-магазине Светофор-Мебель.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'index, follow',
		open_graph_title: buildTitle('Доставка'),
		locale: 'ru_RU',
	});
	
	return (
		<div className="min-h-screen bg-white">

			{/* Hero */}
			<div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-20">
				<div className="max-w-7xl mx-auto px-4 text-center">
					<h1 className="text-5xl font-bold mb-4">Доставка и сборка</h1>
					<p className="text-xl opacity-90">Быстрая доставка и профессиональная сборка мебели</p>
				</div>
			</div>

			<div className="max-w-7xl mx-auto px-4 py-16">
				{/* Breadcrumbs */}
				<div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
					<Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
					<ChevronRight className="size-[1em] text-gray-400" />
					<span className="text-gray-900">Доставка</span>
				</div>

				{/* Delivery Options */}
				<div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
					<div className="bg-white border-2 border-red-600 rounded-2xl p-8 text-center">
						<div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
							<Truck className="size-[1em] text-4xl text-red-600" />
						</div>
						<h3 className="text-2xl font-bold mb-4">Стандартная доставка</h3>
						<p className="text-4xl font-bold text-red-600 mb-4">Бесплатно</p>
						<p className="text-gray-600 mb-6">При заказе от 30 000 ₽</p>
						<ul className="text-left space-y-2 text-gray-700">
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Доставка 1-3 дня
							</li>
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Подъем на этаж
							</li>
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Занос в квартиру
							</li>
						</ul>
					</div>

					<div className="bg-white border-2 border-yellow-600 rounded-2xl p-8 text-center">
						<div className="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
							<Zap className="size-[1em] text-4xl text-yellow-600" />
						</div>
						<h3 className="text-2xl font-bold mb-4">Экспресс-доставка</h3>
						<p className="text-4xl font-bold text-yellow-600 mb-4">1 500 ₽</p>
						<p className="text-gray-600 mb-6">Доставка в день заказа</p>
						<ul className="text-left space-y-2 text-gray-700">
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Доставка в течение 4 часов
							</li>
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Приоритетная обработка
							</li>
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Гарантированное время
							</li>
						</ul>
					</div>

					<div className="bg-white border-2 border-green-600 rounded-2xl p-8 text-center">
						<div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
							<MapPin className="size-[1em] text-4xl text-green-600" />
						</div>
						<h3 className="text-2xl font-bold mb-4">Доставка в регионы</h3>
						<p className="text-4xl font-bold text-green-600 mb-4">От 2 000 ₽</p>
						<p className="text-gray-600 mb-6">По всей России</p>
						<ul className="text-left space-y-2 text-gray-700">
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Доставка 3-7 дней
							</li>
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Транспортные компании
							</li>
							<li className="flex items-center gap-2">
								<Check className="size-[1em] text-green-600" />
								Страхование груза
							</li>
						</ul>
					</div>
				</div>

				{/* Assembly Services */}
				<div className="bg-gray-50 rounded-2xl p-12 mb-16">
					<h2 className="text-3xl font-bold mb-8 text-center">Услуги сборки</h2>
					<div className="grid grid-cols-1 md:grid-cols-2 gap-8">
						<div className="bg-white rounded-xl p-6">
							<div className="flex items-start gap-4 mb-4">
								<div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
									<Wrench className="size-[1em] text-red-600 text-xl" />
								</div>
								<div>
									<h3 className="text-xl font-bold mb-2">Стандартная сборка</h3>
									<p className="text-2xl font-bold text-red-600 mb-2">Бесплатно</p>
									<p className="text-gray-600">При покупке от 30 000 ₽</p>
								</div>
							</div>
							<ul className="space-y-2 text-gray-700">
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Сборка в день доставки
								</li>
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Профессиональные мастера
								</li>
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Гарантия на сборку 12 месяцев
								</li>
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Вывоз упаковки
								</li>
							</ul>
						</div>

						<div className="bg-white rounded-xl p-6">
							<div className="flex items-start gap-4 mb-4">
								<div className="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0">
									<Settings className="size-[1em] text-yellow-600 text-xl" />
								</div>
								<div>
									<h3 className="text-xl font-bold mb-2">Премиум сборка</h3>
									<p className="text-2xl font-bold text-yellow-600 mb-2">От 2 000 ₽</p>
									<p className="text-gray-600">Расширенный сервис</p>
								</div>
							</div>
							<ul className="space-y-2 text-gray-700">
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Все услуги стандартной сборки
								</li>
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Установка на место
								</li>
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Регулировка и настройка
								</li>
								<li className="flex items-center gap-2">
									<Check className="size-[1em] text-green-600" />
									Консультация по уходу
								</li>
							</ul>
						</div>
					</div>
				</div>

				{/* How It Works */}
				<div className="mb-16">
					<h2 className="text-3xl font-bold mb-12 text-center">Как это работает</h2>
					<div className="grid grid-cols-1 md:grid-cols-4 gap-8">
						{[
							{
								step: '1',
								title: 'Оформление заказа',
								description: 'Выберите товар и оформите заказ на сайте или в магазине',
								icon: ShoppingCart,
								color: 'red'
							},
							{
								step: '2',
								title: 'Подтверждение',
								description: 'Менеджер свяжется с вами для уточнения деталей',
								icon: Phone,
								color: 'yellow'
							},
							{
								step: '3',
								title: 'Доставка',
								description: 'Доставим мебель в удобное для вас время',
								icon: Truck,
								color: 'green'
							},
							{
								step: '4',
								title: 'Сборка',
								description: 'Соберем и установим мебель на место',
								icon: Wrench,
								color: 'red'
							}
						].map((item, idx) => (
							<div key={idx} className="text-center">
								<div className={`w-20 h-20 bg-${item.color}-100 rounded-full flex items-center justify-center mx-auto mb-4`}>
									<Icon name={item.icon} className={`size-[1em] text-4xl text-${item.color}-600`} />
								</div>
								<div className={`text-4xl font-bold text-${item.color}-600 mb-2`}>{item.step}</div>
								<h3 className="text-xl font-bold mb-2">{item.title}</h3>
								<p className="text-gray-600">{item.description}</p>
							</div>
						))}
					</div>
				</div>

				{/* FAQ */}
				<div className="bg-white border border-gray-200 rounded-2xl p-12">
					<h2 className="text-3xl font-bold mb-8 text-center">Часто задаваемые вопросы</h2>
					<div className="space-y-6">
						{[
							{
								question: 'Как рассчитывается стоимость доставки?',
								answer: 'Доставка бесплатна при заказе от 30 000 ₽. Для заказов меньшей суммы стоимость рассчитывается индивидуально в зависимости от габаритов товара и адреса доставки.'
							},
							{
								question: 'Можно ли выбрать точное время доставки?',
								answer: 'Да, при оформлении заказа вы можете выбрать удобный временной интервал. Наш менеджер свяжется с вами за день до доставки для уточнения времени.'
							},
							{
								question: 'Что входит в услугу сборки?',
								answer: 'Стандартная сборка включает: распаковку товара, сборку согласно инструкции, установку на место, вывоз упаковки. Гарантия на сборку - 12 месяцев.'
							},
							{
								question: 'Как происходит доставка крупногабаритной мебели?',
								answer: 'Крупногабаритная мебель доставляется специальным транспортом. Наши грузчики занесут товар в квартиру и поднимут на нужный этаж (лифтом или по лестнице).'
							},
							{
								question: 'Что делать, если товар не подошел?',
								answer: 'Вы можете вернуть товар в течение 14 дней с момента получения. Подробнее об условиях возврата читайте в разделе "Возврат товара".'
							}
						].map((item, idx) => (
							<div key={idx} className="border-b border-gray-200 pb-6 last:border-0">
								<h3 className="text-lg font-bold mb-2">{item.question}</h3>
								<p className="text-gray-700">{item.answer}</p>
							</div>
						))}
					</div>
				</div>
			</div>

		</div>
	);
}
