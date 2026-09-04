import { Link } from 'react-router-dom';
import { usePageSeo } from '../../hooks/usePageSeo';
import { buildTitle } from '../../constants/seo';
import Icon from '../../components/ui/icons/Icon';
import { Award, BookOpen, CircleHelp, Download, Eye, FileArchive, FileChartColumn, FileSpreadsheet, FileText, Info, Mail, Phone, ShieldCheck, Wrench } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Классы Tailwind нельзя собирать в рантайме: сборщик сканирует исходники
 * статически и выражение вида `bg-${color}-100` не видит, а safelist в конфиге
 * нет. Из-за этого плашки документов на проде оставались серыми. Явный словарь
 * даёт сборщику увидеть все нужные классы.
 */
const DOC_COLORS: Record<string, { bg: string; text: string }> = {
	red: { bg: 'bg-red-100', text: 'text-red-600' },
	yellow: { bg: 'bg-yellow-100', text: 'text-yellow-600' },
	green: { bg: 'bg-green-100', text: 'text-green-600' },
};

const docColors = (color: string) => DOC_COLORS[color] ?? DOC_COLORS.red;

/** Иконка по расширению файла. */
const FILE_ICONS: Record<string, LucideIcon> = {
	pdf: FileText,
	doc: FileText,
	docx: FileText,
	xls: FileSpreadsheet,
	xlsx: FileSpreadsheet,
	zip: FileArchive,
	rar: FileArchive,
};


const documents = [
	{
		category: 'Юридические документы',
		icon: FileText,
		color: 'red',
		files: [
			{ name: 'Политика конфиденциальности', size: '245 КБ', format: 'PDF' },
			{ name: 'Пользовательское соглашение', size: '312 КБ', format: 'PDF' },
			{ name: 'Договор оферты', size: '428 КБ', format: 'PDF' },
			{ name: 'Реквизиты компании', size: '156 КБ', format: 'PDF' }
		]
	},
	{
		category: 'Сертификаты и лицензии',
		icon: Award,
		color: 'yellow',
		files: [
			{ name: 'Сертификат соответствия ГОСТ', size: '1.2 МБ', format: 'PDF' },
			{ name: 'Сертификат качества ISO 9001', size: '890 КБ', format: 'PDF' },
			{ name: 'Экологический сертификат', size: '756 КБ', format: 'PDF' },
			{ name: 'Пожарный сертификат', size: '634 КБ', format: 'PDF' }
		]
	},
	{
		category: 'Гарантийные документы',
		icon: ShieldCheck,
		color: 'green',
		files: [
			{ name: 'Гарантийный талон', size: '189 КБ', format: 'PDF' },
			{ name: 'Условия гарантии', size: '267 КБ', format: 'PDF' },
			{ name: 'Инструкция по эксплуатации', size: '1.5 МБ', format: 'PDF' },
			{ name: 'Правила ухода за мебелью', size: '423 КБ', format: 'PDF' }
		]
	},
	{
		category: 'Каталоги и прайс-листы',
		icon: BookOpen,
		color: 'red',
		files: [
			{ name: 'Каталог мебели 2024', size: '15.3 МБ', format: 'PDF' },
			{ name: 'Прайс-лист', size: '567 КБ', format: 'XLSX' },
			{ name: 'Акции и скидки', size: '2.1 МБ', format: 'PDF' },
			{ name: 'Новинки сезона', size: '8.7 МБ', format: 'PDF' }
		]
	},
	{
		category: 'Инструкции по сборке',
		icon: Wrench,
		color: 'yellow',
		files: [
			{ name: 'Инструкция сборки диванов', size: '3.4 МБ', format: 'PDF' },
			{ name: 'Инструкция сборки шкафов', size: '4.2 МБ', format: 'PDF' },
			{ name: 'Инструкция сборки кроватей', size: '2.8 МБ', format: 'PDF' },
			{ name: 'Инструкция сборки столов', size: '1.9 МБ', format: 'PDF' }
		]
	},
	{
		category: 'Финансовые документы',
		icon: FileChartColumn,
		color: 'green',
		files: [
			{ name: 'Образец договора купли-продажи', size: '345 КБ', format: 'PDF' },
			{ name: 'Образец акта приема-передачи', size: '234 КБ', format: 'PDF' },
			{ name: 'Условия рассрочки', size: '289 КБ', format: 'PDF' },
			{ name: 'Условия кредитования', size: '412 КБ', format: 'PDF' }
		]
	}
];

export default function Documents() {
	usePageSeo({
		title: buildTitle('Документы'),
		description: 'Документы интернет-магазина Светофор-Мебель.',
		image: '/logo.png',
		canonical_url: window.location.href,
		robots: 'index, follow',
		open_graph_title: buildTitle('Документы'),
		locale: 'ru_RU',
	});
	return (
		<div className="min-h-screen bg-white">

			{/* Hero */}
			<div className="bg-gradient-to-r from-red-600 to-yellow-500 text-white py-20">
				<div className="max-w-7xl mx-auto px-4 text-center">
					<h1 className="text-5xl font-bold mb-4">Документы</h1>
					<p className="text-xl opacity-90">Вся необходимая документация в одном месте</p>
				</div>
			</div>

			<div className="max-w-7xl mx-auto px-4 py-16">
				{/* Info */}
				<div className="bg-blue-50 border-2 border-blue-600 rounded-2xl p-8 mb-12">
					<div className="flex items-start gap-4">
						<div className="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
							<Info className="size-[1em] text-3xl text-white" />
						</div>
						<div>
							<h2 className="text-2xl font-bold mb-3">Важная информация</h2>
							<p className="text-gray-700 text-lg mb-2">
								Все документы представлены в актуальной версии и соответствуют действующему законодательству РФ.
							</p>
							<p className="text-gray-700 text-lg">
								Для просмотра PDF-файлов необходим Adobe Acrobat Reader или аналогичная программа.
							</p>
						</div>
					</div>
				</div>

				{/* Documents Grid */}
				<div className="space-y-8">
					{documents.map((category, idx) => (
						<div key={idx} className="bg-white border border-gray-200 rounded-2xl overflow-hidden">
							<div className={`bg-${category.color}-50 p-6 border-b border-gray-200`}>
								<div className="flex items-center gap-4">
									<div className={`w-14 h-14 bg-${category.color}-600 rounded-full flex items-center justify-center`}>
										<Icon name={category.icon} className="size-[1em] text-2xl text-white" />
									</div>
									<h2 className="text-2xl font-bold">{category.category}</h2>
								</div>
							</div>
							<div className="p-6">
								<div className="grid grid-cols-1 md:grid-cols-2 gap-4">
									{category.files.map((file, fileIdx) => (
										<div
											key={fileIdx}
											className="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
										>
											<div className="flex items-center gap-4 flex-1">
												<div className={`w-12 h-12 ${docColors(category.color).bg} rounded-lg flex items-center justify-center flex-shrink-0`}>
													<Icon
															name={FILE_ICONS[file.format.toLowerCase()] ?? FileText}
															className={`size-[1em] text-2xl ${docColors(category.color).text}`}
														/>
												</div>
												<div className="flex-1 min-w-0">
													<h3 className="font-semibold mb-1 truncate">{file.name}</h3>
													<div className="flex items-center gap-3 text-sm text-gray-500">
														<span>{file.format}</span>
														<span>•</span>
														<span>{file.size}</span>
													</div>
												</div>
											</div>
											<div className="flex gap-2 ml-4">
												<button className="w-10 h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 hover:bg-red-50 cursor-pointer">
													<Eye className="size-[1em] text-xl text-red-600" />
												</button>
												<button className="w-10 h-10 flex items-center justify-center border border-gray-300 rounded-lg hover:border-red-600 hover:bg-red-50 cursor-pointer">
													<Download className="size-[1em] text-xl text-red-600" />
												</button>
											</div>
										</div>
									))}
								</div>
							</div>
						</div>
					))}
				</div>

				{/* Help Section */}
				<div className="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8">
					<div className="bg-red-50 rounded-2xl p-8 text-center">
						<div className="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
							<CircleHelp className="size-[1em] text-3xl text-white" />
						</div>
						<h3 className="text-xl font-bold mb-2">Нужна помощь?</h3>
						<p className="text-gray-600 mb-4">Не нашли нужный документ?</p>
						<Link to="/contacts" className="text-red-600 font-medium hover:underline">
							Свяжитесь с нами
						</Link>
					</div>
					<div className="bg-yellow-50 rounded-2xl p-8 text-center">
						<div className="w-16 h-16 bg-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4">
							<Mail className="size-[1em] text-3xl text-white" />
						</div>
						<h3 className="text-xl font-bold mb-2">Email</h3>
						<p className="text-gray-600 mb-4">Отправьте запрос на почту</p>
						<a href="mailto:docs@svetofor-mebel.ru" className="text-yellow-600 font-medium hover:underline">
							docs@svetofor-mebel.ru
						</a>
					</div>
					<div className="bg-green-50 rounded-2xl p-8 text-center">
						<div className="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
							<Phone className="size-[1em] text-3xl text-white" />
						</div>
						<h3 className="text-xl font-bold mb-2">Телефон</h3>
						<p className="text-gray-600 mb-4">Позвоните нам</p>
						<a href="tel:+78001234567" className="text-green-600 font-medium hover:underline">
							+7 (800) 123-45-67
						</a>
					</div>
				</div>
			</div>

		</div>
	);
}
