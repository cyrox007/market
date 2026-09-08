import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';
import prettier from 'eslint-config-prettier';

/**
 * Плоский конфиг ESLint 9.
 *
 * Правила намеренно мягкие: ужесточение будет по мере редизайна
 */
export default tseslint.config(
  {
    ignores: [
      'dist/**',
      'node_modules/**',
      '.vite/**',
      'auto-imports.d.ts', // генерируется unplugin-auto-import
    ],
  },

  {
    files: ['**/*.{ts,tsx}'],
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,

      'no-undef': 'off',

      /**
       * Предупреждение в легаси 23 нарушения, и часть из них —
       * не мусор, а следы незавершённых фич. Пример: в checkout не вызывается
       * setAssemblyNeeded, из-за чего сборка мебели всегда false.
       */
      '@typescript-eslint/no-unused-vars': [
        'warn',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_', caughtErrors: 'none' },
      ],

      // Массово нарушается в легаси — предупреждение, чтобы не блокировать работу
      '@typescript-eslint/no-explicit-any': 'warn',
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-empty': ['warn', { allowEmptyCatch: true }],
      'no-useless-catch': 'warn',
      'no-unused-expressions': 'warn',

      // Vite HMR: файл-компонент должен экспортировать только компоненты
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],
    },
  },

  // Тесты: свои глобальные переменные vitest
  {
    files: ['**/*.test.{ts,tsx}', 'src/test/**'],
    languageOptions: {
      globals: { ...globals.node },
    },
    rules: {
      '@typescript-eslint/no-explicit-any': 'off',
      'no-console': 'off',
      // В тестах моки хуков называются по-своему, правило здесь ложно срабатывает
      'react-hooks/rules-of-hooks': 'off',
    },
  },

  // Конфиги и серверная часть — окружение Node
  {
    files: ['*.config.{js,ts}', 'server/**/*.ts'],
    languageOptions: {
      globals: { ...globals.node },
    },
    rules: {
      'no-console': 'off',
    },
  },

  prettier,
);
