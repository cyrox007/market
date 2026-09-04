import { Link } from 'react-router-dom';
import PhoneInput from '../../components/ui/PhoneInput';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import Icon from '../../components/ui/icons/Icon';
import { Bus, Calculator, Car, ChevronRight, Headset, Mail, MapPin, Phone, ShoppingBag, TrainFront, Truck, Undo2, Users } from 'lucide-react';
import { VkIcon, WhatsAppIcon } from '../../components/ui/icons/brands';

export default function Contacts() {
	usePageSeo({
		title: buildTitle('Контакты'),
		description: 'Контакты интернет-магазина Светофор-Мебель. Адреса магазинов, телефоны, режим работы.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'index, follow',
		open_graph_title: buildTitle('Контакты'),
		locale: 'ru_RU',
	});
	return (
		<div className="min-h-screen bg-white">

			{/* Hero */}
			<div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-20">
				<div className="max-w-7xl mx-auto px-4 text-center">
					<h1 className="text-5xl font-bold mb-4">Контакты</h1>
					<p className="text-xl opacity-90">Мы всегда рады помочь вам</p>
				</div>
			</div>

			<div className="max-w-7xl mx-auto px-4 py-16">
				{/* Breadcrumbs */}
				<div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
					<Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
					<ChevronRight className="size-[1em] text-gray-400" />
					<span className="text-gray-900">Контакты</span>
				</div>

				{/* Contact Cards */}
				<div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
					<div className="bg-red-50 rounded-2xl p-8 text-center">
						<div className="w-20 h-20 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-6">
							<Phone className="size-[1em] text-4xl text-white" />
						</div>
						<h3 className="text-2xl font-bold mb-4">Телефон</h3>
						<p className="text-gray-600 mb-4">Звоните нам ежедневно</p>
						<a href="tel:+78001234567" className="text-2xl font-bold text-red-600 hover:underline block mb-2">
							+7 (800) 123-45-67
						</a>
						<p className="text-sm text-gray-500">Бесплатно по России</p>
						<p className="text-gray-700 mt-4">Пн-Вс: 9:00 - 21:00</p>
					</div>

					<div className="bg-yellow-50 rounded-2xl p-8 text-center">
						<div className="w-20 h-20 bg-yellow-600 rounded-full flex items-center justify-center mx-auto mb-6">
							<Mail className="size-[1em] text-4xl text-white" />
						</div>
						<h3 className="text-2xl font-bold mb-4">Email</h3>
						<p className="text-gray-600 mb-4">Напишите нам</p>
						<a href="mailto:info@svetofor-mebel.ru" className="text-xl font-bold text-yellow-600 hover:underline block mb-2">
							info@svetofor-mebel.ru
						</a>
						<a href="mailto:support@svetofor-mebel.ru" className="text-xl font-bold text-yellow-600 hover:underline block">
							support@svetofor-mebel.ru
						</a>
						<p className="text-gray-700 mt-4">Ответим в течение 1 часа</p>
					</div>

					<div className="bg-green-50 rounded-2xl p-8 text-center">
						<div className="w-20 h-20 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
							<MapPin className="size-[1em] text-4xl text-white" />
						</div>
						<h3 className="text-2xl font-bold mb-4">Адрес офиса</h3>
						<p className="text-gray-600 mb-4">Главный офис</p>
						<p className="text-lg font-semibold text-gray-900 mb-2">
							г. Москва, ул. Ленина, 123
						</p>
						<p className="text-gray-700">БЦ "Светофор", 5 этаж</p>
						<p className="text-gray-700 mt-4">Пн-Пт: 9:00 - 18:00</p>
					</div>
				</div>

				{/* Map and Form */}
				<div className="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-16">
					{/* Map */}
					<div>
						<h2 className="text-3xl font-bold mb-6">Как нас найти</h2>
						<div className="bg-gray-100 rounded-2xl overflow-hidden h-96 mb-6">
							<iframe
								src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2245.4926492354165!2d37.61842315!3d55.75124425!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x46b54a50b315e573%3A0xa886bf5a3d9b2e68!2z0JzQvtGB0LrQstCw!5e0!3m2!1sru!2sru!4v1234567890"
								width="100%"
								height="100%"
								style={{ border: 0 }}
								allowFullScreen
								loading="lazy"
							></iframe>
						</div>
						<div className="space-y-4">
							<div className="flex items-start gap-3">
								<TrainFront className="size-[1em] text-red-600 text-xl flex-shrink-0 mt-1" />
								<div>
									<p className="font-semibold">Метро</p>
									<p className="text-gray-600">Станция "Площадь Революции", 5 минут пешком</p>
								</div>
							</div>
							<div className="flex items-start gap-3">
								<Car className="size-[1em] text-red-600 text-xl flex-shrink-0 mt-1" />
								<div>
									<p className="font-semibold">Парковка</p>
									<p className="text-gray-600">Подземная парковка, первые 2 часа бесплатно</p>
								</div>
							</div>
							<div className="flex items-start gap-3">
								<Bus className="size-[1em] text-red-600 text-xl flex-shrink-0 mt-1" />
								<div>
									<p className="font-semibold">Общественный транспорт</p>
									<p className="text-gray-600">Автобусы: 12, 25, 45. Остановка "Центральная"</p>
								</div>
							</div>
						</div>
					</div>

					{/* Contact Form */}
					<div>
						<h2 className="text-3xl font-bold mb-6">Напишите нам</h2>
						<form className="space-y-6">
							<div>
								<label className="block text-sm font-medium mb-2">Ваше имя *</label>
								<input
									type="text"
									required
									className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
									placeholder="Иван Иванов"
								/>
							</div>
							<div>
								<label className="block text-sm font-medium mb-2">Email *</label>
								<input
									type="email"
									required
									className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
									placeholder="example@mail.ru"
								/>
							</div>
							<div>
								<label className="block text-sm font-medium mb-2">Телефон *</label>
								<PhoneInput
									type="tel"
									required
									className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
									placeholder="+7 (999) 123-45-67"
								/>
							</div>
							<div>
								<label className="block text-sm font-medium mb-2">Тема обращения</label>
								<select className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none pr-8">
									<option>Вопрос о товаре</option>
									<option>Доставка и оплата</option>
									<option>Гарантия и возврат</option>
									<option>Сотрудничество</option>
									<option>Другое</option>
								</select>
							</div>
							<div>
								<label className="block text-sm font-medium mb-2">Сообщение *</label>
								<textarea
									required
									rows={5}
									maxLength={500}
									className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none resize-none"
									placeholder="Опишите ваш вопрос..."
								></textarea>
							</div>
							<button
								type="submit"
								className="w-full bg-red-600 text-white py-4 rounded-lg font-medium text-lg hover:bg-red-700 transition-colors whitespace-nowrap"
							>
								Отправить сообщение
							</button>
							<p className="text-sm text-gray-500 text-center">
								Нажимая кнопку, вы соглашаетесь с политикой конфиденциальности
							</p>
						</form>
					</div>
				</div>

				{/* Social Media */}
				<div className="bg-gray-50 rounded-2xl p-12 text-center">
					<h2 className="text-3xl font-bold mb-6">Мы в социальных сетях</h2>
					<p className="text-gray-600 mb-8">Следите за новостями и акциями</p>
					<div className="flex justify-center gap-4">
						{[
							{ Logo: VkIcon, color: 'text-blue-600', name: 'ВКонтакте' },
							{ Logo: WhatsAppIcon, color: 'text-green-600', name: 'WhatsApp' },
							/* Telegram, Instagram и YouTube убраны: логотипов нет ни в Lucide,
							   ни в макете. Вернуть вместе с SVG:
							{ Logo: TelegramIcon, color: 'text-sky-500', name: 'Telegram' },
							{ Logo: InstagramIcon, color: 'text-pink-600', name: 'Instagram' },
							{ Logo: YoutubeIcon, color: 'text-red-600', name: 'YouTube' },
							*/
						].map(({ Logo, color, name }) => (
							<a
								key={name}
								href="#"
								className={`w-14 h-14 ${color} hover:opacity-80 transition-opacity cursor-pointer`}
								title={name}
							>
								<Logo className="w-full h-full" />
							</a>
						))}
					</div>
				</div>

				{/* Departments */}
				<div className="mt-16">
					<h2 className="text-3xl font-bold mb-8 text-center">Отделы компании</h2>
					<div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
						{[
							{ title: 'Отдел продаж', phone: '+7 (800) 123-45-67', email: 'sales@svetofor-mebel.ru', icon: ShoppingBag, color: 'red' },
							{ title: 'Служба поддержки', phone: '+7 (800) 123-45-68', email: 'support@svetofor-mebel.ru', icon: Headset, color: 'yellow' },
							{ title: 'Отдел доставки', phone: '+7 (800) 123-45-69', email: 'delivery@svetofor-mebel.ru', icon: Truck, color: 'green' },
							{ title: 'Отдел возвратов', phone: '+7 (800) 123-45-70', email: 'returns@svetofor-mebel.ru', icon: Undo2, color: 'red' },
							{ title: 'Отдел кадров', phone: '+7 (800) 123-45-71', email: 'hr@svetofor-mebel.ru', icon: Users, color: 'yellow' },
							{ title: 'Бухгалтерия', phone: '+7 (800) 123-45-72', email: 'accounting@svetofor-mebel.ru', icon: Calculator, color: 'green' }
						].map((dept, idx) => (
							<div key={idx} className={`bg-${dept.color}-50 rounded-xl p-6`}>
								<div className={`w-12 h-12 bg-${dept.color}-600 rounded-full flex items-center justify-center mb-4`}>
									<Icon name={dept.icon} className="size-[1em] text-2xl text-white" />
								</div>
								<h3 className="font-bold text-lg mb-3">{dept.title}</h3>
								<div className="space-y-2">
									<a href={`tel:${dept.phone}`} className="flex items-center gap-2 text-gray-700 hover:text-red-600">
										<Phone className="size-[1em]" />
										<span>{dept.phone}</span>
									</a>
									<a href={`mailto:${dept.email}`} className="flex items-center gap-2 text-gray-700 hover:text-red-600">
										<Mail className="size-[1em]" />
										<span className="text-sm">{dept.email}</span>
									</a>
								</div>
							</div>
						))}
					</div>
				</div>
			</div>

		</div>
	);
}
