import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react-swc'

export default defineConfig({
  base: '/admin-ui/',
  plugins: [react()],
  build: {
    outDir: '../../services/backend/public/admin-ui',
    emptyOutDir: true,
  },
  server: {
    port: 5174,
    strictPort: true,
  },
})
