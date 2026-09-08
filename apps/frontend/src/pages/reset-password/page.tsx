import { FormEvent, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { api } from '../../lib/api';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { ChevronRight } from 'lucide-react';

function useQuery() {
  return new URLSearchParams(useLocation().search);
}

export default function ResetPasswordPage() {
  const navigate = useNavigate();
  const query = useQuery();

  const token = query.get('token') || '';
  const emailFromQuery = query.get('email') || '';

  const [email, setEmail] = useState(emailFromQuery);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  usePageSeo({
    title: buildTitle('Сброс пароля'),
    description: 'Сброс пароля в интернет-магазине Светофор-Мебель.',
    image: '/logo.png',
    robots: 'noindex, follow',
    open_graph_title: buildTitle('Сброс пароля'),
    locale: 'ru_RU',
  });

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    if (!token) {
      setError('Ссылка для восстановления недействительна или устарела.');
      return;
    }

    setIsLoading(true);

    try {
      await api.auth.resetPassword({
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      });

      setSuccess('Пароль успешно изменен. Сейчас вы будете перенаправлены на страницу входа.');

      setTimeout(() => {
        navigate('/login');
      }, 2000);
    } catch (err: any) {
      const message =
        err?.data?.message ||
        err?.message ||
        'Не удалось изменить пароль. Проверьте данные и попробуйте ещё раз.';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-md mx-auto px-4 py-12">
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-sm mb-6">
          <Link to="/" className="text-gray-600 hover:text-red-600">
            Главная
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <Link to="/login" className="text-gray-600 hover:text-red-600">
            Вход
          </Link>
          <ChevronRight className="size-[1em] text-gray-400" />
          <span className="text-gray-900">Новый пароль</span>
        </div>

        <div className="bg-white border border-gray-200 rounded-2xl p-8">
          <h1 className="text-3xl font-bold mb-2">Установка нового пароля</h1>
          <p className="text-sm text-gray-600 mb-6">
            Введите новый пароль для вашей учетной записи. Постарайтесь сделать его надежным.
          </p>

          {error && (
            <div className="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
              <p className="text-red-600 text-sm">{error}</p>
            </div>
          )}

          {success && (
            <div className="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
              <p className="text-green-700 text-sm">{success}</p>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label htmlFor="email" className="block text-sm font-medium mb-2">
                Email *
              </label>
              <input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
                placeholder="your@email.com"
              />
            </div>

            <div>
              <label htmlFor="password" className="block text-sm font-medium mb-2">
                Новый пароль *
              </label>
              <input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
                placeholder="Введите новый пароль"
              />
            </div>

            <div>
              <label htmlFor="password_confirmation" className="block text-sm font-medium mb-2">
                Подтверждение пароля *
              </label>
              <input
                id="password_confirmation"
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                required
                className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
                placeholder="Повторите новый пароль"
              />
            </div>

            <button
              type="submit"
              disabled={isLoading}
              className="w-full bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
            >
              {isLoading ? 'Сохранение...' : 'Сохранить новый пароль'}
            </button>
          </form>

          <div className="mt-6 text-center">
            <Link to="/login" className="text-sm text-gray-600 hover:text-red-600">
              Вернуться к входу
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
