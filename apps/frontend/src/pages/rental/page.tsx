import { buildTitle } from '../../constants/seo';
import { usePageSeo } from '../../hooks/usePageSeo';
import {
  Building2,
  ChevronRight,
  CircleCheck,
  MapPin,
  Megaphone,
  Snowflake,
  Sun,
  ThermometerSun,
} from 'lucide-react';

export default function Rental() {
  usePageSeo({
    title: buildTitle('Аренда'),
    description: 'Аренда мебели в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    robots: 'index, follow',
    open_graph_title: buildTitle('Аренда'),
    locale: 'ru_RU',
  });

  return (
    <div className="min-h-screen bg-white">
      {/* Hero */}
      <div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-20">
        <div className="max-w-7xl mx-auto px-4 text-center">
          <h1 className="text-5xl font-bold mb-4">Аренда торговых помещений</h1>
          <p className="text-xl opacity-90">Рассмотрим ваши предложения</p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-16">
        {/* Introduction */}
        <div className="bg-white border-2 border-gray-200 rounded-2xl p-8 md:p-12 mb-12">
          <p className="text-lg text-gray-700 leading-relaxed">
            Наша компания готова рассмотреть предложения по аренде торговых помещений.
          </p>
          <div className="mt-6 bg-red-50 border-l-4 border-red-600 p-6 rounded-r-lg">
            <p className="text-gray-800 font-medium">
              Свои предложения присылайте на электронный адрес:
            </p>
            <a
              href="mailto:mebelr13@mail.ru"
              className="text-red-600 text-xl font-bold hover:text-red-700 transition-colors"
            >
              mebelr13@mail.ru
            </a>
          </div>
        </div>

        {/* Requirements */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
          {/* Location Requirements */}
          <div className="bg-white border-2 border-gray-200 rounded-2xl p-8">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                <MapPin className="size-[1em] text-red-600 text-2xl" />
              </div>
              <h2 className="text-2xl font-bold">Требования к месторасположению</h2>
            </div>
            <ul className="space-y-4">
              <li className="flex items-start gap-3">
                <CircleCheck className="size-[1em] text-green-600 text-xl mt-1" />
                <span className="text-gray-700">Пересечение крупных автомагистралей</span>
              </li>
              <li className="flex items-start gap-3">
                <CircleCheck className="size-[1em] text-green-600 text-xl mt-1" />
                <span className="text-gray-700">Близость транспортных узлов</span>
              </li>
              <li className="flex items-start gap-3">
                <CircleCheck className="size-[1em] text-green-600 text-xl mt-1" />
                <span className="text-gray-700">
                  Удобство подъезда на личном и общественном транспорте, высокий пешеходный трафик
                </span>
              </li>
              <li className="flex items-start gap-3">
                <CircleCheck className="size-[1em] text-green-600 text-xl mt-1" />
                <span className="text-gray-700">Наличие автостоянки</span>
              </li>
            </ul>
          </div>

          {/* Object Requirements */}
          <div className="bg-white border-2 border-gray-200 rounded-2xl p-8">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                <Building2 className="size-[1em] text-yellow-600 text-2xl" />
              </div>
              <h2 className="text-2xl font-bold">Требования к объекту</h2>
            </div>
            <div className="space-y-6">
              <div>
                <h3 className="font-bold text-lg mb-3 text-gray-800">Площади:</h3>
                <ul className="space-y-2 ml-4">
                  <li className="flex items-start gap-2">
                    <span className="text-red-600 font-bold">•</span>
                    <span className="text-gray-700">
                      <strong>Общая:</strong> от 450 до 2 000 м²
                    </span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-red-600 font-bold">•</span>
                    <span className="text-gray-700">
                      <strong>Торговая:</strong> не менее 350 м²
                    </span>
                  </li>
                  <li className="flex items-start gap-2">
                    <span className="text-red-600 font-bold">•</span>
                    <span className="text-gray-700">
                      <strong>Склад и служебные помещения:</strong> примерно 100 м²
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        {/* Additional Requirements */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
          {/* Advertising */}
          <div className="bg-white border-2 border-gray-200 rounded-2xl p-8">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <Megaphone className="size-[1em] text-green-600 text-2xl" />
              </div>
              <h2 className="text-2xl font-bold">Реклама</h2>
            </div>
            <ul className="space-y-3 text-gray-700">
              <li className="flex items-start gap-2">
                <ChevronRight className="size-[1em] text-red-600 mt-1" />
                <span>Бесплатное размещение наружного рекламного оформления магазина</span>
              </li>
              <li className="flex items-start gap-2">
                <ChevronRight className="size-[1em] text-red-600 mt-1" />
                <span>Не менее 2х вывесок (на крыше и фасаде) + 2 баннера</span>
              </li>
              <li className="flex items-start gap-2">
                <ChevronRight className="size-[1em] text-red-600 mt-1" />
                <span>Предусмотреть подвод электропитания к наружным рекламным конструкциям</span>
              </li>
              <li className="flex items-start gap-2">
                <ChevronRight className="size-[1em] text-red-600 mt-1" />
                <span>
                  Электропитание организовать от узла учёта Арендатора + выполнить закладные детали
                </span>
              </li>
            </ul>
          </div>

          {/* Temperature */}
          <div className="bg-white border-2 border-gray-200 rounded-2xl p-8">
            <div className="flex items-center gap-3 mb-6">
              <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                <ThermometerSun className="size-[1em] text-red-600 text-2xl" />
              </div>
              <h2 className="text-2xl font-bold">Температура воздуха в помещениях</h2>
            </div>
            <div className="space-y-4">
              <div className="bg-blue-50 rounded-lg p-4">
                <div className="flex items-center gap-3">
                  <Snowflake className="size-[1em] text-blue-600 text-2xl" />
                  <div>
                    <p className="font-bold text-gray-800">Зимнее время</p>
                    <p className="text-gray-700">Не ниже +18°C</p>
                  </div>
                </div>
              </div>
              <div className="bg-orange-50 rounded-lg p-4">
                <div className="flex items-center gap-3">
                  <Sun className="size-[1em] text-orange-600 text-2xl" />
                  <div>
                    <p className="font-bold text-gray-800">Летнее время</p>
                    <p className="text-gray-700">Не выше +23°C</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Priority Cities */}
        <div className="bg-gradient-to-br from-red-50 to-yellow-50 rounded-2xl p-8 md:p-12">
          <div className="text-center mb-8">
            <h2 className="text-3xl font-bold mb-3">Приоритетные направления</h2>
            <p className="text-gray-600">Города, в которых мы рассматриваем открытие магазинов</p>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {[
              'Елец',
              'Тула',
              'Новомосковск',
              'Рязань',
              'Тамбов',
              'Мичуринск',
              'Орел',
              'Курск',
              'Калуга',
              'Обнинск',
              'Брянск',
            ].map((city, index) => (
              <div
                key={index}
                className="bg-white rounded-xl p-4 text-center shadow-sm hover:shadow-md transition-shadow"
              >
                <MapPin className="size-[1em] text-red-600 text-2xl mb-2" />
                <p className="font-semibold text-gray-800">{city}</p>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
