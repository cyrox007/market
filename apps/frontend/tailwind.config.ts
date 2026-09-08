/** @type {import('tailwindcss').Config} */

/**
 * Дизайн-токены перенесены из Figma Variables.
 * страница ui-kit → Section 2.
 *
 *   Brand/Yellow      → brand-yellow        Inter-14-600 → text-14 font-semibold
 *   Text/Primary      → ink                 Onest-52-700 → font-display text-52 font-bold
 *   Surface/Grey      → surface-grey
 *
 *
 *
 */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      /**
       * Брейкпоинты макета (заданы владельцем 07.09.2026):
       *
       *   md   до 979   основной
       *   sm   до 768   опциональный
       *   vsm  до 549   основной
       *
       * Объявлены как min-width на единицу больше, а пользоваться ими нужно
       * через встроенные варианты `max-*`, которые Tailwind выводит отсюда сам:
       *
       *   max-md:   ≤ 979.98
       *   max-sm:   ≤ 768.98
       *   max-vsm:  ≤ 549.98
       *
       * Почему не `md: { max: '979px' }`, хотя так короче: имена `sm` и `md`
       * уже заняты Tailwind и означают min-width. В существующей вёрстке Readdy
       * `md:` встречается 224 раза, `sm:` — 89. Объявление их как max-width
       * инвертировало бы каждое из этих мест разом и молча.
       *
       * ⚠️ Значения `sm` и `md` при этом всё равно сдвигаются: 640 → 769 и
       * 768 → 980. Старая вёрстка не ломается, но переключается позже. lg, xl
       * и 2xl оставлены дефолтными — их в макете не смотрели.
       */
      screens: {
        vsm: '550px',
        sm: '769px',
        md: '980px',
      },

      colors: {
        // Brand/* — фирменные цвета
        brand: {
          yellow: '#FFD000', // основное действие
          green: '#0A6044', // hover основной кнопки, подвал
          red: '#E23B2E', // распродажа, ошибки
        },
        // Text/* — цвета текста
        ink: {
          DEFAULT: '#141414', // Text/Primary
          secondary: '#6B6B6B', // Text/Secondary
          inverse: '#FFFFFF', // Text/White
        },
        // Surface/* — фоны и границы
        surface: {
          DEFAULT: '#FFFFFF', // Surface/White
          grey: '#F5F3F1', // Surface/Grey
          border: '#E8E5E1', // Surface/Border
          stroke: '#141414', // Surface/Stroke, применяется с прозрачностью
        },
      },

      fontFamily: {
        // Переопределяет дефолтный стек Tailwind: Inter становится шрифтом всего сайта
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Arial', 'sans-serif'],
        // Только крупные заголовки
        display: ['Onest', 'Inter', 'system-ui', 'sans-serif'],
      },

      /**
       * Кегли названы по размеру в px — так же, как переменные в Figma
       * (Inter-14-500 → text-14 font-medium). Числовые ключи не пересекаются
       * с дефолтной шкалой Tailwind (xs / sm / base / …), поэтому существующая
       * вёрстка не затрагивается.
       *
       * lineHeight: 'normal' — подтверждено дизайнером и проверено по компонентам
       * макета. Значение 100 %, которое отдаёт инструмент чтения Figma, — это Auto.
       */
      fontSize: {
        '8': ['8px', { lineHeight: 'normal' }], // замерено: счётчик цветов «+8» на карточке
        '10': ['10px', { lineHeight: 'normal' }],
        '12': ['12px', { lineHeight: 'normal' }],
        '14': ['14px', { lineHeight: 'normal' }],
        '15': ['15px', { lineHeight: 'normal' }], // замерено: левая колонка меню шапки
        '16': ['16px', { lineHeight: 'normal' }],
        '18': ['18px', { lineHeight: 'normal' }],
        '20': ['20px', { lineHeight: 'normal' }], // замерено: цена на карточке товара
        '24': ['24px', { lineHeight: 'normal' }],
        '52': ['52px', { lineHeight: 'normal' }],
      },

      borderRadius: {
        swatch: '4px', // замерено: образцы цвета на карточке товара, 24×24
        badge: '6px', // замерено: бейдж «до −60%» в навигации, единственный не-пилюля
        btn: '12px', // кнопки
        pill: '100px', // поле поиска, круглые иконочные кнопки
      },

      boxShadow: {
        // Figma: Shadow
        card: '0 2px 10px 0 rgba(20, 20, 20, 0.18)',
        // Замерено 07.09.2026 у круглых кнопок на карточке товара:
        // Drop shadow X 0, Y 2, Blur 3, Spread 0, #000000 10 %.
        // Оказалось, что «единственной тени в макете» не бывает — их две.
        btn: '0 2px 3px 0 rgba(0, 0, 0, 0.1)',
      },

      /**
       * Движения в макете нет: дизайнер подтвердил 07.09.2026, что нарисованы
       * просто два состояния и переход между ними не предполагался. Примитивы
       * меняют цвет мгновенно.
       *
       * Это заготовка на случай, если решение поменяется: классы duration-fast
       * и duration-slow доступны, но сами по себе ничего не включают — нужен
       * ещё transition-*. DEFAULT намеренно НЕ переопределяем, иначе молча
       * поменялось бы поведение 173 существующих transition-colors и
       * 36 transition-all по всему сайту.
       */
      transitionDuration: {
        fast: '120ms',
        slow: '320ms',
      },

      keyframes: {
        fadeIn: {
          '0%': { opacity: '0', transform: 'scale(0.95)' },
          '100%': { opacity: '1', transform: 'scale(1)' },
        },
      },
      animation: {
        fadeIn: 'fadeIn 0.2s ease-out',
      },
    },
  },
  plugins: [],
};
