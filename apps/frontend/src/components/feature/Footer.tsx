import { Link } from 'react-router-dom';
import { organizationRequisites } from '../../data/organizationRequisites';
import FooterRequisites from './FooterRequisites';
import { VkIcon } from '../ui/icons/brands';

export default function Footer() {
  return (
    <footer className="bg-gray-900 text-white">
      <div className="max-w-7xl mx-auto px-4 py-8 md:py-12">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8 mb-8 md:mb-12">
          {/* Company */}
          <div>
            <h3 className="font-semibold text-base md:text-lg mb-3 md:mb-4">Компания</h3>
            <ul className="space-y-2 text-sm md:text-base">
              <li>
                <Link to="/about" className="text-gray-400 hover:text-white transition-colors">
                  О нас
                </Link>
              </li>
              <li>
                <Link to="/stores" className="text-gray-400 hover:text-white transition-colors">
                  Магазины
                </Link>
              </li>
              <li>
                <Link to="/careers" className="text-gray-400 hover:text-white transition-colors">
                  Вакансии
                </Link>
              </li>
              <li>
                <Link to="/documents" className="text-gray-400 hover:text-white transition-colors">
                  Документы
                </Link>
              </li>
            </ul>
          </div>

          {/* Catalog */}
          <div>
            <h3 className="font-semibold text-base md:text-lg mb-3 md:mb-4">Каталог</h3>
            <ul className="space-y-2 text-sm md:text-base">
              <li>
                <Link
                  to="/catalog/divany-i-kresla"
                  className="text-gray-400 hover:text-white transition-colors"
                >
                  Диваны
                </Link>
              </li>
              <li>
                <Link
                  to="/catalog/spalni"
                  className="text-gray-400 hover:text-white transition-colors"
                >
                  Спальни
                </Link>
              </li>
              <li>
                <Link
                  to="/catalog/kuhni"
                  className="text-gray-400 hover:text-white transition-colors"
                >
                  Кухни
                </Link>
              </li>
              <li>
                <Link
                  to="/catalog/gostinye"
                  className="text-gray-400 hover:text-white transition-colors"
                >
                  Гостиные
                </Link>
              </li>
            </ul>
          </div>

          {/* Services */}
          <div>
            <h3 className="font-semibold text-base md:text-lg mb-3 md:mb-4">Услуги</h3>
            <ul className="space-y-2 text-sm md:text-base">
              <li>
                <Link to="/delivery" className="text-gray-400 hover:text-white transition-colors">
                  Доставка
                </Link>
              </li>
              <li>
                <Link to="/returns" className="text-gray-400 hover:text-white transition-colors">
                  Возврат
                </Link>
              </li>
              <li>
                <Link to="/rental" className="text-gray-400 hover:text-white transition-colors">
                  Аренда
                </Link>
              </li>
            </ul>
          </div>

          {/* Contacts */}
          <div>
            <h3 className="font-semibold text-base md:text-lg mb-3 md:mb-4">Контакты</h3>
            <ul className="space-y-2 text-sm md:text-base">
              <li>
                <a
                  href={organizationRequisites.phoneHref}
                  className="text-gray-400 hover:text-white transition-colors"
                >
                  {organizationRequisites.phoneDisplay}
                </a>
              </li>
              <li className="text-gray-400">{organizationRequisites.legalAddress}</li>
            </ul>
          </div>
        </div>

        {/* Social Media */}
        <div className="flex flex-wrap items-center justify-center gap-3 md:gap-4 mb-6 md:mb-8 pb-6 md:pb-8 border-b border-gray-800">
          {/* Логотип из макета — это плашка: круг закрашен currentColor,
              знак прорезан насквозь. Своя подложка ему не нужна, иначе
              получается кружок в кружке. */}
          <a
            href="#"
            aria-label="ВКонтакте"
            className="w-10 h-10 text-gray-400 hover:text-red-600 transition-colors"
          >
            <VkIcon className="w-full h-full" />
          </a>
          {/* Telegram убран: логотипа нет ни в Lucide, ни в макете.
              Вернуть вместе с SVG.
          <a
            href="#"
            aria-label="Telegram"
            className="w-10 h-10 text-gray-400 hover:text-red-600 transition-colors"
          >
            <TelegramIcon className="w-full h-full" />
          </a>
          */}
        </div>

        <FooterRequisites />

        {/* Copyright & legal links */}
        <div className="flex mt-2 flex-col md:flex-row items-center justify-center md:justify-between gap-3 text-xs md:text-sm text-gray-400">
          <p className="text-center md:text-left">
            © {new Date().getFullYear()} {organizationRequisites.fullName}. Все права защищены.
          </p>
          <nav
            className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2"
            aria-label="Правовая информация"
          >
            <Link to="/privacy" className="hover:text-white transition-colors whitespace-nowrap">
              Политика конфиденциальности
            </Link>
            <span className="hidden sm:inline text-gray-600" aria-hidden>
              |
            </span>
            <Link to="/oferta" className="hover:text-white transition-colors whitespace-nowrap">
              Публичная оферта
            </Link>
            <span className="hidden sm:inline text-gray-600" aria-hidden>
              |
            </span>
            <Link to="/cookies" className="hover:text-white transition-colors whitespace-nowrap">
              Cookie-файлы
            </Link>
          </nav>
        </div>
      </div>
    </footer>
  );
}
