import { type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

type LegalPageTemplateProps = {
  title: string;
  heroSubtitle?: string;
  breadcrumbLabel: string;
  children: ReactNode;
};

export default function LegalPageTemplate({
  title,
  heroSubtitle,
  breadcrumbLabel,
  children,
}: LegalPageTemplateProps) {
  return (
    <div className="min-h-screen bg-white">
      <div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-14 md:py-20">
        <div className="max-w-7xl mx-auto px-4 text-center">
          <h1 className="text-3xl md:text-5xl font-bold mb-3">{title}</h1>
          {heroSubtitle ? <p className="text-base md:text-xl opacity-90 max-w-2xl mx-auto">{heroSubtitle}</p> : null}
        </div>
      </div>

      <div className="max-w-3xl mx-auto px-4 py-10 md:py-14">
        <nav className="flex items-center gap-2 text-xs sm:text-sm text-gray-600 mb-8" aria-label="Хлебные крошки">
          <Link to="/" className="hover:text-red-600 transition-colors">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400 shrink-0" />
          <span className="text-gray-900">{breadcrumbLabel}</span>
        </nav>

        <article className="text-sm md:text-base text-gray-700 leading-relaxed space-y-4 [&_h2]:text-lg [&_h2]:md:text-xl [&_h2]:font-bold [&_h2]:text-gray-900 [&_h2]:mt-8 [&_h2]:mb-2 [&_h2:first-child]:mt-0 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-2">
          {children}
        </article>
      </div>
    </div>
  );
}
