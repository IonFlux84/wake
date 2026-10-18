import { defineConfig } from 'vite'

// Builds the editor into resources/dist/editor.js. The built file is
// committed, so servers never need Node (decision 0003).
export default defineConfig({
  build: {
    outDir: 'resources/dist',
    emptyOutDir: false,
    lib: {
      entry: 'resources/js/editor.js',
      name: 'WakeEditor',
      formats: ['iife'],
      fileName: () => 'editor.js',
    },
    minify: true,
    sourcemap: false,
  },
})
