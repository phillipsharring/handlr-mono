import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { handlrBuild } from '@phillipsharring/handlr-build/vite';
import { resolveModuleDirs } from '@phillipsharring/handlr-build/module-dirs';
import siteConfig from './site.config.js';

// Resolve enabled modules into the multi-root pagesDirs / componentsDirs that
// handlr-build expects. App's own content/* roots come first so app routes
// always sort before module-contributed routes; conflict detection in
// handlr-build will throw if a module tries to claim an app-owned route.
const projectRoot = path.dirname(fileURLToPath(import.meta.url));
const modules = Array.isArray(siteConfig.modules) ? siteConfig.modules : [];
const { pagesDirs: modulePagesDirs, componentsDirs: moduleComponentsDirs } =
    resolveModuleDirs(projectRoot, modules);

const pagesDirs = [path.join(projectRoot, 'content', 'pages'), ...modulePagesDirs];
const componentsDirs = [path.join(projectRoot, 'content', 'components'), ...moduleComponentsDirs];

// Optional dev allowlist for the Vite host check. Set HANDLR_ALLOWED_HOSTS to
// a comma-separated list (e.g. "myapp.test") when serving the dev server
// behind a reverse proxy. Vite's default host check rejects unknown Host
// headers; this is the supported escape hatch.
const allowedHosts = (process.env.HANDLR_ALLOWED_HOSTS || '')
    .split(',')
    .map((h) => h.trim())
    .filter(Boolean);

export default defineConfig({
    root: 'src',
    publicDir: '../public',
    optimizeDeps: {
        entries: ['app.js'],
        include: ['htmx.org', 'handlebars', 'sortablejs'],
    },
    server: {
        ...(allowedHosts.length > 0 ? { allowedHosts } : {}),
        proxy: {
            '/api': {
                target: process.env.HANDLR_ORIGIN || 'http://localhost:8000',
                changeOrigin: false,
                secure: false,
            },
        },
    },
    plugins: [tailwindcss(), handlrBuild({ siteConfig, pagesDirs, componentsDirs })],
    build: {
        outDir: '../dist',
        assetsDir: 'assets',
        manifest: true,
        emptyOutDir: true,
        rollupOptions: {
            input: { app: './src/app.js' },
            onwarn(warning, warn) {
                if (warning?.code === 'EVAL' && typeof warning?.id === 'string' && warning.id.includes('/node_modules/htmx.org/')) return;
                warn(warning);
            },
        },
    },
});
