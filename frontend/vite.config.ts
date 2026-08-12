import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],

  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,

    hmr: {
      protocol: 'wss',
      host: 'mapalotow.test',
      clientPort: 443,
    },

    proxy: {
      '/api': {
        target: 'https://mapalotow.test',
        changeOrigin: true,
        secure: false,
      },
    },
  },
})