import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { ChevronRight } from 'lucide-react';

export default function Login() {
	const navigate = useNavigate();
	const { login, isAuthenticated } = useAuth();
	const [email, setEmail] = useState('');
	const [password, setPassword] = useState('');
	const [error, setError] = useState<string | null>(null);
	const [isLoading, setIsLoading] = useState(false);

	// Редирект, если уже авторизован
	useEffect(() => {
		if (isAuthenticated) {
			navigate('/account');
		}
	}, [isAuthenticated, navigate]);

	usePageSeo({
		title: buildTitle('Вход'),
		description: 'Вход в личный кабинет интернет-магазина Светофор-Мебель.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'noindex, follow',
		open_graph_title: buildTitle('Вход'),
		locale: 'ru_RU',
	});

	const handleSubmit = async (e: React.FormEvent) => {
		e.preventDefault();
		setError(null);
		setIsLoading(true);

		try {
			await login(email, password);
			navigate('/account');
		} catch (err: any) {
			setError(err?.data?.message || err?.message || 'Ошибка входа. Проверьте email и пароль.');
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
					<span className="text-gray-900">Вход</span>
				</div>

				<div className="bg-white border border-gray-200 rounded-2xl p-8">
					<h1 className="text-3xl font-bold mb-6">Вход в личный кабинет</h1>

					{error && (
						<div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
							<p className="text-red-600 text-sm">{error}</p>
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
								Пароль *
							</label>
							<input
								id="password"
								type="password"
								value={password}
								onChange={(e) => setPassword(e.target.value)}
								required
								className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
								placeholder="Введите пароль"
							/>
						</div>

						<button
							type="submit"
							disabled={isLoading}
							className="w-full bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
						>
							{isLoading ? 'Вход...' : 'Войти'}
						</button>
					</form>

					<div className="mt-6 text-center space-y-2">
						<p className="text-sm text-gray-600">
							Нет аккаунта?{' '}
							<Link to="/register" className="text-red-600 hover:text-red-700 font-medium">
								Зарегистрироваться
							</Link>
						</p>
						<p className="text-sm">
							<Link to="/forgot-password" className="text-gray-600 hover:text-red-600">
								Забыли пароль?
							</Link>
						</p>
					</div>
				</div>
			</div>
		</div>
	);
}
