import { useMemo } from 'react';
import LegalPageTemplate from '../../components/legal/LegalPageTemplate';
import { organizationRequisites } from '../../data/organizationRequisites';
import { usePageSeo } from '../../hooks/usePageSeo';
import type { SeoMeta } from '../../lib/api';

export default function CookiesPage() {
  const seo: SeoMeta = useMemo(
    () => ({
      title: 'Cookie-файлы',
      description: 'Информация об использовании cookie-файлов на сайте и целях их обработки.',
      image: null,
      canonical_url: null,
      robots: 'index, follow',
      open_graph_title: 'Cookie-файлы',
      locale: 'ru_RU',
    }),
    [],
  );
  usePageSeo(seo);

  const r = organizationRequisites;

  return (
    <LegalPageTemplate
      title="Cookie-файлы"
      heroSubtitle="Как и зачем мы используем cookie"
      breadcrumbLabel="Cookie-файлы"
    >
      <h2>1. Что такое cookie</h2>
      <p>
        Cookie — это небольшие файлы, которые сохраняются в браузере на вашем устройстве. Они
        помогают распознавать пользователя, сохранять настройки и обеспечивать работу отдельных
        функций сайта.
      </p>

      <h2>2. Какие cookie мы используем</h2>
      <ul>
        <li>
          <strong>Технические (обязательные)</strong> — необходимы для корректной работы сайта
          (например, сохранение выбора региона или содержимого корзины).
        </li>
        <li>
          <strong>Функциональные</strong> — запоминают ваши предпочтения и настройки интерфейса.
        </li>
        <li>
          <strong>Аналитические</strong> — помогают понять, как пользователи взаимодействуют с
          сайтом, чтобы улучшать сервис (если подключены системы аналитики).
        </li>
      </ul>

      <h2>3. Для чего используются cookie</h2>
      <ul>
        <li>обеспечение работы сайта и его функций;</li>
        <li>персонализация интерфейса;</li>
        <li>статистика и улучшение качества сервиса;</li>
        <li>защита и предотвращение мошеннических действий.</li>
      </ul>

      <h2>4. Как управлять cookie</h2>
      <p>
        Вы можете ограничить или отключить использование cookie в настройках браузера. Обратите
        внимание: отключение обязательных cookie может привести к некорректной работе сайта.
      </p>

      <h2>5. Контакты</h2>
      <p>
        По вопросам, связанным с обработкой данных и использованием cookie, вы можете связаться с
        нами:
        <br />
        {r.fullName}
        <br />
        Юридический адрес: {r.legalAddress}
        <br />
        Тел.:{' '}
        <a className="text-red-600 hover:underline" href={r.phoneHref}>
          {r.phoneDisplay}
        </a>
      </p>
    </LegalPageTemplate>
  );
}
