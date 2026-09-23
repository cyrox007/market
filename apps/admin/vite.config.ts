import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react-swc'

const backend = process.env.ADMIN_BACKEND || 'http://svetofor.local'

const proxy = {
  target: backend,
  changeOrigin: true,
  secure: false,
  cookieDomainRewrite: 'localhost',
}

export default defineConfig({
  plugins: [react()],
  server: {
    port: 5174,
    strictPort: true,
    proxy: {
      '/api': proxy,
      '/admin_sv': proxy,
      '/livewire': proxy,
      '/filament': proxy,
      '/storage': proxy,
      '/build': proxy,
      '/css': proxy,
      '/js': proxy,
      '/favicon.ico': proxy,
    },
  },
})
