import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import PhoneInput from '../../components/ui/PhoneInput';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import { ChevronRight } from 'lucide-react';

export default function Register() {
	const navigate = useNavigate();
	const { register, isAuthenticated } = useAuth();
	const [formData, setFormData] = useState({
		name: '',
		email: '',
		password: '',
		password_confirmation: '',
		phone: '',
	});
	const [error, setError] = useState<string | null>(null);
	const [isLoading, setIsLoading] = useState(false);

	// Редирект, если уже авторизован
	useEffect(() => {
		if (isAuthenticated) {
			navigate('/account');
		}
	}, [isAuthenticated, navigate]);

	usePageSeo({
		title: buildTitle('Регистрация'),
		description: 'Регистрация в интернет-магазине Светофор-Мебель.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'noindex, follow',
		open_graph_title: buildTitle('Регистрация'),
		locale: 'ru_RU',
	});

	const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
		setFormData({
			...formData,
			[e.target.name]: e.target.value,
		});
	};

	const handleSubmit = async (e: React.FormEvent) => {
		e.preventDefault();
		setError(null);

		// Валидация на клиенте
		if (formData.password !== formData.password_confirmation) {
			setError('Пароли не совпадают');
			return;
		}

		if (formData.password.length < 8) {
			setError('Пароль должен содержать минимум 8 символов');
			return;
		}

		setIsLoading(true);

		try {
			const registerData: any = {
				name: formData.name,
				email: formData.email,
				password: formData.password,
				password_confirmation: formData.password_confirmation,
			};

			if (formData.phone) {
				registerData.phone = formData.phone;
			}

			await register(registerData);
			navigate('/account');
		} catch (err: any) {
			const errorMessage = err?.data?.message || err?.data?.errors
				? Object.values(err.data.errors || {}).flat().join(', ')
				: err?.message || 'Ошибка регистрации. Попробуйте еще раз.';
			setError(errorMessage);
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
					<span className="text-gray-900">Регистрация</span>
				</div>

				<div className="bg-white border border-gray-200 rounded-2xl p-8">
					<h1 className="text-3xl font-bold mb-6">Регистрация</h1>

					{error && (
						<div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
							<p className="text-red-600 text-sm">{error}</p>
						</div>
					)}

					<form onSubmit={handleSubmit} className="space-y-6">
						<div>
							<label htmlFor="name" className="block text-sm font-medium mb-2">
								Имя *
							</label>
							<input
								id="name"
								name="name"
								type="text"
								value={formData.name}
								onChange={handleChange}
								required
								className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
								placeholder="Ваше имя"
							/>
						</div>

						<div>
							<label htmlFor="email" className="block text-sm font-medium mb-2">
								Email *
							</label>
							<input
								id="email"
								name="email"
								type="email"
								value={formData.email}
								onChange={handleChange}
								required
								className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
								placeholder="your@email.com"
							/>
						</div>

						<div>
							<label htmlFor="phone" className="block text-sm font-medium mb-2">
								Телефон
							</label>
							<PhoneInput
								id="phone"
								name="phone"
								type="tel"
								value={formData.phone}
								onChange={handleChange}
								className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
								placeholder="+7 (999) 123-45-67"
							/>
						</div>

						<div>
							<label htmlFor="password" className="block text-sm font-medium mb-2">
								Пароль *
							</label>
							<input
								id="password"
								name="password"
								type="password"
								value={formData.password}
								onChange={handleChange}
								required
								minLength={8}
								className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
								placeholder="Минимум 8 символов"
							/>
						</div>

						<div>
							<label htmlFor="password_confirmation" className="block text-sm font-medium mb-2">
								Подтвердите пароль *
							</label>
							<input
								id="password_confirmation"
								name="password_confirmation"
								type="password"
								value={formData.password_confirmation}
								onChange={handleChange}
								required
								minLength={8}
								className="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:border-red-600 focus:outline-none"
								placeholder="Повторите пароль"
							/>
						</div>

						<button
							type="submit"
							disabled={isLoading}
							className="w-full bg-red-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-red-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
						>
							{isLoading ? 'Регистрация...' : 'Зарегистрироваться'}
						</button>
					</form>

					<div className="mt-6 text-center">
						<p className="text-sm text-gray-600">
							Уже есть аккаунт?{' '}
							<Link to="/login" className="text-red-600 hover:text-red-700 font-medium">
								Войти
							</Link>
						</p>
					</div>
				</div>
			</div>

		</div>
	);
}
