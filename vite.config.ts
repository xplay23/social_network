import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { cpSync } from 'node:fs'
import { basename, resolve } from 'node:path'

const outputDirectory = resolve('dist')

function copyPhpApi() {
  return {
    name: 'copy-php-api',
    closeBundle() {
      cpSync(resolve('api'), resolve(outputDirectory, 'api'), {
        recursive: true,
        filter: (source) => basename(source) !== 'config.php',
      })
    },
  }
}

export default defineConfig({
  base: '/social-vue/',
  plugins: [vue(), copyPhpApi()],
  build: {
    outDir: outputDirectory,
    emptyOutDir: true,
  },
})
