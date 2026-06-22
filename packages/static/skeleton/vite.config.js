import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { grasprBuild } from '@phillipsharring/graspr-build/vite';
import { resolveModuleDirs } from '@phillipsharring/graspr-build/modules';
import siteConfig from './site.config.js';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));
const modules = Array.isArray(siteConfig.modules) ? siteConfig.modules : [];
const { pagesDirs: modulePagesDirs, componentsDirs: moduleComponentsDirs } =
    resolveModuleDirs(projectRoot, modules);

const pagesDirs = [path.join(projectRoot, 'content', 'pages'), ...modulePagesDirs];
const componentsDirs = [path.join(projectRoot, 'content', 'components'), ...moduleComponentsDirs];

export default defineConfig({
    root: 'src',
    publicDir: '../public',
    plugins: [tailwindcss(), grasprBuild({ siteConfig, pagesDirs, componentsDirs })],
    build: {
        outDir: '../dist',
        assetsDir: 'assets',
        manifest: true,
        emptyOutDir: true,
        rollupOptions: {
            input: { app: './src/app.js' },
        },
    },
});
