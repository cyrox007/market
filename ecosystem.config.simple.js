module.exports = {
  apps: [
    {
      name: 'react-frontend',
      script: 'npm',
      args: 'run dev',
      interpreter: 'none',
      cwd: process.cwd(),
      env: {
        NODE_ENV: 'development',
      },
      error_file: './logs/error.log',
      out_file: './logs/out.log',
      log_file: './logs/combined.log',
      time: true,
      autorestart: true,
      watch: false,
      max_memory_restart: '1G',
    },
  ],
};




