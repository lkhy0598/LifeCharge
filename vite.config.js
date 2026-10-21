import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  base: '/LifeCharge/',
  plugins: [vue()],
  server: {
    watch: {
      ignored: ['**/backend/**'],
    },
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8080',
        changeOrigin: true,
        rewrite: path => {
          const targetPath = path.replace(
            /^\/api\/(health|games|csrf|me|login|logout|register)(?=\?|$)/,
            '/api/$1.php'
          )

          return `/lifecharge-backend${targetPath}`
        },
      },
    },
  },
})
