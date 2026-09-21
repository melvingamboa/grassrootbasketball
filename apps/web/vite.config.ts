import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.VITE_API_PROXY_TARGET || 'http://localhost:8010'
  const hmrClientPort = Number(env.VITE_HMR_CLIENT_PORT || 5175)

  return {
    plugins: [react(), tailwindcss()],
    server: {
      host: '0.0.0.0',
      port: 5173,
      hmr: { clientPort: hmrClientPort },
      proxy: {
        '/api': { target: apiTarget, changeOrigin: true },
        '/sanctum': { target: apiTarget, changeOrigin: true },
      },
    },
  }
})
