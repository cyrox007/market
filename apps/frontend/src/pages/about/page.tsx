import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { usePageSeo } from '../../hooks/usePageSeo';
import { api, type AboutPage } from '../../lib/api';
import Icon from '../../components/ui/icons/Icon';
import { ChevronRight, Star, User } from 'lucide-react';

export default function About() {
  const [aboutData, setAboutData] = useState<AboutPage | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  usePageSeo(aboutData?.seo ?? null);

  useEffect(() => {
    const loadData = async () => {
      try {
        setIsLoading(true);
        setError(null);
        const response = await api.about.get();
        setAboutData(response.about);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Ошибка загрузки данных');
        console.error('Failed to load about page:', err);
      } finally {
        setIsLoading(false);
      }
    };

    loadData();
  }, []);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-12">
          <div className="flex items-center justify-center py-16">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600"></div>
          </div>
        </div>
      </div>
    );
  }

  if (error || !aboutData) {
    return (
      <div className="min-h-screen bg-white">
        <div className="max-w-7xl mx-auto px-4 py-12">
          <div className="text-center py-16">
            <p className="text-red-600 mb-4">{error || 'Данные не найдены'}</p>
            <button
              onClick={() => window.location.reload()}
              className="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700"
            >
              Обновить страницу
            </button>
          </div>
        </div>
      </div>
    );
  }

  const statistics = aboutData.statistics || {};
  const formatNumber = (num?: number) => {
    if (!num) return '0';
    if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M+`;
    if (num >= 1000) return `${(num / 1000).toFixed(0)}K+`;
    return `${num}+`;
  };

  return (
    <div className="min-h-screen bg-white">

      {/* Hero */}
      <div className="relative h-[500px] overflow-hidden">
        {aboutData.hero_image ? (
          <img
            src={aboutData.hero_image_fullhd || aboutData.hero_image_hd || aboutData.hero_image}
            alt={aboutData.hero_title || 'О нас'}
            className="w-full h-full object-cover object-top"
          />
        ) : (
          <div className="w-full h-full bg-gradient-to-r from-red-600 to-yellow-500"></div>
        )}
        <div className="absolute inset-0 bg-gradient-to-r from-red-600/90 to-yellow-500/90 flex items-center">
          <div className="max-w-7xl mx-auto px-4 text-white">
            <h1 className="text-5xl font-bold mb-4">{aboutData.hero_title || 'О компании Светофор Мебели'}</h1>
            {aboutData.hero_description && (
              <p className="text-xl opacity-90 max-w-2xl">
                {aboutData.hero_description}
              </p>
            )}
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 py-16">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-xs sm:text-sm mb-6">
          <Link to="/" className="text-gray-600 hover:text-red-600">Главная</Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">О нас</span>
        </div>

        {/* Our Story */}
        {aboutData.story_title && (
          <div className="mb-16">
            <h2 className="text-3xl font-bold mb-8">{aboutData.story_title}</h2>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
              {aboutData.story_content && (
                <div>
                  {aboutData.story_content.split('\n\n').map((paragraph, idx) => (
                    <p key={idx} className="text-gray-700 text-lg mb-4">
                      {paragraph}
                    </p>
                  ))}
                </div>
              )}
              {aboutData.story_images && aboutData.story_images.length > 0 && (
                <div className="grid grid-cols-2 gap-4">
                  {aboutData.story_images.slice(0, 2).map((img, idx) => (
                    <img
                      key={idx}
                      src={img.fullhd || img.hd || img.url}
                      alt={`История ${idx + 1}`}
                      className={`w-full h-64 object-cover object-top rounded-2xl ${idx === 1 ? 'mt-8' : ''}`}
                    />
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Statistics */}
        {(statistics.years || statistics.stores || statistics.clients || statistics.products) && (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mb-16">
            {statistics.years && (
              <div className="bg-red-50 rounded-2xl p-8 text-center">
                <div className="text-4xl font-bold text-red-600 mb-2">{formatNumber(statistics.years)}</div>
                <p className="text-gray-700">лет на рынке</p>
              </div>
            )}
            {statistics.stores && (
              <div className="bg-yellow-50 rounded-2xl p-8 text-center">
                <div className="text-4xl font-bold text-yellow-600 mb-2">{formatNumber(statistics.stores)}</div>
                <p className="text-gray-700">магазинов</p>
              </div>
            )}
            {statistics.clients && (
              <div className="bg-green-50 rounded-2xl p-8 text-center">
                <div className="text-4xl font-bold text-green-600 mb-2">{formatNumber(statistics.clients)}</div>
                <p className="text-gray-700">довольных клиентов</p>
              </div>
            )}
            {statistics.products && (
              <div className="bg-red-50 rounded-2xl p-8 text-center">
                <div className="text-4xl font-bold text-red-600 mb-2">{formatNumber(statistics.products)}</div>
                <p className="text-gray-700">товаров в каталоге</p>
              </div>
            )}
          </div>
        )}

        {/* Our Values / Advantages */}
        {aboutData.advantages && aboutData.advantages.length > 0 && (
          <div className="mb-16">
            <h2 className="text-3xl font-bold mb-8 text-center">Наши ценности</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
              {aboutData.advantages.map((advantage) => {
                const colorClass = advantage.color || 'red';
                const getColorClasses = (color: string) => {
                  const colorMap: Record<string, { border: string; bg: string; text: string }> = {
                    red: { border: 'border-red-600', bg: 'bg-red-600', text: 'text-red-600' },
                    yellow: { border: 'border-yellow-600', bg: 'bg-yellow-600', text: 'text-yellow-600' },
                    green: { border: 'border-green-600', bg: 'bg-green-600', text: 'text-green-600' },
                    blue: { border: 'border-blue-600', bg: 'bg-blue-600', text: 'text-blue-600' },
                  };
                  return colorMap[color] || colorMap.red;
                };
                const colors = getColorClasses(colorClass);
                return (
                  <div key={advantage.id} className={`bg-white border-2 ${colors.border} rounded-2xl p-8`}>
                    <div className={`w-16 h-16 ${colors.bg} rounded-full flex items-center justify-center mb-6`}>
                      {advantage.icon ? (
                        <Icon name={advantage.icon} className="size-[1em] text-3xl text-white" />
                      ) : (
                        <Star className="size-[1em] text-3xl text-white" />
                      )}
                    </div>
                    <h3 className={`text-2xl font-bold mb-4 ${colors.text}`}>{advantage.title}</h3>
                    {advantage.description && (
                      <p className="text-gray-700">{advantage.description}</p>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Our Team */}
        {aboutData.team_members && aboutData.team_members.length > 0 && (
          <div className="mb-16">
            <h2 className="text-3xl font-bold mb-8 text-center">Наша команда</h2>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
              {aboutData.team_members.map((member) => (
                <div key={member.id} className="text-center">
                  <div className="w-48 h-48 mx-auto mb-4 rounded-full overflow-hidden bg-gray-100">
                    {member.image ? (
                      <img
                        src={member.image_fullhd || member.image_hd || member.image}
                        alt={member.name}
                        className="w-full h-full object-cover object-top"
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center text-gray-400">
                        <User className="size-[1em] text-6xl" />
                      </div>
                    )}
                  </div>
                  <h3 className="font-bold text-lg mb-1">{member.name}</h3>
                  <p className="text-gray-600">{member.position}</p>
                </div>
              ))}
            </div>
          </div>
        )}


        {/* CTA */}
        <div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white rounded-2xl p-12 text-center">
          <h2 className="text-3xl font-bold mb-4">Станьте частью нашей истории</h2>
          <p className="text-xl mb-8 opacity-90">
            Посетите наши салоны и убедитесь в качестве нашей мебели
          </p>
          <div className="flex flex-wrap justify-center gap-4">
            <Link to="/stores" className="bg-white text-red-600 px-8 py-4 rounded-lg font-medium text-lg hover:bg-gray-100 transition-colors whitespace-nowrap">
              Наши магазины
            </Link>
            <Link to="/catalog" className="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-medium text-lg hover:bg-white/10 transition-colors whitespace-nowrap">
              Каталог товаров
            </Link>
          </div>
        </div>
      </div>

    </div>
  );
}
