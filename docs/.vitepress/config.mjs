import { defineConfig } from 'vitepress'

// Served from the custom domain https://handlr.dev/ (see docs/public/CNAME).
// If you ever revert to GitHub Pages project pages, set `base` back to
// '/handlr-mono/' and remove the CNAME.
export default defineConfig({
  title: 'Handlr',
  description: 'A lightweight PHP Pipe + Handler framework and its HTMX frontend toolkit.',
  base: '/',
  cleanUrls: true,
  lastUpdated: true,

  head: [['link', { rel: 'icon', type: 'image/png', href: '/handlr.png' }]],

  // Keep internal ops notes out of the published site.
  srcExclude: ['BACKLOG.md', '**/README.md'],

  themeConfig: {
    // Wordmark already reads "HANDLR" — show the logo, drop the duplicate text.
    logo: '/handlr.png',
    siteTitle: false,

    search: { provider: 'local' },

    nav: [
      { text: 'Guide', link: '/getting-started/installation' },
      { text: 'Backend', link: '/backend/' },
      { text: 'Frontend', link: '/frontend/' },
      { text: 'Modules', link: '/modules/' },
      { text: 'Principles', link: '/principles' },
      { text: 'Roadmap', link: '/roadmap' },
      {
        text: 'Packages',
        items: [
          { text: 'handlr-backend (Packagist)', link: 'https://packagist.org/packages/phillipsharring/handlr-backend' },
          { text: 'handlr-frontend (npm)', link: 'https://www.npmjs.com/package/@phillipsharring/handlr-frontend' },
          { text: 'handlr-build (npm)', link: 'https://www.npmjs.com/package/@phillipsharring/handlr-build' },
        ],
      },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        collapsed: false,
        items: [
          { text: 'Installation', link: '/getting-started/installation' },
          { text: 'Principles', link: '/principles' },
          { text: 'Your First App', link: '/getting-started/first-app' },
        ],
      },
      {
        text: 'Backend',
        collapsed: false,
        items: [
          { text: 'Overview', link: '/backend/' },
          { text: 'Core Concepts', link: '/backend/concepts' },
          { text: 'The Container', link: '/backend/container' },
          { text: 'Routing & Junctions', link: '/backend/routing' },
          { text: 'Authorization', link: '/backend/authorization' },
          { text: 'Validation', link: '/backend/validation' },
          { text: 'Database', link: '/backend/database' },
          { text: 'API Responses', link: '/backend/api-responses' },
          { text: 'Auth', link: '/backend/auth' },
          { text: 'Events & Listeners', link: '/backend/events' },
          { text: 'Service Providers', link: '/backend/service-providers' },
          { text: 'CLI & Makers', link: '/backend/cli' },
        ],
      },
      {
        text: 'Frontend',
        collapsed: false,
        items: [
          { text: 'Overview', link: '/frontend/' },
          { text: 'Build (handlr-build)', link: '/frontend/build' },
          { text: 'Runtime (handlr-frontend)', link: '/frontend/runtime' },
          { text: 'Declarative Behavior', link: '/frontend/declarative' },
          { text: 'HTMX Patterns', link: '/frontend/htmx' },
          { text: 'Modals & Toasts', link: '/frontend/ui' },
          { text: 'CSRF', link: '/frontend/csrf' },
          { text: 'Auth State', link: '/frontend/auth-state' },
        ],
      },
      {
        text: 'Modules',
        collapsed: false,
        items: [
          { text: 'Writing a Module', link: '/modules/' },
          { text: 'Landing (email capture)', link: '/modules/landing' },
          { text: 'A/B Testing', link: '/modules/ab' },
        ],
      },
      {
        text: 'Project',
        collapsed: false,
        items: [
          { text: 'Roadmap', link: '/roadmap' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/phillipsharring/handlr-mono' },
    ],

    editLink: {
      pattern: 'https://github.com/phillipsharring/handlr-mono/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    outline: 'deep',

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © Phillip Harrington',
    },
  },
})
