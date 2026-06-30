
export default function WhyChooseUs() {
  const features = [
    {
      icon: 'ri-truck-line',
      title: 'Бесплатная доставка',
      description: 'Доставляем по всей Украине при заказе от 10 000 ₴',
      color: 'bg-red-50 text-red-600'
    },
    {
      icon: 'ri-shield-check-line',
      title: 'Гарантия качества',
      description: 'Официальная гарантия на всю мебель до 5 лет',
      color: 'bg-yellow-50 text-yellow-600'
    },
    {
      icon: 'ri-customer-service-2-line',
      title: 'Поддержка 24/7',
      description: 'Наши специалисты всегда готовы помочь с выбором',
      color: 'bg-green-50 text-green-600'
    },
    {
      icon: 'ri-exchange-line',
      title: 'Легкий возврат',
      description: 'Возврат и обмен товара в течение 14 дней',
      color: 'bg-red-50 text-red-600'
    }
  ];

  return (
    <section className="px-6 lg:px-12 py-16">
      <div className="max-w-7xl mx-auto">
        <div className="text-center mb-12">
          <h2 className="text-3xl font-bold text-gray-900 mb-4">
            Почему выбирают нас
          </h2>
          <p className="text-lg text-gray-600 max-w-2xl mx-auto">
            Мы предлагаем лучшие условия для покупки качественной мебели
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
          {features.map((feature, index) => (
            <div
              key={index}
              className="text-center"
            >
              <div className={`w-16 h-16 flex items-center justify-center ${feature.color} rounded-2xl mx-auto mb-4`}>
                <i className={`${feature.icon} text-3xl`}></i>
              </div>
              <h3 className="text-xl font-bold text-gray-900 mb-2">
                {feature.title}
              </h3>
              <p className="text-gray-600">
                {feature.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
