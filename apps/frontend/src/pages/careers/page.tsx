import { useState } from 'react';
import PhoneInput from '../../components/ui/PhoneInput';
import { buildTitle } from '../../constants/seo';
import { usePageSeo } from '../../hooks/usePageSeo';
import {
  Building2,
  ChartLine,
  Check,
  CircleDollarSign,
  Clock,
  CloudUpload,
  GraduationCap,
  MapPin,
  Users,
} from 'lucide-react';

const vacancies = [
  {
    id: 1,
    title: 'Продавец-консультант',
    department: 'Продажи',
    location: 'Москва, ТЦ "Мега"',
    type: 'Полная занятость',
    salary: 'от 60 000 ₽',
    experience: 'Без опыта',
    responsibilities: [
      'Консультирование покупателей по ассортименту',
      'Помощь в выборе мебели',
      'Оформление заказов',
      'Поддержание порядка в торговом зале',
    ],
    requirements: [
      'Коммуникабельность',
      'Желание работать с людьми',
      'Ответственность',
      'Обучаемость',
    ],
  },
  {
    id: 2,
    title: 'Дизайнер интерьеров',
    department: 'Дизайн',
    location: 'Москва, офис',
    type: 'Полная занятость',
    salary: 'от 80 000 ₽',
    experience: 'От 1 года',
    responsibilities: [
      'Разработка дизайн-проектов интерьеров',
      'Подбор мебели для клиентов',
      'Создание 3D-визуализаций',
      'Работа с клиентами',
    ],
    requirements: [
      'Опыт работы дизайнером от 1 года',
      'Знание программ 3D Max, AutoCAD',
      'Портфолио работ',
      'Чувство стиля',
    ],
  },
  {
    id: 3,
    title: 'Водитель-экспедитор',
    department: 'Логистика',
    location: 'Москва',
    type: 'Полная занятость',
    salary: 'от 70 000 ₽',
    experience: 'От 2 лет',
    responsibilities: [
      'Доставка мебели клиентам',
      'Погрузка и разгрузка товара',
      'Контроль сохранности груза',
      'Ведение документации',
    ],
    requirements: [
      'Права категории B, C',
      'Опыт вождения от 2 лет',
      'Знание города',
      'Ответственность',
    ],
  },
  {
    id: 4,
    title: 'Сборщик мебели',
    department: 'Сервис',
    location: 'Москва',
    type: 'Полная занятость',
    salary: 'от 75 000 ₽',
    experience: 'От 1 года',
    responsibilities: [
      'Сборка мебели у клиентов',
      'Установка и регулировка',
      'Консультирование по эксплуатации',
      'Гарантийное обслуживание',
    ],
    requirements: [
      'Опыт сборки мебели от 1 года',
      'Наличие инструмента',
      'Аккуратность',
      'Пунктуальность',
    ],
  },
  {
    id: 5,
    title: 'SMM-менеджер',
    department: 'Маркетинг',
    location: 'Москва, офис',
    type: 'Полная занятость',
    salary: 'от 65 000 ₽',
    experience: 'От 1 года',
    responsibilities: [
      'Ведение социальных сетей компании',
      'Создание контента',
      'Работа с блогерами',
      'Анализ эффективности',
    ],
    requirements: [
      'Опыт ведения соцсетей от 1 года',
      'Знание трендов',
      'Креативность',
      'Грамотная речь',
    ],
  },
  {
    id: 6,
    title: 'Менеджер по закупкам',
    department: 'Закупки',
    location: 'Москва, офис',
    type: 'Полная занятость',
    salary: 'от 90 000 ₽',
    experience: 'От 2 лет',
    responsibilities: [
      'Поиск и работа с поставщиками',
      'Ведение переговоров',
      'Контроль качества товара',
      'Оптимизация закупок',
    ],
    requirements: [
      'Опыт работы в закупках от 2 лет',
      'Знание рынка мебели',
      'Навыки переговоров',
      'Английский язык приветствуется',
    ],
  },
];

export default function Careers() {
  const [phone, setPhone] = useState('');
  usePageSeo({
    title: buildTitle('Вакансии'),
    description: 'Работа в компании Светофор-Мебель. Вакансии и условия.',
    image: '/logo.png',
    robots: 'index, follow',
    open_graph_title: buildTitle('Вакансии'),
    locale: 'ru_RU',
  });

  return (
    <div className="min-h-screen bg-white">
      {/* Hero */}
      <div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-20">
        <div className="max-w-7xl mx-auto px-4 text-center">
          <h1 className="text-5xl font-bold mb-4">Вакансии</h1>
          <p className="text-xl opacity-90">Присоединяйтесь к нашей команде профессионалов</p>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-16">
        {/* Why Work With Us */}
        <div className="mb-16">
          <h2 className="text-3xl font-bold mb-8 text-center">Почему стоит работать у нас</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div className="bg-red-50 rounded-xl p-6 text-center">
              <div className="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <CircleDollarSign className="size-[1em] text-3xl text-white" />
              </div>
              <h3 className="font-bold text-lg mb-2">Достойная зарплата</h3>
              <p className="text-gray-600">Конкурентная оплата труда и бонусы</p>
            </div>
            <div className="bg-yellow-50 rounded-xl p-6 text-center">
              <div className="w-16 h-16 bg-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <ChartLine className="size-[1em] text-3xl text-white" />
              </div>
              <h3 className="font-bold text-lg mb-2">Карьерный рост</h3>
              <p className="text-gray-600">Возможности для развития</p>
            </div>
            <div className="bg-green-50 rounded-xl p-6 text-center">
              <div className="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <GraduationCap className="size-[1em] text-3xl text-white" />
              </div>
              <h3 className="font-bold text-lg mb-2">Обучение</h3>
              <p className="text-gray-600">Тренинги и курсы повышения квалификации</p>
            </div>
            <div className="bg-red-50 rounded-xl p-6 text-center">
              <div className="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <Users className="size-[1em] text-3xl text-white" />
              </div>
              <h3 className="font-bold text-lg mb-2">Дружный коллектив</h3>
              <p className="text-gray-600">Комфортная атмосфера в команде</p>
            </div>
          </div>
        </div>

        {/* Vacancies */}
        <div className="mb-16">
          <h2 className="text-3xl font-bold mb-8">Открытые вакансии</h2>
          <div className="space-y-6">
            {vacancies.map((vacancy) => (
              <div
                key={vacancy.id}
                className="bg-white border border-gray-200 rounded-2xl p-8 hover:shadow-lg transition-shadow"
              >
                <div className="flex flex-wrap items-start justify-between gap-4 mb-6">
                  <div>
                    <h3 className="text-2xl font-bold mb-2">{vacancy.title}</h3>
                    <div className="flex flex-wrap gap-3">
                      <span className="flex items-center gap-2 text-gray-600">
                        <Building2 className="size-[1em]" />
                        {vacancy.department}
                      </span>
                      <span className="flex items-center gap-2 text-gray-600">
                        <MapPin className="size-[1em]" />
                        {vacancy.location}
                      </span>
                      <span className="flex items-center gap-2 text-gray-600">
                        <Clock className="size-[1em]" />
                        {vacancy.type}
                      </span>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-3xl font-bold text-red-600 mb-1">{vacancy.salary}</p>
                    <p className="text-sm text-gray-500">Опыт: {vacancy.experience}</p>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                  <div>
                    <h4 className="font-bold mb-3">Обязанности:</h4>
                    <ul className="space-y-2">
                      {vacancy.responsibilities.map((item, idx) => (
                        <li key={idx} className="flex items-start gap-2 text-gray-700">
                          <Check className="size-[1em] text-green-600 flex-shrink-0 mt-1" />
                          <span>{item}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                  <div>
                    <h4 className="font-bold mb-3">Требования:</h4>
                    <ul className="space-y-2">
                      {vacancy.requirements.map((item, idx) => (
                        <li key={idx} className="flex items-start gap-2 text-gray-700">
                          <Check className="size-[1em] text-green-600 flex-shrink-0 mt-1" />
                          <span>{item}</span>
                        </li>
                      ))}
                    </ul>
                  </div>
                </div>

                <button className="bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors whitespace-nowrap">
                  Откликнуться на вакансию
                </button>
              </div>
            ))}
          </div>
        </div>

        {/* Application Form */}
        <div className="bg-gray-50 rounded-2xl p-12">
          <h2 className="text-3xl font-bold mb-8 text-center">Не нашли подходящую вакансию?</h2>
          <p className="text-center text-gray-600 mb-8">
            Отправьте нам свое резюме, и мы свяжемся с вами при появлении подходящей позиции
          </p>
          <form className="max-w-2xl mx-auto space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                <label className="block text-sm font-medium mb-2">Телефон *</label>
                <PhoneInput
                  type="tel"
                  required
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
                  placeholder="+7 (999) 123-45-67"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                />
              </div>
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
              <label className="block text-sm font-medium mb-2">Желаемая должность</label>
              <input
                type="text"
                className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
                placeholder="Например: Продавец-консультант"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">О себе</label>
              <textarea
                rows={4}
                maxLength={500}
                className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none resize-none"
                placeholder="Расскажите о своем опыте и навыках..."
              ></textarea>
            </div>
            <div>
              <label className="block text-sm font-medium mb-2">Резюме</label>
              <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-red-600 transition-colors cursor-pointer">
                <CloudUpload className="size-[1em] text-4xl text-gray-400 mb-2" />
                <p className="text-gray-600">Прикрепите файл резюме (PDF, DOC, DOCX)</p>
                <p className="text-sm text-gray-400 mt-2">Максимальный размер: 5 МБ</p>
              </div>
            </div>
            <button
              type="submit"
              className="w-full bg-red-600 text-white py-4 rounded-lg font-medium text-lg hover:bg-red-700 transition-colors whitespace-nowrap"
            >
              Отправить резюме
            </button>
            <p className="text-sm text-gray-500 text-center">
              Нажимая кнопку, вы соглашаетесь с политикой конфиденциальности
            </p>
          </form>
        </div>
      </div>
    </div>
  );
}
