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
        '10': ['10px', { lineHeight: 'normal' }],
        '12': ['12px', { lineHeight: 'normal' }],
        '14': ['14px', { lineHeight: 'normal' }],
        '16': ['16px', { lineHeight: 'normal' }],
        '18': ['18px', { lineHeight: 'normal' }],
        '24': ['24px', { lineHeight: 'normal' }],
        '52': ['52px', { lineHeight: 'normal' }],
      },

      borderRadius: {
        badge: '5px', // мелкие лейблы «до −60%»
        btn: '12px', // кнопки
        pill: '100px', // поле поиска, круглые иконочные кнопки
      },

      boxShadow: {
        // Единственная тень в макете (Figma: Shadow)
        card: '0 2px 10px 0 rgba(20, 20, 20, 0.18)',
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
