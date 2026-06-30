module.exports = {
  apps: [
    {
      name: 'react-dev',
      script: 'npx',
      args: 'vite --host 0.0.0.0 --port 3000',
      cwd: './apps/frontend',
      interpreter: 'none',
      env: {
        NODE_ENV: 'development',
      },
      error_file: './logs/react-dev-error.log',
      out_file: './logs/react-dev-out.log',
      log_file: './logs/react-dev-combined.log',
      time: true,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
    },
  ],
};




