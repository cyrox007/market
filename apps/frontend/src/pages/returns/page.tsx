import { Link } from 'react-router-dom';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import Icon from '../../components/ui/icons/Icon';
import {
  Check,
  ChevronRight,
  CircleCheck,
  CircleDollarSign,
  CircleX,
  FileText,
  Mail,
  Phone,
  ShieldCheck,
  Truck,
} from 'lucide-react';

export default function Returns() {
  usePageSeo({
    title: buildTitle('Возврат'),
    description: 'Условия возврата товаров в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    robots: 'index, follow',
    open_graph_title: buildTitle('Возврат'),
    locale: 'ru_RU',
  });

  return (
    <div className="min-h-screen bg-white">
      {/* Hero */}
      <div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-20">
        <div className="max-w-7xl mx-auto px-4 text-center">
          <h1 className="text-5xl font-bold mb-4">Возврат товара</h1>
          <p className="text-xl opacity-90">Простая и прозрачная процедура возврата</p>
        </div>
      </div>

      <div className="max-w-4xl mx-auto px-4 py-16">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
          <Link to="/" className="text-gray-600 hover:text-red-600">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Возврат</span>
        </div>

        {/* Main Info */}
        <div className="bg-green-50 border-2 border-green-600 rounded-2xl p-8 mb-12">
          <div className="flex items-start gap-4">
            <div className="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center flex-shrink-0">
              <ShieldCheck className="size-[1em] text-3xl text-white" />
            </div>
            <div>
              <h2 className="text-2xl font-bold mb-3">Гарантия возврата 14 дней</h2>
              <p className="text-gray-700 text-lg">
                Вы можете вернуть товар надлежащего качества в течение 14 дней с момента получения.
                Мы вернем вам полную стоимость товара.
              </p>
            </div>
          </div>
        </div>

        {/* Conditions */}
        <div className="mb-12">
          <h2 className="text-3xl font-bold mb-6">Условия возврата</h2>
          <div className="space-y-4">
            {[
              {
                icon: CircleCheck,
                text: 'Товар не был в употреблении и сохранил товарный вид',
                color: 'green',
              },
              {
                icon: CircleCheck,
                text: 'Сохранены все ярлыки, бирки и заводская упаковка',
                color: 'green',
              },
              {
                icon: CircleCheck,
                text: 'Товар не был собран (для мебели, требующей сборки)',
                color: 'green',
              },
              {
                icon: CircleCheck,
                text: 'Есть документ, подтверждающий покупку (чек, накладная)',
                color: 'green',
              },
              {
                icon: CircleX,
                text: 'Товар, изготовленный на заказ, возврату не подлежит',
                color: 'red',
              },
              {
                icon: CircleX,
                text: 'Товар со следами эксплуатации возврату не подлежит',
                color: 'red',
              },
            ].map((item, idx) => (
              <div key={idx} className="flex items-start gap-3">
                <Icon
                  name={item.icon}
                  className={`size-[1em] text-2xl text-${item.color}-600 flex-shrink-0 mt-1`}
                />
                <p className="text-gray-700 text-lg">{item.text}</p>
              </div>
            ))}
          </div>
        </div>

        {/* How to Return */}
        <div className="mb-12">
          <h2 className="text-3xl font-bold mb-8">Как оформить возврат</h2>
          <div className="space-y-6">
            {[
              {
                step: '1',
                title: 'Свяжитесь с нами',
                description:
                  'Позвоните по телефону +7 (800) 123-45-67 или напишите на email: returns@svetofor-mebel.ru',
                icon: Phone,
                color: 'red',
              },
              {
                step: '2',
                title: 'Заполните заявление',
                description:
                  'Наш менеджер поможет заполнить заявление на возврат и ответит на все вопросы',
                icon: FileText,
                color: 'yellow',
              },
              {
                step: '3',
                title: 'Передайте товар',
                description:
                  'Мы заберем товар по указанному адресу или вы можете привезти его в наш магазин',
                icon: Truck,
                color: 'green',
              },
              {
                step: '4',
                title: 'Получите деньги',
                description: 'После проверки товара мы вернем деньги в течение 10 рабочих дней',
                icon: CircleDollarSign,
                color: 'red',
              },
            ].map((item, idx) => (
              <div key={idx} className="flex gap-6 bg-gray-50 rounded-xl p-6">
                <div
                  className={`w-16 h-16 bg-${item.color}-100 rounded-full flex items-center justify-center flex-shrink-0`}
                >
                  <Icon name={item.icon} className={`size-[1em] text-3xl text-${item.color}-600`} />
                </div>
                <div>
                  <div className={`text-3xl font-bold text-${item.color}-600 mb-2`}>
                    {item.step}
                  </div>
                  <h3 className="text-xl font-bold mb-2">{item.title}</h3>
                  <p className="text-gray-700">{item.description}</p>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Warranty */}
        <div className="bg-yellow-50 border-2 border-yellow-600 rounded-2xl p-8 mb-12">
          <h2 className="text-2xl font-bold mb-4">Гарантийное обслуживание</h2>
          <p className="text-gray-700 mb-4">
            На всю нашу мебель распространяется гарантия производителя сроком от 12 до 24 месяцев.
            Гарантия покрывает производственные дефекты и неисправности.
          </p>
          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <Check className="size-[1em] text-green-600 text-xl flex-shrink-0 mt-1" />
              <p className="text-gray-700">
                Бесплатный ремонт или замена товара при гарантийном случае
              </p>
            </div>
            <div className="flex items-start gap-3">
              <Check className="size-[1em] text-green-600 text-xl flex-shrink-0 mt-1" />
              <p className="text-gray-700">Выезд мастера на дом для диагностики</p>
            </div>
            <div className="flex items-start gap-3">
              <Check className="size-[1em] text-green-600 text-xl flex-shrink-0 mt-1" />
              <p className="text-gray-700">Предоставление подменного товара на время ремонта</p>
            </div>
          </div>
        </div>

        {/* FAQ */}
        <div className="bg-white border border-gray-200 rounded-2xl p-8">
          <h2 className="text-2xl font-bold mb-6">Частые вопросы</h2>
          <div className="space-y-6">
            {[
              {
                question: 'Можно ли вернуть товар, если он просто не подошел?',
                answer:
                  'Да, вы можете вернуть товар надлежащего качества в течение 14 дней, если он не подошел по размеру, цвету или другим характеристикам.',
              },
              {
                question: 'Кто оплачивает доставку при возврате?',
                answer:
                  'Если товар надлежащего качества, доставку оплачивает покупатель. Если товар с браком - доставку оплачиваем мы.',
              },
              {
                question: 'Как быстро вернут деньги?',
                answer:
                  'После получения и проверки товара деньги возвращаются в течение 10 рабочих дней тем же способом, которым была произведена оплата.',
              },
              {
                question: 'Можно ли обменять товар на другой?',
                answer:
                  'Да, вы можете обменять товар на аналогичный другого размера, цвета или модели. Если есть разница в цене, производится доплата или возврат.',
              },
              {
                question: 'Что делать, если обнаружил брак после сборки?',
                answer:
                  'Свяжитесь с нами сразу после обнаружения брака. Мы организуем выезд мастера для осмотра и решим вопрос по гарантии.',
              },
            ].map((item, idx) => (
              <div key={idx} className="border-b border-gray-200 pb-6 last:border-0">
                <h3 className="font-bold text-lg mb-2">{item.question}</h3>
                <p className="text-gray-700">{item.answer}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Contact */}
        <div className="mt-12 text-center bg-gradient-to-r from-red-600 to-yellow-500 text-white rounded-2xl p-8">
          <h2 className="text-2xl font-bold mb-4">Остались вопросы?</h2>
          <p className="text-lg mb-6 opacity-90">Свяжитесь с нами любым удобным способом</p>
          <div className="flex flex-wrap justify-center gap-4">
            <a
              href="tel:+78001234567"
              className="bg-white text-red-600 px-6 py-3 rounded-lg font-medium hover:bg-gray-100 transition-colors whitespace-nowrap"
            >
              <Phone className="size-[1em] mr-2" />
              +7 (800) 123-45-67
            </a>
            <a
              href="mailto:returns@svetofor-mebel.ru"
              className="bg-white text-red-600 px-6 py-3 rounded-lg font-medium hover:bg-gray-100 transition-colors whitespace-nowrap"
            >
              <Mail className="size-[1em] mr-2" />
              returns@svetofor-mebel.ru
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}
