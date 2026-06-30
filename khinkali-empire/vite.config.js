import { defineConfig } from 'vite';

// Relative base so the build works under any GitHub Pages sub-path
// (e.g. https://user.github.io/khinkali-empire/).
export default defineConfig({
  base: './',
  build: {
    target: 'es2019',
    outDir: 'dist',
    assetsDir: 'assets',
    sourcemap: false,
  },
  server: {
    host: true,
    port: 5173,
  },
});
