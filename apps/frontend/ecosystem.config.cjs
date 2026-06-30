module.exports = {
  apps: [
    {
      name: 'react-ssr-prod',
      script: 'node_modules/.bin/tsx',
      args: 'server/index.ts',
      cwd: process.cwd(),
      interpreter: 'node',
      env: {
        NODE_ENV: 'production',
        PORT: 3000,
        BASE_PATH: '/',
        // VITE_API_BASE_URL будет загружен из .env файла через dotenv
        // Если нужно переопределить, можно задать здесь:
        // VITE_API_BASE_URL: 'https://api.yourdomain.com/api/v1',
      },
      error_file: './logs/react-ssr-prod-error.log',
      out_file: './logs/react-ssr-prod-out.log',
      log_file: './logs/react-ssr-prod-combined.log',
      time: true,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
      instances: 2,
      exec_mode: 'cluster',
    },
  ],
};
