import { FormEvent, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../lib/api';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';

export default function ForgotPasswordPage() {
	const [email, setEmail] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [success, setSuccess] = useState<string | null>(null);

	usePageSeo({
		title: buildTitle('Восстановление пароля'),
		description: 'Восстановление пароля в интернет-магазине Светофор-Мебель.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'noindex, follow',
		open_graph_title: buildTitle('Восстановление пароля'),
		locale: 'ru_RU',
	});

	const handleSubmit = async (e: FormEvent) => {
		e.preventDefault();
		setError(null);
		setSuccess(null);
		setIsLoading(true);

		try {
			await api.auth.forgotPassword(email);
			setSuccess('Мы отправили ссылку для восстановления пароля на вашу почту.');
		} catch (err: any) {
			const message =
				err?.data?.message ||
				err?.message ||
				'Не удалось отправить письмо для восстановления пароля. Попробуйте позже.';
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
					<i className="ri-arrow-right-s-line text-gray-400"></i>
					<Link to="/login" className="text-gray-600 hover:text-red-600">
						Вход
					</Link>
					<i className="ri-arrow-right-s-line text-gray-400"></i>
					<span className="text-gray-900">Восстановление пароля</span>
				</div>

				<div className="bg-white border border-gray-200 rounded-2xl p-8">
					<h1 className="text-3xl font-bold mb-2">Восстановление пароля</h1>
					<p className="text-sm text-gray-600 mb-6">
						Укажите email, который вы использовали при регистрации. Мы отправим на него ссылку
						для восстановления пароля.
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

						<button
							type="submit"
							disabled={isLoading}
							className="w-full bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
						>
							{isLoading ? 'Отправка...' : 'Отправить ссылку'}
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

