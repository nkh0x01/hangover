import { defineConfig } from 'vite';

// Vite configuration for Khinkali Empire.
// - base: './' keeps asset paths relative so the built `dist/` works inside a
//   Capacitor webview (which serves from the filesystem, not a web root).
// - publicDir: the default `public/` folder is copied verbatim into dist/.
export default defineConfig({
  base: './',
  server: {
    host: true,
    port: 5173,
  },
  build: {
    target: 'es2019',
    outDir: 'dist',
    assetsDir: 'assets',
    sourcemap: false,
  },
});
